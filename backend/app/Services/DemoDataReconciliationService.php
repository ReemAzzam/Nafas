<?php

namespace App\Services;

use App\Models\ActivityCompletion;
use App\Models\Article;
use App\Models\ArticleComment;
use App\Models\ArticleCommentReport;
use App\Models\AudioTrack;
use App\Models\Exercise;
use App\Models\Feedback;
use App\Models\Goal;
use App\Models\MoodLog;
use App\Models\PlanActivity;
use App\Models\PlanDay;
use App\Models\User;
use App\Models\WellnessPlan;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class DemoDataReconciliationService
{
    private const REFERENCE_DATE = '2026-10-17';

    private const TIMEZONE = 'Asia/Damascus';

    private const LEGACY_PLAN_TITLE = 'خطتك الأسبوعية المخصصة';

    private const CURRENT_PLAN_TITLES = ['خطة عرض حالية', 'خطة عرض سابقة'];

    private array $actions = [];

    private array $ambiguous = [];

    private array $verifiedUsers = [];

    private array $counts = [];

    public function reconcile(bool $apply = false): array
    {
        if (! $this->buildPlan()) {
            throw new \RuntimeException('Seed the published content catalog before reconciling demo data.');
        }

        if ($apply) {
            DB::transaction(function (): void {
                foreach ($this->actions as $action) {
                    /** @var Model $model */
                    $model = $action['model'];
                    $model->forceFill($action['attributes'])->save();
                }
            });
        }

        return [
            'counts' => $this->counts,
            'ambiguous' => $this->ambiguous,
            'deletions' => 0,
        ];
    }

    private function buildPlan(): bool
    {
        $this->actions = [];
        $this->ambiguous = [];
        $this->counts = [];
        $users = $this->verifyDemoUsers();

        if ($users->isEmpty()) {
            return true;
        }

        $admin = User::query()
            ->where('role', 'admin')
            ->where('is_blocked', false)
            ->orderBy('id')
            ->first();

        if (! $admin) {
            $this->ambiguous[] = [
                'record' => 'feedback/reports',
                'id' => 'n/a',
                'reason' => 'No active admin exists to validate reviewed records.',
            ];
        }

        $articles = Article::query()->where('is_published', true)->with('goals')->orderBy('id')->get();
        $exercises = Exercise::query()->where('is_published', true)->with('goals')->orderBy('id')->get();
        $audioTracks = AudioTrack::query()->where('is_published', true)->orderBy('id')->get();
        if ($articles->isEmpty() || $exercises->isEmpty()) {
            return false;
        }

        $this->reconcileMoodLogs($users, $articles, $exercises);
        $matchedComments = $this->reconcileComments($users, $articles);
        $this->reconcileReports($users, $matchedComments, $admin);
        $this->reconcilePlans($users, $articles, $exercises, $audioTracks);
        $this->reconcileJournalEntries($users);
        $this->reconcileFeedback($users, $admin);

        return true;
    }

    private function verifyDemoUsers()
    {
        $names = [
            'أمل زين', 'حسن قاسم', 'رنا عادل', 'سالم ياسين', 'لينا محمود',
            'فادي نادر', 'مريم هاشم', 'عمر كنعان', 'سارة نبيل', 'إياد شاهر',
            'ندى رزق', 'ياسر مارد', 'مها جلال', 'تامر رامي', 'جمانة علي',
            'باسل عماد', 'يزن حسان', 'ريما جهاد', 'أمير شربل', 'سجى فؤاد',
            'رائد طارق', 'زهراء مروان', 'فيصل حيدر', 'ديمة ساهر', 'مجد لؤي',
            'هالة أنس', 'رامي قيس', 'ندى طارق', 'مروان كرم', 'سجى مراد',
            'وائل يحيى', 'رغد ليث', 'عبد الرحمن مهند', 'غدير فادي', 'ملاذ يزن',
            'أسامة لؤي', 'إسراء محمد', 'أركان نواف', 'يارا جود', 'كريم جاسم',
        ];
        $users = collect();

        foreach (range(0, 39) as $index) {
            $email = sprintf('demo.%02d@demo.nafas.test', $index + 1);
            $user = User::withTrashed()->where('email', $email)->first();
            $legacyCreatedAt = CarbonImmutable::parse('2026-08-15 08:30:00', 'UTC')
                ->addDays($index)
                ->addMinutes($index % 2);
            $legacyPhone = '+9639'.sprintf('%08d', 40000000 + $index);

            if (! $user
                || $user->role !== 'user'
                || $user->name !== $names[$index]
                || $user->phone !== $legacyPhone
                || $user->created_at?->utc()->format('Y-m-d H:i:s') !== $legacyCreatedAt->format('Y-m-d H:i:s')) {
                $this->ambiguous[] = [
                    'record' => 'user',
                    'id' => $user?->id ?? $email,
                    'reason' => 'Email matched the allowlist, but legacy name, phone, role, or creation fingerprint did not.',
                ];

                continue;
            }

            $users->put($index, $user);
            $this->verifiedUsers[$user->id] = $index;
        }

        return $users;
    }

    private function reconcileMoodLogs($users, $articles, $exercises): void
    {
        $articleIds = $articles->pluck('id')->all();
        $exerciseIds = $exercises->pluck('id')->all();

        foreach ($users as $index => $user) {
            if ($index === 39 || $user->trashed()) {
                continue;
            }

            $logs = MoodLog::query()->where('user_id', $user->id)->get();
            $byLocalDate = $logs->groupBy(
                fn (MoodLog $log) => $log->created_at->timezone(self::TIMEZONE)->toDateString()
            );

            foreach (range(29, 0) as $daysAgo) {
                if (! $this->expectsMoodLog($index, $daysAgo)) {
                    continue;
                }

                $date = $this->atReferenceOffset($daysAgo, 9);
                $sameDay = $byLocalDate->get($date->toDateString(), collect());

                if ($sameDay->count() !== 1) {
                    if ($sameDay->count() > 1) {
                        $this->markAmbiguous('mood_log', $sameDay->pluck('id')->all(), $user, 'More than one row exists for the demo user and local date.');
                    }

                    continue;
                }

                /** @var MoodLog $log */
                $log = $sameDay->first();
                if (! $this->hasMoodFingerprint($log, $articleIds, $exerciseIds)) {
                    $this->markAmbiguous('mood_log', [$log->id], $user, 'The same-day record does not match the legacy demo mood shape.');

                    continue;
                }

                $type = ($index + $daysAgo) % 2 === 0 ? 'article' : 'exercise';
                $attributes = [
                    'mood_score' => 1 + (($index + $daysAgo) % 5),
                    'suggestion_type' => $type,
                    'suggested_article_id' => $type === 'article'
                        ? $articleIds[($index + $daysAgo) % count($articleIds)]
                        : null,
                    'suggested_exercise_id' => $type === 'exercise'
                        ? $exerciseIds[($index + $daysAgo) % count($exerciseIds)]
                        : null,
                    'dismissed_suggestions' => [],
                    'suggestion_completed_at' => ($index + $daysAgo) % 3 === 0
                        ? $this->atReferenceOffset($daysAgo, 10)->utc()
                        : null,
                    'created_at' => $date->utc(),
                    'updated_at' => $date->utc(),
                ];

                $this->scheduleUpdate($log, $attributes);
            }
        }
    }

    private function expectsMoodLog(int $index, int $daysAgo): bool
    {
        $daysSinceSignup = 16 - $daysAgo;
        $missedRetentionDay = $daysSinceSignup === 3 && $index >= 24;
        $missedRecentActivity = $daysAgo <= 6 && $index >= 32 && ($index + $daysAgo) % 3 === 0;
        $inFirstWeek = $daysSinceSignup >= 0 && $daysSinceSignup <= 6;
        $sparseHistoryDay = ! $inFirstWeek
            && $daysAgo > 6
            && $daysAgo < 29
            && ($index + $daysAgo) % 8 === 0;

        return ! ($missedRetentionDay || $missedRecentActivity || $sparseHistoryDay);
    }

    private function hasMoodFingerprint(MoodLog $log, array $articleIds, array $exerciseIds): bool
    {
        if ($log->getRawOriginal('note') !== null || $log->mood_score < 1 || $log->mood_score > 5) {
            return false;
        }

        return match ($log->suggestion_type) {
            'article' => in_array($log->suggested_article_id, $articleIds, true)
                && $log->suggested_exercise_id === null,
            'exercise' => in_array($log->suggested_exercise_id, $exerciseIds, true)
                && $log->suggested_article_id === null,
            default => false,
        };
    }

    private function reconcileComments($users, $articles): array
    {
        $legacyBodies = [
            'هذا المقال لطيف جدًا؛ خاصة الفكرة عن البدء بخطوة صغيرة. — ',
            'أحببت التوازن بين الهدوء والواقع، واللغة واضحة ومريحة. — ',
            'المعلومة سهلة التطبيق في اليومي، وخصوصًا الجزء الخاص بالمقارنة مع النفس. — ',
        ];
        $currentBodies = [
            'هذا المقال لطيف، والفكرة عن البدء بخطوة صغيرة سهلة التطبيق.',
            'أحببت هدوء الأسلوب؛ شعرت أن النص يترك مساحة للتفكير دون ضغط.',
            'المعلومة واضحة وقريبة من الحياة اليومية، وسأحاول تطبيقها هذا الأسبوع.',
        ];
        $matched = [];

        foreach ($articles as $article) {
            foreach (range(0, 2) as $index) {
                $user = $users->get($index);
                if (! $user) {
                    continue;
                }

                $legacyBody = $legacyBodies[$index].$article->title;
                $currentBody = $currentBodies[$index].' — '.$article->title;
                $comments = ArticleComment::query()
                    ->where('article_id', $article->id)
                    ->where('user_id', $user->id)
                    ->whereIn('body', [$legacyBody, $currentBody])
                    ->get();

                if ($comments->count() !== 1) {
                    if ($comments->count() > 1) {
                        $this->markAmbiguous('article_comment', $comments->pluck('id')->all(), $user, 'Multiple legacy/current fingerprint matches exist for the same author and article.');
                    }

                    continue;
                }

                $comment = $comments->first();
                $hidden = $index === 1;
                $this->scheduleUpdate($comment, [
                    'body' => $currentBody,
                    'status' => $hidden ? ArticleComment::STATUS_HIDDEN : ArticleComment::STATUS_VISIBLE,
                    'moderation_status' => $hidden ? 'rejected' : 'approved',
                    'moderation_score' => $hidden ? 0.97 : 0.02,
                    'moderation_labels' => $hidden ? ['demo_review'] : [],
                    'moderated_at' => $this->atReferenceOffset(1, 10)->utc(),
                    'updated_at' => $this->atReferenceOffset(1, 10)->utc(),
                ]);
                $matched[$comment->id] = ['comment' => $comment, 'author_index' => $index, 'article' => $article];
            }
        }

        return $matched;
    }

    private function reconcileReports($users, array $comments, ?User $admin): void
    {
        if (! $admin) {
            return;
        }

        $reasons = ['أرجو التحقق من وضوح السياق.', 'قد يحتاج هذا التعليق إلى مراجعة.', 'أبلغ عن هذا التعليق التجريبي.'];
        $legacyReason = 'محتوى يحتاج مراجعة';

        foreach ($comments as $matched) {
            /** @var ArticleComment $comment */
            $comment = $matched['comment'];
            $authorIndex = $matched['author_index'];
            $reporter = $users->get($authorIndex + 3);
            if (! $reporter) {
                continue;
            }

            $reports = ArticleCommentReport::query()
                ->where('article_comment_id', $comment->id)
                ->get();
            $target = $reports->firstWhere('user_id', $reporter->id);

            if (! $target) {
                $legacyCandidates = $reports->filter(
                    fn (ArticleCommentReport $report) => $report->reason === $legacyReason
                        && isset($this->verifiedUsers[$report->user_id])
                        && $this->hasLegacySeedTimestamp($report->created_at)
                );

                if ($legacyCandidates->count() === 1) {
                    $target = $legacyCandidates->first();
                } elseif ($legacyCandidates->count() > 1) {
                    $this->markAmbiguous('article_comment_report', $legacyCandidates->pluck('id')->all(), $reporter, 'Multiple legacy reports could map to this report slot.');

                    continue;
                }
            } elseif ($target->reason !== $legacyReason && $target->reason !== $reasons[$authorIndex]) {
                $this->markAmbiguous('article_comment_report', [$target->id], $reporter, 'Existing report has no recognized legacy/current reason fingerprint.');

                continue;
            }

            $closed = $authorIndex !== 0;
            $attributes = [
                'user_id' => $reporter->id,
                'reason' => $reasons[$authorIndex],
                'status' => ['pending', 'resolved', 'dismissed'][$authorIndex],
                'resolved_by' => $closed ? $admin->id : null,
                'admin_note' => $closed ? 'تمت مراجعة البلاغ التجريبي.' : null,
                'resolved_at' => $closed ? $this->atReferenceOffset(0, 11)->utc() : null,
                'updated_at' => $this->atReferenceOffset(0, 11)->utc(),
            ];

            if ($target) {
                $this->scheduleUpdate($target, $attributes);
            } else {
                $report = new ArticleCommentReport;
                $report->article_comment_id = $comment->id;
                $report->created_at = $this->atReferenceOffset(2, 11)->utc();
                $this->scheduleCreate($report, $attributes);
            }
        }
    }

    private function reconcilePlans($users, $articles, $exercises, $audioTracks): void
    {
        $goalTitles = ['نوم أفضل', 'قلق أقل', 'تركيز أكثر', 'مزاج أفضل', 'ضغط الدراسة', 'الأكل العاطفي'];
        $goals = Goal::query()->pluck('id', 'title');
        $supportGroups = [
            ['exercises', 'writing'],
            ['exercises', 'listening'],
            ['exercises', 'reading', 'listening'],
            ['exercises', 'reading', 'practical'],
        ];

        foreach ($users as $index => $user) {
            $plans = WellnessPlan::query()->where('user_id', $user->id)->get();
            if ($plans->count() !== 1) {
                if ($plans->count() > 1) {
                    $this->markAmbiguous('wellness_plan', $plans->pluck('id')->all(), $user, 'More than one plan exists for the allowlisted account.');
                }

                continue;
            }

            /** @var WellnessPlan $plan */
            $plan = $plans->first();
            if (! in_array($plan->title, array_merge([self::LEGACY_PLAN_TITLE], self::CURRENT_PLAN_TITLES), true)) {
                $this->markAmbiguous('wellness_plan', [$plan->id], $user, 'Plan title is not a recognized legacy/current demo marker.');

                continue;
            }

            $isCurrent = $index < 24;
            $startDaysAgo = $isCurrent ? 6 : 15;
            $planStart = $this->atReferenceOffset($startDaysAgo)->startOfDay();
            $completedThrough = $isCurrent ? [1, 2, 3][$index % 3] : [2, 4, 6][$index % 3];
            $title = $isCurrent ? 'خطة عرض حالية' : 'خطة عرض سابقة';

            $this->scheduleUpdate($plan, [
                'title' => $title,
                'description' => 'خطة تجريبية ثابتة من سبعة أيام لعرض رحلة استخدام نفس.',
                'start_date' => $planStart->toDateString(),
                'end_date' => $planStart->addDays(6)->toDateString(),
                'is_active' => $isCurrent,
                'created_at' => $planStart->utc(),
                'updated_at' => $planStart->utc(),
            ]);

            $profileGoalIds = collect(range(0, 2 + ($index % 2)))
                ->map(fn (int $offset) => $goals[$goalTitles[($index + $offset) % count($goalTitles)]] ?? null)
                ->filter()
                ->unique()
                ->values();
            $profileSupportSlugs = $supportGroups[$index % count($supportGroups)];
            $matchingArticles = $this->forGoals($articles, $profileGoalIds);
            $matchingExercises = $this->forGoals($exercises, $profileGoalIds);
            $userAudio = in_array('listening', $profileSupportSlugs, true) ? $audioTracks : collect();

            foreach (range(1, 7) as $dayNumber) {
                $day = PlanDay::query()
                    ->where('wellness_plan_id', $plan->id)
                    ->where('day_number', $dayNumber)
                    ->first();

                if (! $day) {
                    $this->ambiguous[] = [
                        'record' => 'plan_day',
                        'id' => $plan->id.':'.$dayNumber,
                        'reason' => 'The legacy plan day is missing; it will not be created by reconciliation.',
                    ];

                    continue;
                }

                $dayDate = $planStart->addDays($dayNumber - 1);
                $plannedCompleted = $dayNumber <= $completedThrough;
                $activityRows = PlanActivity::query()->where('plan_day_id', $day->id)->get();
                if ($activityRows->count() !== 1) {
                    if ($activityRows->count() > 1) {
                        $this->markAmbiguous('plan_activity', $activityRows->pluck('id')->all(), $user, 'A plan day has multiple activities; none will be removed or changed.');
                    }

                    $this->scheduleUpdate($day, [
                        'date' => $dayDate->toDateString(),
                        'is_completed' => $plannedCompleted,
                        'created_at' => $planStart->utc(),
                        'updated_at' => $planStart->utc(),
                    ]);

                    continue;
                }

                [$activityType, $activityId] = $this->desiredPlanActivity(
                    $index,
                    $dayNumber,
                    in_array('reading', $profileSupportSlugs, true),
                    $matchingArticles,
                    $matchingExercises,
                    $userAudio
                );
                /** @var PlanActivity $activity */
                $activity = $activityRows->first();
                $completions = ActivityCompletion::query()
                    ->where('plan_activity_id', $activity->id)
                    ->get();

                if ($completions->count() > 1) {
                    $this->markAmbiguous('activity_completion', $completions->pluck('id')->all(), $user, 'Multiple completion rows reference one plan activity.');

                    continue;
                }

                $completion = $completions->first();
                if ($completion && $completion->user_id !== $user->id) {
                    $this->markAmbiguous('activity_completion', [$completion->id], $user, 'Completion owner does not match the plan owner.');

                    continue;
                }

                $completed = $plannedCompleted || $completion !== null;
                $this->scheduleUpdate($day, [
                    'date' => $dayDate->toDateString(),
                    'is_completed' => $completed,
                    'created_at' => $planStart->utc(),
                    'updated_at' => $planStart->utc(),
                ]);
                $this->scheduleUpdate($activity, [
                    'activity_type' => $activityType,
                    'activity_id' => $activityId,
                    'is_completed' => $completed,
                    'created_at' => $planStart->utc(),
                    'updated_at' => $planStart->utc(),
                ]);

                if (! $completed) {
                    continue;
                }

                if (! $completion) {
                    continue;
                }
                $completedAt = $dayDate->setTime(18, 0)->utc();
                $this->scheduleUpdate($completion, [
                    'activity_type' => $activityType,
                    'activity_id' => $activityId,
                    'source' => 'plan',
                    'mood_log_id' => null,
                    'created_at' => $completedAt,
                    'updated_at' => $completedAt,
                ]);
            }
        }
    }

    private function forGoals($content, $goalIds)
    {
        $matched = $content->filter(
            fn (Article|Exercise $item) => $item->goals->pluck('id')->intersect($goalIds)->isNotEmpty()
        )->values();

        return $matched->isNotEmpty() ? $matched : $content;
    }

    private function desiredPlanActivity(
        int $index,
        int $dayNumber,
        bool $readsArticles,
        $articles,
        $exercises,
        $audioTracks
    ): array {
        if ($dayNumber === 3 && $audioTracks->isNotEmpty()) {
            $audio = $audioTracks[$index % $audioTracks->count()];

            return [AudioTrack::class, $audio->id];
        }

        if ($dayNumber === 5 && $readsArticles) {
            $article = $articles[($index + $dayNumber) % $articles->count()];

            return [Article::class, $article->id];
        }

        $exercise = $exercises[($index + $dayNumber) % $exercises->count()];

        return [Exercise::class, $exercise->id];
    }

    private function reconcileJournalEntries($users): void
    {
        foreach ($users as $user) {
            $index = $this->verifiedUsers[$user->id];
            foreach ($user->journalEntries()->get() as $entry) {
                $legacyFingerprint = $entry->prompt === 'ماذا لاحظت اليوم؟'
                    && $entry->body === 'استثمرت في خطوة واحدة صغيرة، ودفعت نفسي إلى الاستمرار دون انتقاد.'
                    && $this->hasLegacySeedTimestamp($entry->created_at);
                $currentFingerprint = $index < 39
                    && $index % 4 === 0
                    && $entry->prompt === 'ما الخطوة الصغيرة التي منحتك بعض الهدوء اليوم؟'
                    && $entry->body === 'لاحظت أن تقسيم المهمة إلى جزء قصير جعل البدء أسهل، وسأكرر ذلك عندما أحتاج.'
                    && $entry->created_at?->utc()->format('Y-m-d H:i:s') === $this->atReferenceOffset($index % 7, 17)->utc()->format('Y-m-d H:i:s');

                if (! $legacyFingerprint && ! $currentFingerprint) {
                    $this->markAmbiguous('journal_entry', [$entry->id], $user, 'Encrypted journal content, after model decryption, does not match the legacy demo fingerprint.');
                }
            }
        }
    }

    private function reconcileFeedback($users, ?User $admin): void
    {
        foreach ($users as $index => $user) {
            foreach ($user->feedbacks()->get() as $feedback) {
                $isCurrentTarget = $index < 39 && $index % 3 !== 2;
                if ($isCurrentTarget && $this->hasCurrentFeedbackFingerprint($feedback, $index)) {
                    $this->repairReviewState($feedback, $admin);

                    continue;
                }

                $legacyFingerprint = $feedback->message === 'التطبيق سهل ومفيد.'
                    && in_array($feedback->rating, [4, 5], true)
                    && $this->hasLegacySeedTimestamp($feedback->created_at);

                if (! $legacyFingerprint) {
                    $this->markAmbiguous('feedback', [$feedback->id], $user, 'Encrypted feedback content, after model decryption, does not match the legacy demo fingerprint.');

                    continue;
                }

                if ($isCurrentTarget) {
                    $reviewed = $index % 2 === 0;
                    if ($reviewed && ! $admin) {
                        $this->markAmbiguous('feedback', [$feedback->id], $user, 'A reviewed demo feedback row requires an active admin and was left unchanged.');

                        continue;
                    }

                    $createdAt = $this->atReferenceOffset(10, 13, $index)->utc();
                    $this->scheduleUpdate($feedback, [
                        'rating' => [2, 3, 4, 5, 4][$index % 5],
                        'message' => $index % 4 === 0
                            ? null
                            : [
                                'التطبيق يساعدني على التوقف قليلًا وملاحظة ما أشعر به.',
                                'أحببت بساطة التمارين، وأتمنى إضافة تذكير اختياري.',
                                'المحتوى واضح ومريح، واستخدمت تمرين التنفس أكثر من مرة.',
                            ][$index % 3],
                        'status' => $reviewed ? 'reviewed' : 'pending',
                        'reviewed_by' => $reviewed ? $admin?->id : null,
                        'admin_note' => $reviewed ? 'تمت مراجعة الملاحظة التجريبية.' : null,
                        'reviewed_at' => $reviewed ? $this->atReferenceOffset(0, 10)->utc() : null,
                        'created_at' => $createdAt,
                        'updated_at' => $createdAt,
                    ]);
                } else {
                    $this->repairReviewState($feedback, $admin);
                }
            }
        }
    }

    private function repairReviewState(Feedback $feedback, ?User $admin): void
    {
        if ($feedback->status === 'reviewed') {
            if (! $admin) {
                return;
            }

            $this->scheduleUpdate($feedback, [
                'reviewed_by' => $admin->id,
                'admin_note' => $feedback->admin_note ?: 'تمت مراجعة الملاحظة التجريبية.',
                'reviewed_at' => $feedback->reviewed_at ?: $this->atReferenceOffset(0, 10)->utc(),
            ]);
        } elseif ($feedback->status === 'pending') {
            $this->scheduleUpdate($feedback, [
                'reviewed_by' => null,
                'admin_note' => null,
                'reviewed_at' => null,
            ]);
        } else {
            $this->markAmbiguous('feedback', [$feedback->id], null, 'Legacy feedback has an unknown status and was left unchanged.');
        }
    }

    private function hasCurrentFeedbackFingerprint(Feedback $feedback, int $index): bool
    {
        $createdAt = $this->atReferenceOffset(10, 13, $index)->utc();
        $expectedMessage = $index % 4 === 0
            ? null
            : [
                'التطبيق يساعدني على التوقف قليلًا وملاحظة ما أشعر به.',
                'أحببت بساطة التمارين، وأتمنى إضافة تذكير اختياري.',
                'المحتوى واضح ومريح، واستخدمت تمرين التنفس أكثر من مرة.',
            ][$index % 3];

        return $feedback->created_at?->utc()->format('Y-m-d H:i:s') === $createdAt->format('Y-m-d H:i:s')
            && $feedback->rating === [2, 3, 4, 5, 4][$index % 5]
            && $feedback->message === $expectedMessage;
    }

    private function hasLegacySeedTimestamp(?CarbonInterface $timestamp): bool
    {
        if (! $timestamp) {
            return false;
        }

        $utc = $timestamp->utc();

        return $utc->toDateString() === '2026-10-17'
            && $utc->format('H:i') >= '07:45'
            && $utc->format('H:i') <= '08:00';
    }

    private function markAmbiguous(string $type, array $ids, ?User $user, string $reason): void
    {
        foreach ($ids as $id) {
            $this->ambiguous[] = [
                'record' => $type,
                'id' => $id,
                'email' => $user?->email,
                'reason' => $reason,
            ];
        }
    }

    private function scheduleUpdate(Model $model, array $attributes): void
    {
        $changed = [];
        foreach ($attributes as $key => $value) {
            if ($this->normalize($model->getAttribute($key)) !== $this->normalize($value)) {
                $changed[$key] = $value;
            }
        }

        if ($changed === []) {
            return;
        }

        $this->actions[] = ['model' => $model, 'attributes' => $changed, 'operation' => 'updated'];
        $this->counts[$model->getTable()]['updated'] = ($this->counts[$model->getTable()]['updated'] ?? 0) + 1;
    }

    private function scheduleCreate(Model $model, array $attributes): void
    {
        $this->actions[] = ['model' => $model, 'attributes' => $attributes, 'operation' => 'added'];
        $this->counts[$model->getTable()]['added'] = ($this->counts[$model->getTable()]['added'] ?? 0) + 1;
    }

    private function normalize(mixed $value): mixed
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_array($value)) {
            return json_encode($value);
        }

        return $value;
    }

    private function atReferenceOffset(int $daysAgo, int $hour = 0, int $minute = 0): CarbonImmutable
    {
        return CarbonImmutable::parse(self::REFERENCE_DATE, self::TIMEZONE)
            ->subDays($daysAgo)
            ->setTime($hour, $minute);
    }
}
