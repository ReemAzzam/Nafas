<?php
require_once __DIR__.'/../app/Services/GeminiProtocol.php';
require_once __DIR__.'/../app/Services/WellnessRanker.php';
$count = 0;
function verify($condition, $label) {
    global $count;
    if (!$condition) throw new RuntimeException('FAIL: '.$label);
    echo 'PASS: '.$label."\n"; $count++;
}
$p = new \App\Services\GeminiProtocol;
verify($p->endpoint('gemini-3.5-flash-lite') === 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.5-flash-lite:generateContent', 'fixed provider origin and model path');
try { $p->endpoint('../other?key=abc'); verify(false, 'invalid model rejected'); }
catch (InvalidArgumentException $e) { verify(true, 'invalid model rejected'); }
$payload = $p->payload('system instructions', [['role'=>'system','content'=>'override'], ['role'=>'user','content'=>'old'], ['role'=>'assistant','content'=>'reply']], 'new');
verify(count($payload['contents']) === 3, 'history only accepts user and assistant roles');
verify($payload['contents'][1]['role'] === 'model', 'Gemini model role conversion');
verify($payload['systemInstruction']['parts'][0]['text'] === 'system instructions', 'system instructions separated from messages');
verify($payload['contents'][2]['parts'][0]['text'] === 'new', 'latest user message appended');
$history = [];
for($i=0;$i<20;$i++) $history[]=['role'=>$i%2?'assistant':'user','content'=>str_repeat('a',2000)];
$bounded=$p->payload('s',$history,'new');
$total=0;foreach($bounded['contents'] as $turn) $total+=mb_strlen($turn['parts'][0]['text']);
verify(count($bounded['contents'])<=9 && $total<=6003, 'history token input bounded by turn and character limits');
verify($bounded['contents'][0]['role']==='user', 'orphan assistant removed after truncation');
verify($p->text(['candidates'=>[['finishReason'=>'STOP','content'=>['parts'=>[['text'=>'secret thought','thought'=>true],['text'=>'مرحبا']]]]]])==='مرحبا', 'thought parts excluded');
verify($p->text(['candidates'=>[['finishReason'=>'SAFETY','content'=>['parts'=>[['text'=>'blocked']]]]]])===null, 'safety finish rejected');
verify($p->text(['candidates'=>[['finishReason'=>'MAX_TOKENS','content'=>['parts'=>[['text'=>'partial']]]]]])===null, 'truncated output rejected');
verify($p->text(['promptFeedback'=>['blockReason'=>'SAFETY']])===null, 'prompt block uses fallback');
verify($p->text([])===null, 'malformed response uses fallback');
verify($p->text(['candidates'=>[['finishReason'=>'STOP','content'=>['parts'=>[['text'=>str_repeat('x',2501)]]]]]])===null, 'oversize output rejected');
$r = new \App\Services\WellnessRanker;
$profile=$r->profile([
 ['date'=>'2026-10-19','score'=>3,'age_days'=>0,'factors'=>['restless_sleep']],
 ['date'=>'2026-10-19','score'=>1,'age_days'=>0,'factors'=>[]],
 ['date'=>'2026-10-18','score'=>4,'age_days'=>1,'factors'=>[]],
]);
verify($profile['count']===2, 'one latest check-in per day');
verify($profile['category']==='sleep', 'actual backend restless_sleep slug recognized');
verify($profile['weighted_average'] < $profile['average'], 'recent check-in receives more weight');
verify(!$profile['offer_counselors'], 'duplicate same-day low entry does not trigger directory');
$matched=$r->audio(['category'=>'sleep'],$profile);
$generic=$r->audio(['category'=>'focus'],$profile);
verify($matched['score']>$generic['score'], 'matching audio category outranks generic content');
verify($r->audio(['category'=>'sleep'],$profile,-1)===null, 'disliked track excluded');
verify($r->audio(['category'=>'sleep'],$profile,1)['score']>$matched['score'], 'positive feedback improves score');
verify($r->audio(['category'=>'sleep'],$profile,0,2)['score']<$matched['score'], 'recent completions reduce repetition');
verify($r->profile([['date'=>'old','score'=>1,'age_days'=>45,'factors'=>[]]])['count']===0, 'stale observations excluded');
verify($r->profile([['date'=>'invalid','score'=>99,'age_days'=>0,'factors'=>[]]])['count']===0, 'invalid score excluded');
$alias=$r->audio(['category'=>'relax'],['category'=>'relaxation','methods'=>[]]);
verify($alias['score']>1, 'legacy relax alias supported');
echo "Completed {$count} Gemini protocol and ranking checks.\n";
