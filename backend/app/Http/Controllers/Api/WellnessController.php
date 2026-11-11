<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\{SupportMessage, RecommendationFeedback, AudioTrack, Article, Exercise};
use App\Services\{SupportChatService, SupportKnowledge, WellnessRecommendationService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Cache, DB, RateLimiter};

class WellnessController extends Controller
{
    public function config()
    {
        return response()->json(['success'=>true, 'cloud_available'=>(bool) config('wellness.cloud_enabled') && trim((string) config('wellness.gemini_key')) !== '',
            'provider'=>'Google Gemini', 'retention_days'=>30, 'consent_version'=>'gemini-v1']);
    }
    public function recommendations(Request $request, WellnessRecommendationService $service)
    {
        $data = $request->validate(['city'=>'nullable|string|max:100']);
        return response()->json(['success'=>true] + $service->build($request->user(), $data['city'] ?? null));
    }
    public function feedback(Request $request)
    {
        $data = $request->validate(['item_type'=>'required|in:audio,article,exercise', 'item_id'=>'required|integer|min:1', 'rating'=>'required|integer|in:-1,0,1']);
        $model = ['audio'=>AudioTrack::class, 'article'=>Article::class, 'exercise'=>Exercise::class][$data['item_type']];
        abort_unless($model::whereKey($data['item_id'])->where('is_published', true)->exists(), 404);
        DB::transaction(function () use ($request, $data) {
            \App\Models\User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            RecommendationFeedback::updateOrCreate(['user_id'=>$request->user()->id, 'item_type'=>$data['item_type'], 'item_id'=>$data['item_id']], ['rating'=>$data['rating']]);
        });
        return response()->json(['success'=>true]);
    }
    public function clearFeedback(Request $request)
    {
        RecommendationFeedback::where('user_id', $request->user()->id)->where('item_type', 'audio')->delete();
        return response()->json(['success'=>true]);
    }
    public function messages(Request $request)
    {
        $this->prune($request->user()->id);
        $messages = SupportMessage::where('user_id', $request->user()->id)->orderByDesc('id')->limit(40)->get()->reverse()->values();
        return response()->json(['success'=>true, 'data'=>$messages]);
    }
    public function send(Request $request, SupportChatService $service)
    {
        $data = $request->validate(['message'=>'required|string|max:2000', 'consent'=>'required|accepted',
            'cloud_consent'=>'sometimes|boolean', 'request_key'=>'required|string|max:64|regex:/^[a-zA-Z0-9_-]+$/']);
        $message = trim($data['message']);
        abort_if($message === '', 422, 'اكتب رسالة أولاً.');
        $cloudConsent = (bool) ($data['cloud_consent'] ?? false);
        $fingerprint = hash_hmac('sha256', $message.'|'.($cloudConsent ? 'cloud' : 'local'), (string) config('app.key'));
        $userId = $request->user()->id;
        $lock = Cache::lock('support-chat:'.$userId, 60);
        if (!$lock->get()) return response()->json(['message'=>'انتظر الرد الحالي ثم حاول مجدداً.'], 409);
        try {
            $this->prune($userId);
            $existing = SupportMessage::where('user_id', $userId)->where('request_key', $data['request_key'])->first();
            if ($existing) {
                if ($existing->request_fingerprint && !hash_equals($existing->request_fingerprint, $fingerprint)) {
                    return response()->json(['message'=>'مفتاح الإرسال مستخدم لرسالة مختلفة.'], 409);
                }
                return response()->json(['success'=>true, 'message'=>$existing, 'mode'=>$existing->mode]);
            }
            $history = SupportMessage::where('user_id', $userId)->orderByDesc('id')->limit(8)->get()
                ->reverse()->map(fn ($m) => ['role'=>$m->role, 'content'=>$m->content])->values()->all();
            $limited = false;
            $cloud = $cloudConsent && config('wellness.cloud_enabled') && trim((string) config('wellness.gemini_key')) !== '';
            if ($cloud && !(new SupportKnowledge)->urgent($message)) {
                $quota = 'gemini-daily:'.$userId.':'.now('UTC')->format('Y-m-d');
                if (RateLimiter::tooManyAttempts($quota, (int) config('wellness.daily_cloud_limit', 40))) {
                    $cloud = false; $limited = true;
                } else { RateLimiter::hit($quota, 86400); }
            }
            $reply = $service->respond($message, $history, $cloud);
            if ($limited && !$reply['urgent']) $reply['mode'] = 'local_limit';
            $saved = DB::transaction(function () use ($userId, $message, $reply, $data, $fingerprint) {
                $owner = \App\Models\User::whereKey($userId)->lockForUpdate()->first();
                abort_unless($owner && $owner->status !== 'blocked', 403);
                SupportMessage::create(['user_id'=>$userId, 'role'=>'user', 'content'=>$message, 'mode'=>'user']);
                return SupportMessage::create(['user_id'=>$userId, 'role'=>'assistant', 'content'=>$reply['content'],
                    'sources'=>$reply['sources'], 'urgent'=>$reply['urgent'], 'request_key'=>$data['request_key'],
                    'request_fingerprint'=>$fingerprint, 'mode'=>$reply['mode']]);
            });
            return response()->json(['success'=>true, 'message'=>$saved, 'mode'=>$reply['mode']], 201);
        } finally { $lock->release(); }
    }
    public function clear(Request $request)
    {
        $lock = Cache::lock('support-chat:'.$request->user()->id, 60);
        if (!$lock->get()) return response()->json(['message'=>'انتظر الرد الحالي ثم حاول مجدداً.'], 409);
        try { SupportMessage::where('user_id', $request->user()->id)->delete(); }
        finally { $lock->release(); }
        return response()->json(['success'=>true]);
    }
    private function prune(int $userId): void
    {
        SupportMessage::where('user_id', $userId)->where('created_at', '<', now()->subDays(30))->delete();
    }
}
