<?php
namespace App\Services;
use App\Models\{AudioTrack, SosCounselor, User, WellnessProfile, RecommendationFeedback};
use App\Http\Resources\{AudioTrackResource, SosCounselorResource};
use Carbon\CarbonImmutable;

class WellnessRecommendationService
{
    public function build(User $user, ?string $city = null): array
    {
        $today = CarbonImmutable::today('Asia/Damascus');
        // One most recent check-in per calendar day avoids repeated same-day entries dominating.
        $daily = $user->moodLogs()->with('factors')->where('created_at', '>=', now()->subDays(30))
            ->orderByDesc('id')->lazy(100)->unique(fn ($log) => $log->created_at->copy()->timezone('Asia/Damascus')->toDateString())->take(14)->collect();
        $logs = $daily->map(fn ($log) => ['date'=>$log->created_at->copy()->timezone('Asia/Damascus')->toDateString(),
            'age_days'=>(int) abs($today->diffInDays(CarbonImmutable::parse($log->created_at)->timezone('Asia/Damascus')->startOfDay())),
            'score'=>$log->mood_score, 'factors'=>$log->factors->pluck('slug')->all()])->values()->all();
        $ranker = new WellnessRanker;
        $profile = $ranker->profile($logs, $user->supportMethods()->pluck('support_methods.slug')->all(), $user->goals()->pluck('goals.id')->all());
        \Illuminate\Support\Facades\DB::transaction(function () use ($user, $profile) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            WellnessProfile::updateOrCreate(['user_id'=>$user->id], ['snapshot'=>$profile]);
        });
        $feedback = RecommendationFeedback::where('user_id', $user->id)->where('item_type', 'audio')->pluck('rating', 'item_id');
        $completed = $user->activityCompletions()->where('activity_type', AudioTrack::class)->where('created_at', '>=', now()->subDays(7))->get()->countBy('activity_id');
        $ranked = AudioTrack::where('is_published', true)->orderBy('sort_order')->orderBy('id')->get()->map(function ($track) use ($ranker, $profile, $feedback, $completed) {
            $rank = $ranker->audio($track->toArray(), $profile, (int) ($feedback[$track->id] ?? 0), (int) ($completed[$track->id] ?? 0));
            return $rank ? ['track'=>$track, 'rank'=>$rank] : null;
        })->filter()->sortByDesc(fn ($entry) => $entry['rank']['score'])->take(3)->values();
        $tracks = $ranked->map(fn ($entry) => array_merge((new AudioTrackResource($entry['track']))->resolve(),
            ['recommendation_reason'=>$entry['rank']['reason'], 'feedback_rating'=>(int) ($feedback[$entry['track']->id] ?? 0)]));
        $counselors = $profile['offer_counselors']
            ? SosCounselor::where('is_active', true)->when($city, fn ($q) => $q->where('city', $city))->orderBy('sort_order')->orderBy('id')->limit(3)->get()
            : collect();
        return ['profile'=>$profile, 'tracks'=>$tracks, 'counselors'=>SosCounselorResource::collection($counselors),
            'counselor_reason'=>'أسماء من الدليل النشط، وليست مطابقة طبية لاختصاص أو ضماناً للتوفر.'];
    }
}
