<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Sos\UpsertSosContactRequest;
use App\Http\Resources\ExerciseResource;
use App\Http\Resources\SosContactResource;
use App\Http\Resources\SosCounselorResource;
use App\Http\Resources\SosHotlineResource;
use App\Models\SosContact;
use App\Models\SosCounselor;
use App\Models\SosHotline;
use App\Services\SosAnalyticsService;
use App\Services\SosCalmingService;
use App\Support\SyrianPhone;

class SosController extends Controller
{
    public function __construct(
        private SosCalmingService $calmingService,
        private SosAnalyticsService $analytics,
    ) {}

    public function index()
    {
        $user = request()->user();
        $this->analytics->recordOpen();

        return response()->json([
            'success' => true,
            'hotlines' => SosHotlineResource::collection(
                SosHotline::query()->where('is_active', true)->orderBy('sort_order')->get()
            ),
            'counselors' => SosCounselorResource::collection(
                SosCounselor::query()->where('is_active', true)->orderBy('sort_order')->get()
            ),
            'contacts' => SosContactResource::collection(
                $user->sosContacts()->latest()->get()
            ),
            'calming_options' => ExerciseResource::collection(
                $this->calmingService->options()
            ),
        ]);
    }

    public function storeContact(UpsertSosContactRequest $request)
    {
        $user = $request->user();

        if ($user->sosContacts()->count() >= 5) {
            return response()->json([
                'success' => false,
                'message' => 'يمكنك إضافة خمسة أشخاص كحد أقصى.',
            ], 422);
        }

        $contact = $user->sosContacts()->create([
            'name' => $request->validated('name'),
            'phone' => SyrianPhone::e164($request->validated('phone')),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تمت إضافة جهة الاتصال.',
            'contact' => SosContactResource::make($contact),
        ], 201);
    }

    public function updateContact(UpsertSosContactRequest $request, SosContact $contact)
    {
        abort_unless($contact->user_id === $request->user()->id, 404);

        $contact->update([
            'name' => $request->validated('name'),
            'phone' => SyrianPhone::e164($request->validated('phone')),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث جهة الاتصال.',
            'contact' => SosContactResource::make($contact),
        ]);
    }

    public function destroyContact(SosContact $contact)
    {
        abort_unless($contact->user_id === request()->user()->id, 404);

        $contact->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف جهة الاتصال.',
        ]);
    }
}
