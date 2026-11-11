<?php
require_once __DIR__.'/../app/Services/StudyPlanner.php';
require_once __DIR__.'/../app/Services/SupportKnowledge.php';
$planner=new \App\Services\StudyPlanner;
$number=0;
function studyCheck($value,string $label): void { global $number; if (!$value) throw new RuntimeException('FAIL: '.$label); $number++; echo 'PASS: '.$label."\n"; }
$input=['subjects'=>[['title'=>'رياضيات','priority'=>3],['title'=>'تاريخ','priority'=>1]],'days'=>7,'minutes_per_day'=>60,'energy'=>'normal'];
$plan=$planner->build($input,'2026-10-20');
studyCheck(count($plan['days'])===7,'requested study horizon');
studyCheck($plan['days'][6]['date']==='2026-10-26','consecutive dates');
studyCheck(count($plan['days'][0]['tasks'])===2,'60 minutes permits two focus blocks');
studyCheck($plan['days'][0]['tasks'][1]['break_after_minutes']===0,'no trailing break counted');
$budget=true;$ids=[];$counts=[];
foreach($plan['days'] as $day) { $total=0;foreach($day['tasks'] as $task){$total+=$task['minutes']+$task['break_after_minutes'];$ids[]=$task['id'];$counts[$task['subject']]=($counts[$task['subject']]??0)+1;} if($total>60)$budget=false; }
studyCheck($budget,'daily budget includes pauses');
studyCheck(count($ids)===count(array_unique($ids)),'task identifiers unique within plan');
studyCheck($counts['رياضيات']>$counts['تاريخ'],'subject priority increases allocated slots');
studyCheck($planner->summary($plan)['completed_tasks']===0,'new plan never fabricates progress');
$done=$planner->complete($plan,'1-1',true);
studyCheck($planner->summary($done)['completed_tasks']===1,'completion records one task');
studyCheck($planner->complete($done,'1-1',true)===$done,'completion is idempotent');
studyCheck($planner->summary($planner->complete($done,'1-1',false))['completed_tasks']===0,'completion is reversible');
studyCheck($planner->summary($plan)['completed_tasks']===0,'input plan is not mutated');
try{$planner->complete($plan,'999',true);studyCheck(false,'unknown task rejected');}catch(InvalidArgumentException){studyCheck(true,'unknown task rejected');}
$light=$planner->lighten($done,'2026-10-20');
studyCheck($light['days'][0]['tasks'][0]['completed']===true,'light day preserves finished work');
studyCheck($light['days'][0]['tasks'][1]['minutes']===10,'remaining light task limited to ten minutes');
studyCheck($light['days'][1]===$plan['days'][1],'light day leaves other dates unchanged');
studyCheck($planner->lighten($light,'2026-10-20')===$light,'lightening is idempotent');
$emptyLight=$planner->lighten($plan,'2026-10-20');
studyCheck(count($emptyLight['days'][0]['tasks'])===1,'lightening keeps only one unfinished step');
try{$planner->lighten($plan,'2027-01-01');studyCheck(false,'unknown day rejected');}catch(InvalidArgumentException){studyCheck(true,'unknown day rejected');}
$input['energy']='low';$input['minutes_per_day']=30;
$low=$planner->build($input,'2026-12-31');
studyCheck($low['days'][0]['tasks'][0]['minutes']===15,'low energy gets shorter sessions');
studyCheck(count($low['days'][0]['tasks'])===1,'low budget is not overfilled');
studyCheck($low['days'][1]['date']==='2027-01-01','year boundary handled');
studyCheck($planner->studyRequest('اعطيني خُطّة دِراسة'),'Arabic study intent with diacritics');
studyCheck($planner->studyRequest('I need a study plan'),'English study intent');
studyCheck(!$planner->studyRequest('اريد دواء للنوم'),'medical request is not study intent');
studyCheck($planner->quickReply()['sources']===[],'study coaching invents no citations');
studyCheck(str_contains($planner->quickReply()['content'],'15 دقيقة'),'concrete offline study action');
$allBudgets=true;
foreach(['low','normal'] as $energy) for($minutes=30;$minutes<=180;$minutes++){
 $test=$planner->build(array_merge($input,['energy'=>$energy,'minutes_per_day'=>$minutes,'days'=>1]),'2026-10-20');
 $total=array_sum(array_map(fn($task)=>$task['minutes']+$task['break_after_minutes'],$test['days'][0]['tasks']));
 if($total>$minutes)$allBudgets=false;
}
studyCheck($allBudgets,'all allowed minute budgets respected in both energy modes');
echo "Completed {$number} study planning checks.\n";
