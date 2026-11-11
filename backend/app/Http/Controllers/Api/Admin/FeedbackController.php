<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\ReviewFeedbackRequest;
use App\Http\Resources\Admin\AdminFeedbackResource;
use App\Models\Feedback;
use Illuminate\Http\Request;

class FeedbackController extends Controller
{
    public function index(Request $request)
    {
        $feedback = Feedback::query()
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->when($request->query('rating'), fn ($query, $rating) => $query->where('rating', $rating))
            ->with('user')
            ->latest('id')
            ->paginate(max(1, min((int) $request->query('per_page', 20), 100)));

        return AdminFeedbackResource::collection($feedback)->additional(['success' => true]);
    }

    public function show(Feedback $feedback)
    {
        return response()->json([
            'success' => true,
            'feedback' => AdminFeedbackResource::make($feedback->load('user')),
        ]);
    }

    public function review(ReviewFeedbackRequest $request, Feedback $feedback)
    {
        $feedback->update([
            'status' => 'reviewed',
            'admin_note' => $request->validated('admin_note'),
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تمت مراجعة الملاحظة.',
            'feedback' => AdminFeedbackResource::make($feedback->fresh()->load('user')),
        ]);
    }
}
