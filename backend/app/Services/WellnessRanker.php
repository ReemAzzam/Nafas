<?php
namespace App\Services;

/** Lightweight explainable scorer; no diagnosis or trained model weights. */
class WellnessRanker
{
    public function summarize(array $scores, array $factors = []): array
    {
        // Compatibility helper for callers/tests using the original contract.
        $logs = [];
        foreach (array_slice($scores, 0, 14) as $i=>$score) {
            $logs[] = ['date'=>(string) $i, 'score'=>$score, 'age_days'=>$i, 'factors'=>$i===0 ? $factors : []];
        }
        return $this->profile($logs);
    }
    public function profile(array $logs, array $methods = [], array $goals = []): array
    {
        $days = []; $factorWeights = []; $sum = 0.0; $weights = 0.0; $plain = 0;
        $lowDays = 0; $scores = [];
        foreach ($logs as $log) {
            $score = (int) ($log['score'] ?? 0); $age = (int) ($log['age_days'] ?? 0);
            if ($score < 1 || $score > 5 || $age < 0 || $age > 30) continue;
            $date = (string) $log['date'];
            if (isset($days[$date]) || count($days) >= 14) continue;
            $days[$date] = true; $scores[] = $score;
            $weight = 1 / (1 + $age * .25);
            $sum += $score * $weight; $weights += $weight; $plain += $score;
            if ($score <= 2 && $age <= 7) $lowDays++;
            if ($age <= 7) foreach (array_unique($log['factors'] ?? []) as $factor) {
                $factorWeights[$factor] = ($factorWeights[$factor] ?? 0) + $weight;
            }
        }
        $current = $scores[0] ?? null;
        $sleep = array_intersect(array_keys($factorWeights), ['restless_sleep','sleep','insomnia','poor-sleep']);
        $category = $sleep ? 'sleep' : ($current !== null && $current <= 2 ? 'relaxation' : ($current !== null && $current >= 4 ? 'focus' : 'mindfulness'));
        if ($current === null) $category = 'relaxation';
        arsort($factorWeights);
        $trend = count($scores) >= 4 ? round(array_sum(array_slice($scores, 0, 2))/2 - array_sum(array_slice($scores, 2))/count(array_slice($scores, 2)), 2) : null;
        return ['version'=>2, 'count'=>count($scores), 'average'=>$scores ? round($plain/count($scores), 2) : null,
            'weighted_average'=>$weights ? round($sum/$weights, 2) : null, 'latest_score'=>$current,
            'trend'=>$trend, 'category'=>$category, 'factor_weights'=>$factorWeights,
            'methods'=>array_values($methods), 'goal_ids'=>array_values($goals),
            'offer_counselors'=>($current !== null && $current <= 2) || $lowDays >= 3,
            'reason'=>$scores ? 'اقتراحات حسب سجل شعورك وتفضيلاتك والأنشطة التي جرّبتها؛ ليست تقييماً طبياً.' : 'اقتراحات عامة إلى أن تسجّل شعورك.'];
    }
    public function audio(array $track, array $profile, int $rating = 0, int $completed = 0): ?array
    {
        if ($rating < 0) return null;
        $category = strtolower((string) ($track['category'] ?? ''));
        if (in_array($category, ['relax','calm'], true)) $category = 'relaxation';
        $score = 1.0; $reasons = [];
        if ($category === $profile['category']) { $score += 8; $reasons[] = $category === 'sleep' ? 'اخترته بسبب عامل النوم الذي سجّلته.' : 'يناسب نوع المساحة المختار حسب شعورك.'; }
        if (in_array('listening', $profile['methods'] ?? [], true)) { $score += 2; $reasons[]='الاستماع من طرق الدعم التي اخترتها.'; }
        if ($rating > 0) { $score += 3; $reasons[]='سبق وقيّمته كمناسب إلك.'; }
        $score -= min(4, max(0, $completed)) * 2;
        if ($completed > 0) $reasons[]='جرّبته مؤخراً؛ نعطي فرصة لخيارات أخرى.';
        if (!$reasons) $reasons[]='خيار من مكتبة الصوتيات المنشورة.';
        return ['score'=>round($score, 2), 'reason'=>implode(' ', $reasons)];
    }
}
