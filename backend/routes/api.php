<?php

use App\Http\Controllers\Api\Admin\ArticleController as AdminArticleController;
use App\Http\Controllers\Api\Admin\AudioTrackController as AdminAudioTrackController;
use App\Http\Controllers\Api\Admin\CommentModerationController as AdminCommentModerationController;
use App\Http\Controllers\Api\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Api\Admin\ExerciseController as AdminExerciseController;
use App\Http\Controllers\Api\Admin\FeedbackController as AdminFeedbackController;
use App\Http\Controllers\Api\Admin\SosController as AdminSosController;
use App\Http\Controllers\Api\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\ArticleCommentController;
use App\Http\Controllers\Api\ArticleFavoriteController;
use App\Http\Controllers\Api\AudioTrackController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ContentController;
use App\Http\Controllers\Api\FeedbackController;
use App\Http\Controllers\Api\HomeController;
use App\Http\Controllers\Api\JournalController;
use App\Http\Controllers\Api\MoodLogController;
use App\Http\Controllers\Api\OnboardingController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ProgressInsightController;
use App\Http\Controllers\Api\SosController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // Auth Routes (Public)
    Route::prefix('auth')->group(function () {

        // Rate Limiting: 3 requests per minute for OTP verification and resending
        Route::middleware('throttle:auth')->group(function () {
            Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
            Route::post('/resend-otp', [AuthController::class, 'resendOtp']);
            Route::post('/register', [AuthController::class, 'register']);
            Route::post('/login', [AuthController::class, 'login']);
            Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
            Route::post('/reset-password', [AuthController::class, 'resetPassword']);
        });
    });

    // Protected Routes ( Sanctum Token)
    Route::middleware('auth:sanctum', 'blocked')->group(function () {
        Route::delete('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);

        Route::post('/auth/change-password', [AuthController::class, 'changePassword']);

        Route::get('/home', [HomeController::class, 'show']);
        Route::middleware(\App\Http\Middleware\NoStoreWellness::class)->group(function () {
        Route::get('/study-plan', [\App\Http\Controllers\Api\PracticalPlanController::class, 'show']);
        Route::post('/study-plan', [\App\Http\Controllers\Api\PracticalPlanController::class, 'create'])->middleware('throttle:4,1');
        Route::patch('/study-plan', [\App\Http\Controllers\Api\PracticalPlanController::class, 'update'])->middleware('throttle:60,1');
        Route::delete('/study-plan', [\App\Http\Controllers\Api\PracticalPlanController::class, 'delete'])->middleware('throttle:10,1');
        Route::get('/reminder-preferences', [\App\Http\Controllers\Api\PracticalPlanController::class, 'preferences']);
        Route::put('/reminder-preferences', [\App\Http\Controllers\Api\PracticalPlanController::class, 'savePreferences'])->middleware('throttle:10,1');
        Route::get('/wellness/recommendations', [\App\Http\Controllers\Api\WellnessController::class, 'recommendations']);
        Route::put('/wellness/feedback', [\App\Http\Controllers\Api\WellnessController::class, 'feedback'])->middleware('throttle:30,1');
        Route::delete('/wellness/feedback', [\App\Http\Controllers\Api\WellnessController::class, 'clearFeedback'])->middleware('throttle:10,1');
        Route::get('/support/config', [\App\Http\Controllers\Api\WellnessController::class, 'config']);
        Route::get('/support/messages', [\App\Http\Controllers\Api\WellnessController::class, 'messages']);
        Route::post('/support/messages', [\App\Http\Controllers\Api\WellnessController::class, 'send'])->middleware('throttle:10,1');
        Route::delete('/support/messages', [\App\Http\Controllers\Api\WellnessController::class, 'clear']);
        });


        Route::prefix('profile')->group(function () {
            Route::get('/', [ProfileController::class, 'show']);
            Route::patch('/', [ProfileController::class, 'update']);
            Route::delete('/', [ProfileController::class, 'destroy']);
            Route::post('/image', [ProfileController::class, 'updateImage']);
            Route::delete('/image', [ProfileController::class, 'deleteImage']);
            Route::post('/email/request', [ProfileController::class, 'requestEmailChange'])
                ->middleware('throttle:profile-email-request');
            Route::post('/email/verify', [ProfileController::class, 'verifyEmailChange'])
                ->middleware('throttle:profile-email-verify');
        });

        Route::prefix('onboarding')->group(function () {

            Route::post('/goals', [OnboardingController::class, 'saveGoals']);
            Route::post('/support-methods', [OnboardingController::class, 'saveSupportMethods']);
            Route::post('/generate-plan', [OnboardingController::class, 'generatePlan']);
            Route::get('/plans/current', [OnboardingController::class, 'getCurrentPlan']);
            Route::get('/goals', [OnboardingController::class, 'goals']);
            Route::get('/support-methods', [OnboardingController::class, 'supportMethods']);
            Route::post('/skip', [OnboardingController::class, 'skip']);

        });

        Route::prefix('mood-logs')->group(function () {
            Route::get('/latest', [MoodLogController::class, 'latest']);
            Route::post('/', [MoodLogController::class, 'store'])->middleware('throttle:mood-logs');
            Route::post('/{moodLog}/next-suggestion', [MoodLogController::class, 'nextSuggestion']);
        });
        Route::get('/mood/factors', [MoodLogController::class, 'factors']);

        Route::prefix('exercises')->group(function () {
            Route::get('/', [ContentController::class, 'exercises']);
            Route::get('/{exercise}', [ContentController::class, 'showExercise']);
            Route::post('/{exercise}/complete', [ContentController::class, 'completeExercise']);
        });

        Route::prefix('articles')->group(function () {
            Route::get('/', [ContentController::class, 'articles']);
            Route::get('/favorites', [ArticleFavoriteController::class, 'index']);
            Route::get('/{article}/comments', [ArticleCommentController::class, 'index']);
            Route::post('/{article}/comments', [ArticleCommentController::class, 'store'])->middleware('throttle:article-comments');
            Route::delete('/{article}/comments/{comment}', [ArticleCommentController::class, 'destroy']);
            Route::put('/{article}/favorite', [ArticleFavoriteController::class, 'store']);
            Route::delete('/{article}/favorite', [ArticleFavoriteController::class, 'destroy']);
            Route::get('/{article}', [ContentController::class, 'showArticle'])->name('articles.show');
            Route::post('/{article}/complete', [ContentController::class, 'completeArticle']);
        });

        Route::prefix('audio-tracks')->group(function () {
            Route::get('/', [AudioTrackController::class, 'index']);
            Route::get('/{audioTrack:slug}', [AudioTrackController::class, 'show']);
            Route::get('/{audioTrack:slug}/stream', [AudioTrackController::class, 'stream'])
                ->name('audio-tracks.stream');
            Route::post('/{audioTrack:slug}/complete', [AudioTrackController::class, 'complete']);
        });

        Route::prefix('article-comments')->group(function () {
            Route::post('/{comment}/like', [ArticleCommentController::class, 'like'])->middleware('throttle:comment-likes');
            Route::delete('/{comment}/like', [ArticleCommentController::class, 'unlike'])->middleware('throttle:comment-likes');
            Route::post('/{comment}/report', [ArticleCommentController::class, 'report'])->middleware('throttle:comment-reports');
        });

        Route::prefix('journals')->group(function () {
            Route::get('/', [JournalController::class, 'index']);
            Route::post('/', [JournalController::class, 'store'])->middleware('throttle:journals');
            Route::get('/{journalEntry}', [JournalController::class, 'show']);
            Route::delete('/{journalEntry}', [JournalController::class, 'destroy']);
        });

        Route::post('/feedback', [FeedbackController::class, 'store'])
            ->middleware('throttle:feedback');

        Route::get('/progress/insights', [ProgressInsightController::class, 'show']);

        Route::prefix('sos')->group(function () {

            Route::get('/', [SosController::class, 'index']);
            Route::post('/contacts', [SosController::class, 'storeContact']);
            Route::put('/contacts/{contact}', [SosController::class, 'updateContact']);
            Route::delete('/contacts/{contact}', [SosController::class, 'destroyContact']);
        });

        Route::prefix('admin')->middleware('admin')->group(function () {
            Route::get('/dashboard', [AdminDashboardController::class, 'show']);

            Route::get('/users', [AdminUserController::class, 'index']);
            Route::get('/users/{user}', [AdminUserController::class, 'show']);
            Route::patch('/users/{user}/block', [AdminUserController::class, 'block']);
            Route::patch('/users/{user}/unblock', [AdminUserController::class, 'unblock']);

            Route::get('/articles', [AdminArticleController::class, 'index']);
            Route::post('/articles', [AdminArticleController::class, 'store']);
            Route::get('/articles/{article}', [AdminArticleController::class, 'show']);
            Route::post('/articles/{article}', [AdminArticleController::class, 'update']);
            Route::delete('/articles/{article}', [AdminArticleController::class, 'destroy']);
            Route::patch('/articles/{article}/publish', [AdminArticleController::class, 'publish']);
            Route::patch('/articles/{article}/unpublish', [AdminArticleController::class, 'unpublish']);

            Route::get('/exercises', [AdminExerciseController::class, 'index']);
            Route::post('/exercises', [AdminExerciseController::class, 'store']);
            Route::get('/exercises/{exercise}', [AdminExerciseController::class, 'show']);
            Route::post('/exercises/{exercise}', [AdminExerciseController::class, 'update']);
            Route::delete('/exercises/{exercise}', [AdminExerciseController::class, 'destroy']);
            Route::patch('/exercises/{exercise}/publish', [AdminExerciseController::class, 'publish']);
            Route::patch('/exercises/{exercise}/unpublish', [AdminExerciseController::class, 'unpublish']);

            Route::get('/audio-tracks', [AdminAudioTrackController::class, 'index']);
            Route::post('/audio-tracks', [AdminAudioTrackController::class, 'store']);
            Route::get('/audio-tracks/{audioTrack}', [AdminAudioTrackController::class, 'show']);
            Route::get('/audio-tracks/{audioTrack}/preview', [AdminAudioTrackController::class, 'preview']);
            Route::post('/audio-tracks/{audioTrack}', [AdminAudioTrackController::class, 'update']);
            Route::delete('/audio-tracks/{audioTrack}', [AdminAudioTrackController::class, 'destroy']);
            Route::patch('/audio-tracks/{audioTrack}/restore', [AdminAudioTrackController::class, 'restore']);
            Route::patch('/audio-tracks/{audioTrack}/publish', [AdminAudioTrackController::class, 'publish']);
            Route::patch('/audio-tracks/{audioTrack}/unpublish', [AdminAudioTrackController::class, 'unpublish']);

            Route::get('/sos/counselors', [AdminSosController::class, 'counselors']);
            Route::post('/sos/counselors', [AdminSosController::class, 'storeCounselor']);
            Route::post('/sos/counselors/{counselor}', [AdminSosController::class, 'updateCounselor']);
            Route::delete('/sos/counselors/{counselor}', [AdminSosController::class, 'destroyCounselor']);
            Route::get('/sos/hotlines', [AdminSosController::class, 'hotlines']);
            Route::post('/sos/hotlines', [AdminSosController::class, 'storeHotline']);
            Route::patch('/sos/hotlines/{hotline}', [AdminSosController::class, 'updateHotline']);
            Route::delete('/sos/hotlines/{hotline}', [AdminSosController::class, 'destroyHotline']);

            Route::get('/comment-reports', [AdminCommentModerationController::class, 'index']);
            Route::patch('/comments/{comment}/hide', [AdminCommentModerationController::class, 'hide']);
            Route::patch('/comments/{comment}/restore', [AdminCommentModerationController::class, 'restore']);
            Route::patch('/comment-reports/{report}', [AdminCommentModerationController::class, 'resolveReport']);

            Route::get('/feedback', [AdminFeedbackController::class, 'index']);
            Route::get('/feedback/{feedback}', [AdminFeedbackController::class, 'show']);
            Route::patch('/feedback/{feedback}/review', [AdminFeedbackController::class, 'review']);
        });
    });

});

require __DIR__ . '/nafas_videos.php';
