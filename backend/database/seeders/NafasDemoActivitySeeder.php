<?php

namespace Database\Seeders;

use App\Models\ActivityCompletion;
use App\Models\Article;
use App\Models\ArticleComment;
use App\Models\ArticleCommentReport;
use App\Models\AudioTrack;
use App\Models\Exercise;
use App\Models\Factor;
use App\Models\Feedback;
use App\Models\Goal;
use App\Models\JournalEntry;
use App\Models\MoodLog;
use App\Models\PlanActivity;
use App\Models\PlanDay;
use App\Models\SosContact;
use App\Models\SosDailyStat;
use App\Models\SupportMethod;
use App\Models\User;
use App\Models\WellnessPlan;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class NafasDemoActivitySeeder extends Seeder
{
    private const TIMEZONE = 'Asia/Damascus';

    private const LEGACY_PLAN_TITLE = 'خطتك الأسبوعية المخصصة';

    private CarbonImmutable $referenceDate;

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('NafasDemoActivitySeeder is disabled in production.');
        }

        $this->referenceDate = $this->resolveReferenceDate();
        $articles = Article::query()->where('is_published', true)->with('goals')->orderBy('id')->get();
        $exercises = Exercise::query()->where('is_published', true)->with('goals')->orderBy('id')->get();
        $audioTracks = AudioTrack::query()->where('is_published', true)->orderBy('id')->get();
        $goals = Goal::query()->pluck('id', 'title');
        $supportMethods = SupportMethod::query()->pluck('id', 'slug');

        if ($articles->isEmpty() || $exercises->isEmpty()) {
            throw new RuntimeException('Seed the Nafas content catalog before demo activity.');
        }

        $admin = User::query()
            ->where('role', 'admin')
            ->where('is_blocked', false)
            ->orderBy('id')
            ->first();

        if (! $admin) {
            throw new RuntimeException('An active admin account is required for reviewed demo records.');
        }

        $users = $this->seedDemoUsers();

        foreach ($users as $index => $user) {
            if ($user->trashed()) {
                continue;
            }

            $this->seedUserProfile($user, $index, $goals, $supportMethods);
            $this->seedMoodLogHistory($user, $index, $articles, $exercises);
            $this->seedFavorites($user, $articles);
            $this->seedJournal($user, $index);
            $this->seedFeedback($user, $index, $admin);
            $this->seedSosContact($user, $index);
        }

        $this->seedCommentsAndReports($users, $articles, $admin);
        $this->seedPlans($users, $articles, $exercises, $audioTracks);
        $this->seedSosDailyStats();
    }

    /**
     * @return array<int, User>
     */
    private function seedDemoUsers(): array
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

        $users = [];

        foreach ($names as $index => $name) {
            $email = sprintf('demo.%02d@demo.nafas.test', $index + 1);
            $user = User::withTrashed()->firstOrNew(['email' => $email]);
            $isNew = ! $user->exists;
            $createdAt = $this->atReferenceOffset(16, 8, $index)->utc();

            $attributes = [
                'name' => $name,
                'display_name' => ['أمل', 'حسن', 'رنا', 'سالم', 'لونا'][$index % 5],
                'country_code' => 'SY',
                'phone' => '+9639'.sprintf('%08d', 40000000 + $index),
                'date_of_birth' => CarbonImmutable::parse('1995-01-01')->addYears($index % 12)->toDateString(),
                'status' => ['student', 'worker', 'other'][$index % 3],
                'onboarding_status' => $index % 4 === 0 ? 'plan_ready' : 'goals_set',
                'role' => 'user',
                'is_anonymous' => false,
                'is_blocked' => in_array($index, [20, 35, 39], true),
                'email_verified_at' => $createdAt,
            ];

            if ($isNew) {
                $attributes['password'] = Hash::make('DemoPass123!');
            }

            $user->forceFill($attributes);
            $user->created_at = $createdAt;
            $user->updated_at = $createdAt;
            $user->save();

            if ($index === 39) {
                if (! $user->trashed()) {
                    $user->delete();
                }
            } elseif ($user->trashed()) {
                $user->restore();
            }

            $users[$index] = User::withTrashed()->findOrFail($user->id);
        }

        return $users;
    }

    private function seedUserProfile(User $user, int $index, Collection $goals, Collection $supportMethods): void
    {
        $goalTitles = ['نوم أفضل', 'قلق أقل', 'تركيز أكثر', 'مزاج أفضل', 'ضغط الدراسة', 'الأكل العاطفي'];
        $goalIds = collect(range(0, 2 + ($index % 2)))
            ->map(fn (int $offset) => $goals[$goalTitles[($index + $offset) % count($goalTitles)]] ?? null)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $supportByGroup = [
            ['exercises', 'writing'],
            ['exercises', 'listening'],
            ['exercises', 'reading', 'listening'],
            ['exercises', 'reading', 'practical'],
        ];
        $supportIds = collect($supportByGroup[$index % count($supportByGroup)])
            ->map(fn (string $slug) => $supportMethods[$slug] ?? null)
            ->filter()
            ->values()
            ->all();

        $user->goals()->sync($goalIds);
        $user->supportMethods()->sync($supportIds);
    }

    private function seedMoodLogHistory(User $user, int $index, Collection $articles, Collection $exercises): void
    {
        $articleIds = $articles->pluck('id')->all();
        $exerciseIds = $exercises->pluck('id')->all();
        $factorIds = Factor::query()
            ->whereIn('slug', ['study_work', 'overthinking', 'relationships', 'family', 'future_anxiety'])
            ->orderBy('id')
            ->pluck('id')
            ->all();

        foreach (range(29, 0) as $daysAgo) {
            $daysSinceSignup = 16 - $daysAgo;
            $missedRetentionDay = $daysSinceSignup === 3 && $index >= 24;
            $missedRecentActivity = $daysAgo <= 6 && $index >= 32 && ($index + $daysAgo) % 3 === 0;
            $inFirstWeek = $daysSinceSignup >= 0 && $daysSinceSignup <= 6;
            $sparseHistoryDay = ! $inFirstWeek
                && $daysAgo > 6
                && $daysAgo < 29
                && ($index + $daysAgo) % 8 === 0;

            if ($missedRetentionDay || $missedRecentActivity || $sparseHistoryDay) {
                continue;
            }

            $date = $this->atReferenceOffset($daysAgo, 9);
            $type = ($index + $daysAgo) % 2 === 0 ? 'article' : 'exercise';
            $values = [
                'user_id' => $user->id,
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
            ];

            $logQuery = MoodLog::query()
                ->where('user_id', $user->id)
                ->whereBetween('created_at', [
                    $date->startOfDay()->utc()->toDateTimeString(),
                    $date->endOfDay()->utc()->toDateTimeString(),
                ]);
            $sameDayLogs = $logQuery->get();
            if ($sameDayLogs->count() > 1) {
                continue;
            }

            $log = $sameDayLogs->first();
            if ($log && ! $this->hasReconciliableMoodFingerprint($log, $articleIds, $exerciseIds)) {
                continue;
            }

            if (! $log) {
                $log = new MoodLog;
            }

            $log->fill($values);
            $log->created_at = $date->utc();
            $log->updated_at = $date->utc();
            $log->save();

            if ($factorIds !== [] && ($index + $daysAgo) % 4 === 0) {
                $log->factors()->syncWithoutDetaching([
                    $factorIds[($index + $daysAgo) % count($factorIds)],
                ]);
            }
        }
    }

    private function seedFavorites(User $user, Collection $articles): void
    {
        foreach ($articles->take(4) as $article) {
            DB::table('article_favorites')->insertOrIgnore([
                'article_id' => $article->id,
                'user_id' => $user->id,
                'created_at' => $this->atReferenceOffset(8, 12)->utc()->toDateTimeString(),
                'updated_at' => $this->atReferenceOffset(8, 12)->utc()->toDateTimeString(),
            ]);
        }
    }

    private function hasReconciliableMoodFingerprint(MoodLog $log, array $articleIds, array $exerciseIds): bool
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

    private function seedJournal(User $user, int $index): void
    {
        if ($index % 4 !== 0) {
            return;
        }

        $createdAt = $this->atReferenceOffset($index % 7, 17)->utc();
        $entry = JournalEntry::query()
            ->where('user_id', $user->id)
            ->where('created_at', $createdAt->toDateTimeString())
            ->first();

        if ($entry) {
            return;
        }

        $entry = new JournalEntry;
        $entry->fill([
            'user_id' => $user->id,
            'prompt' => 'ما الخطوة الصغيرة التي منحتك بعض الهدوء اليوم؟',
            'body' => 'لاحظت أن تقسيم المهمة إلى جزء قصير جعل البدء أسهل، وسأكرر ذلك عندما أحتاج.',
        ]);
        $entry->created_at = $createdAt;
        $entry->updated_at = $createdAt;
        $entry->save();
    }

    private function seedFeedback(User $user, int $index, User $admin): void
    {
        if ($index % 3 === 2) {
            return;
        }

        $createdAt = $this->atReferenceOffset(10, 13, $index)->utc();
        $feedback = Feedback::query()
            ->where('user_id', $user->id)
            ->where('created_at', $createdAt->toDateTimeString())
            ->first();

        if ($feedback) {
            return;
        }

        $reviewed = $index % 2 === 0;
        $feedback = new Feedback;
        $feedback->fill([
            'user_id' => $user->id,
            'rating' => [2, 3, 4, 5, 4][$index % 5],
            'message' => $index % 4 === 0
                ? null
                : [
                    'التطبيق يساعدني على التوقف قليلًا وملاحظة ما أشعر به.',
                    'أحببت بساطة التمارين، وأتمنى إضافة تذكير اختياري.',
                    'المحتوى واضح ومريح، واستخدمت تمرين التنفس أكثر من مرة.',
                ][$index % 3],
            'status' => $reviewed ? 'reviewed' : 'pending',
            'reviewed_by' => $reviewed ? $admin->id : null,
            'admin_note' => $reviewed ? 'تمت مراجعة الملاحظة التجريبية.' : null,
            'reviewed_at' => $reviewed ? $this->atReferenceOffset(0, 10)->utc() : null,
        ]);
        $feedback->created_at = $createdAt;
        $feedback->updated_at = $createdAt;
        $feedback->save();
    }

    private function seedSosContact(User $user, int $index): void
    {
        if ($index % 3 !== 0) {
            return;
        }

        if (SosContact::query()->where('user_id', $user->id)->exists()) {
            return;
        }

        $contact = new SosContact;
        $contact->forceFill([
            'user_id' => $user->id,
            'name' => 'جهة اتصال تجريبية '.($index + 1),
            'phone' => '+9639'.sprintf('%08d', 50000000 + $index),
        ]);
        $contact->save();
    }

    /**
     * @param  array<int, User>  $users
     */
    private function seedCommentsAndReports(array $users, Collection $articles, User $admin): void
    {
        $activeUsers = array_values(array_filter($users, fn (User $user) => ! $user->trashed()));

        foreach ($articles as $article) {
            foreach (array_slice($activeUsers, 0, 3) as $index => $user) {
                $body = $this->commentBody($index, $article->title);
                $comment = ArticleComment::query()->firstOrNew([
                    'article_id' => $article->id,
                    'user_id' => $user->id,
                    'body' => $body,
                ]);
                $comment->fill([
                    'status' => $index === 1 ? ArticleComment::STATUS_HIDDEN : ArticleComment::STATUS_VISIBLE,
                    'moderation_status' => $index === 1 ? 'rejected' : 'approved',
                    'moderation_score' => $index === 1 ? 0.97 : 0.02,
                    'moderation_labels' => $index === 1 ? ['demo_review'] : [],
                    'moderated_at' => $this->atReferenceOffset(1, 10)->utc(),
                ]);
                $comment->created_at ??= $this->atReferenceOffset(2, 10)->utc();
                $comment->updated_at = $this->atReferenceOffset(1, 10)->utc();
                $comment->save();

                $reporter = $activeUsers[$index + 3];
                $closed = $index !== 0;
                $report = ArticleCommentReport::query()->firstOrNew([
                    'article_comment_id' => $comment->id,
                    'user_id' => $reporter->id,
                ]);
                if ($report->exists
                    && ! in_array($report->reason, [
                        ['أرجو التحقق من وضوح السياق.', 'قد يحتاج هذا التعليق إلى مراجعة.', 'أبلغ عن هذا التعليق التجريبي.'][$index],
                        'محتوى يحتاج مراجعة',
                    ], true)) {
                    continue;
                }
                $report->fill([
                    'reason' => ['أرجو التحقق من وضوح السياق.', 'قد يحتاج هذا التعليق إلى مراجعة.', 'أبلغ عن هذا التعليق التجريبي.'][$index],
                    'status' => ['pending', 'resolved', 'dismissed'][$index],
                    'resolved_by' => $closed ? $admin->id : null,
                    'admin_note' => $closed ? 'تمت مراجعة البلاغ التجريبي.' : null,
                    'resolved_at' => $closed ? $this->atReferenceOffset(0, 11)->utc() : null,
                ]);
                $report->created_at ??= $this->atReferenceOffset(2, 11)->utc();
                $report->updated_at = $this->atReferenceOffset(0, 11)->utc();
                $report->save();

                foreach (array_slice($activeUsers, 6, 2) as $liker) {
                    DB::table('article_comment_likes')->insertOrIgnore([
                        'article_comment_id' => $comment->id,
                        'user_id' => $liker->id,
                        'created_at' => $this->atReferenceOffset(1, 12)->utc()->toDateTimeString(),
                        'updated_at' => $this->atReferenceOffset(1, 12)->utc()->toDateTimeString(),
                    ]);
                }
            }
        }
    }

    private function commentBody(int $index, string $title): string
    {
        $comments = [
            'هذا المقال لطيف، والفكرة عن البدء بخطوة صغيرة سهلة التطبيق.',
            'أحببت هدوء الأسلوب؛ شعرت أن النص يترك مساحة للتفكير دون ضغط.',
            'المعلومة واضحة وقريبة من الحياة اليومية، وسأحاول تطبيقها هذا الأسبوع.',
            'استفدت من التذكير بأن التقدم لا يحتاج إلى الكمال.',
            'الشرح متوازن ويعطي اقتراحًا عمليًا بدل الاكتفاء بالنصيحة.',
            'قرأت المقال في وقت مناسب، وكانت لغته مريحة ومفهومة.',
        ];

        return $comments[$index % count($comments)].' — '.$title;
    }

    /**
     * @param  array<int, User>  $users
     */
    private function seedPlans(array $users, Collection $articles, Collection $exercises, Collection $audioTracks): void
    {
        $articlePool = $articles;
        $exercisePool = $exercises;

        foreach ($users as $index => $user) {
            if ($user->trashed()) {
                continue;
            }

            $isCurrent = $index < 24;
            $title = $isCurrent ? 'خطة عرض حالية' : 'خطة عرض سابقة';
            $startDaysAgo = $isCurrent ? 6 : 15;
            $planStart = $this->atReferenceOffset($startDaysAgo)->startOfDay();
            $planEnd = $planStart->addDays(6);
            $plan = WellnessPlan::query()
                ->where('user_id', $user->id)
                ->where('title', $title)
                ->first();

            if (! $plan) {
                $plan = WellnessPlan::query()
                    ->where('user_id', $user->id)
                    ->where('title', self::LEGACY_PLAN_TITLE)
                    ->orderByDesc('id')
                    ->first() ?? new WellnessPlan;
            }

            $plan->forceFill([
                'user_id' => $user->id,
                'title' => $title,
                'description' => 'خطة تجريبية ثابتة من سبعة أيام لعرض رحلة استخدام نفس.',
                'start_date' => $planStart->toDateString(),
                'end_date' => $planEnd->toDateString(),
                'is_active' => $isCurrent,
                'created_at' => $planStart->utc(),
                'updated_at' => $planStart->utc(),
            ]);
            $plan->save();

            $supportSlugs = $user->supportMethods()->pluck('support_methods.slug');
            $goalIds = $user->goals()->pluck('goals.id');
            $matchingArticles = $this->forGoals($articlePool, $goalIds);
            $matchingExercises = $this->forGoals($exercisePool, $goalIds);
            $userAudio = $supportSlugs->contains('listening') ? $audioTracks : collect();
            $completedThrough = $isCurrent
                ? [1, 2, 3][$index % 3]
                : [2, 4, 6][$index % 3];

            foreach (range(1, 7) as $dayNumber) {
                $dayDate = $planStart->addDays($dayNumber - 1);
                $day = PlanDay::query()->firstOrNew([
                    'wellness_plan_id' => $plan->id,
                    'day_number' => $dayNumber,
                ]);
                $day->forceFill([
                    'date' => $dayDate->toDateString(),
                    'is_completed' => $dayNumber <= $completedThrough,
                    'created_at' => $planStart->utc(),
                    'updated_at' => $planStart->utc(),
                ]);
                $day->save();

                [$activityType, $activityId] = $this->planActivityForDay(
                    $index,
                    $dayNumber,
                    $supportSlugs->contains('reading'),
                    $matchingArticles,
                    $matchingExercises,
                    $userAudio
                );
                $activity = PlanActivity::query()->firstOrNew([
                    'plan_day_id' => $day->id,
                    'activity_type' => $activityType,
                    'activity_id' => $activityId,
                ]);
                $completed = $dayNumber <= $completedThrough
                    || ($activity->exists && ActivityCompletion::query()
                        ->where('user_id', $user->id)
                        ->where('plan_activity_id', $activity->id)
                        ->exists());
                if ($day->is_completed !== $completed) {
                    $day->update(['is_completed' => $completed]);
                }

                $activity->forceFill([
                    'is_completed' => $completed,
                    'created_at' => $planStart->utc(),
                    'updated_at' => $planStart->utc(),
                ]);
                $activity->save();

                if ($completed) {
                    $completedAt = $dayDate->setTime(18, 0)->utc();
                    $completion = ActivityCompletion::query()->firstOrNew([
                        'user_id' => $user->id,
                        'plan_activity_id' => $activity->id,
                    ]);
                    $completion->fill([
                        'activity_type' => $activityType,
                        'activity_id' => $activityId,
                        'source' => 'plan',
                        'mood_log_id' => null,
                    ]);
                    $completion->created_at ??= $completedAt;
                    $completion->updated_at = $completedAt;
                    $completion->save();
                }
            }
        }
    }

    /**
     * @return Collection<int, Article|Exercise>
     */
    private function forGoals(Collection $content, Collection $goalIds): Collection
    {
        $matched = $content->filter(
            fn (Article|Exercise $item) => $item->goals->pluck('id')->intersect($goalIds)->isNotEmpty()
        )->values();

        return $matched->isNotEmpty() ? $matched : $content;
    }

    /**
     * @return array{class-string, int}
     */
    private function planActivityForDay(
        int $index,
        int $dayNumber,
        bool $readsArticles,
        Collection $articles,
        Collection $exercises,
        Collection $audioTracks
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

    private function seedSosDailyStats(): void
    {
        foreach (range(6, 0) as $daysAgo) {
            $date = $this->atReferenceOffset($daysAgo)->toDateString();
            $minimum = $daysAgo === 0 ? 16 : 12;
            $stat = SosDailyStat::query()->firstOrNew(['date' => $date]);
            $stat->opens_count = max((int) ($stat->opens_count ?? 0), $minimum);
            $stat->created_at ??= $this->atReferenceOffset($daysAgo, 20)->utc();
            $stat->updated_at = $this->atReferenceOffset($daysAgo, 20)->utc();
            $stat->save();
        }
    }

    private function atReferenceOffset(int $daysAgo, int $hour = 0, int $minute = 0): CarbonImmutable
    {
        return $this->referenceDate
            ->subDays($daysAgo)
            ->setTime($hour, $minute);
    }

    private function resolveReferenceDate(): CarbonImmutable
    {
        $demoUserIds = User::withTrashed()
            ->whereIn('email', array_map(
                fn (int $index) => sprintf('demo.%02d@demo.nafas.test', $index + 1),
                range(0, 39)
            ))
            ->pluck('id');

        if ($demoUserIds->isNotEmpty()) {
            $latestMoodDate = MoodLog::query()
                ->whereIn('user_id', $demoUserIds)
                ->orderByDesc('created_at')
                ->value('created_at');

            if ($latestMoodDate) {
                return CarbonImmutable::parse($latestMoodDate)->timezone(self::TIMEZONE)->startOfDay();
            }

            $latestPlanDate = PlanDay::query()
                ->whereHas('wellnessPlan', fn ($query) => $query->whereIn('user_id', $demoUserIds))
                ->orderByDesc('date')
                ->value('date');

            if ($latestPlanDate) {
                return CarbonImmutable::parse($latestPlanDate, self::TIMEZONE)->startOfDay();
            }
        }

        return CarbonImmutable::now(self::TIMEZONE)->startOfDay();
    }
}
