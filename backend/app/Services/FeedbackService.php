<?php

namespace App\Services;

use App\Models\Feedback;
use App\Models\User;

class FeedbackService
{
    public function create(User $user, int $rating, ?string $message): Feedback
    {
        return $user->feedbacks()->create([
            'rating' => $rating,
            'message' => $message,
        ]);
    }
}
