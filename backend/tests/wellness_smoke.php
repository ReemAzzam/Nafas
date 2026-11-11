<?php
// Framework-free behavior tests. Run from backend: php tests/wellness_smoke.php
require_once __DIR__.'/../app/Services/WellnessRanker.php';
require_once __DIR__.'/../app/Services/SupportKnowledge.php';

use App\Services\{WellnessRanker, SupportKnowledge};
$checks = 0;
function check($condition, string $label): void {
    global $checks;
    if (!$condition) throw new RuntimeException('FAIL: '.$label);
    $checks++;
    echo "PASS: {$label}\n";
}
$ranker = new WellnessRanker;
check($ranker->summarize([])['average'] === null, 'no history is not a diagnosis');
check($ranker->summarize([1,2,3])['offer_counselors'], 'low current mood offers directory');
check($ranker->summarize([5,5,4])['category'] === 'focus', 'positive mood ranks focus');
check($ranker->summarize([3], ['sleep'])['category'] === 'sleep', 'sleep factor changes ranking');
check($ranker->summarize([4,2,1,2])['offer_counselors'], 'repeated low scores offer directory');
check(!$ranker->summarize([3,4,5])['offer_counselors'], 'neutral record is not flagged');
check($ranker->summarize(array_fill(0, 100, 4))['count'] === 14, 'history window bounded');
$engine = new SupportKnowledge;
$docs = json_decode(file_get_contents(__DIR__.'/../resources/wellness/knowledge.json'), true, 512, JSON_THROW_ON_ERROR);
foreach (['بدي انتحر','بدي أموت','ما بدي عيش','أُريد أَنْ أُؤْذي نفسي','I want to kill myself','I took an overdose','kill someone'] as $message) {
    check($engine->reply($message, $docs)['urgent'], 'urgent bypass: '.$message);
}
check(!$engine->reply('اليوم كان طويل', $docs)['urgent'], 'ordinary text uses support');
check($engine->reply('عندي قلق وتوتر', $docs)['sources'][0]['id'] === 'grounding', 'Arabic retrieval');
check($engine->reply('sad and lonely', $docs)['sources'][0]['id'] === 'kindness', 'English retrieval');
check($engine->reply('لسا', $docs, 'عندي قلق')['sources'][0]['id'] === 'grounding', 'follow-up uses prior user turn');
check($engine->reply('احكي عن كرة القدم', $docs)['sources'] === [], 'unknown topic does not invent a source');
check($engine->reply('بدي انتحر مع توتر', $docs)['sources'] === [], 'crisis bypasses content retrieval');
check(SupportKnowledge::normalize('إِرْهاق') === 'ارهاق', 'Arabic normalization');
echo "Completed {$checks} behavioral checks.\n";
