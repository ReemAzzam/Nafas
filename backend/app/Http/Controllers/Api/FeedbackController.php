<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Feedback\StoreFeedbackRequest;
use App\Http\Resources\FeedbackResource;
use App\Services\FeedbackService;

class FeedbackController extends Controller
{
    public function __construct(private FeedbackService $feedback) {}

    public function store(StoreFeedbackRequest $request)
    {
        $feedback = $this->feedback->create(
            $request->user(),
            $request->integer('rating'),
            $request->validated('message')
        );

        return response()->json([
            'success' => true,
            'message' => 'شكرًا لمشاركتنا رأيك.',
            'feedback' => FeedbackResource::make($feedback),
        ], 201);
    }
}
