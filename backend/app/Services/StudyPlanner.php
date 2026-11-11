<?php
namespace App\Services;

/** A bounded scheduler: the model cannot invent hours, deadlines or completion. */
class StudyPlanner {
 public function build(array $input, string $startDate): array {
  $subjects = $input['subjects'];
  $low = ($input['energy'] ?? 'normal') === 'low';
  $minutes = (int) $input['minutes_per_day'];
  $focus = $low ? 15 : 25;
  $slots = min(5, max(1, intdiv($minutes + 5, $focus + 5)));
  $pool = [];
  foreach ($subjects as $i=>$subject) for ($j=0;$j<(int)$subject['priority'];$j++) $pool[]=$i;
  $cursor = 0; $counts = []; $days = [];
  for ($d=0;$d<(int)$input['days'];$d++) {
   $date = (new \DateTimeImmutable($startDate))->modify('+'.$d.' days')->format('Y-m-d');
   $tasks = [];
   for ($s=0;$s<$slots;$s++) {
    $index = $pool[$cursor++ % count($pool)]; $subject=$subjects[$index]['title'];
    $round = $counts[$index] ?? 0; $counts[$index]=$round+1;
    $action = ['اختَر جزءاً صغيراً وافهمه، ثم اشرح فكرته بكلماتك',
      'أغلق المصدر واكتب 3 أسئلة وأجب عنها من الذاكرة، ثم صحّح إجاباتك',
      'حلّ سؤالاً تطبيقياً أو اكتب مثالاً، وسجّل نقطة تحتاج مراجعة'][$round % 3];
    $tasks[]=['id'=>($d+1).'-'.($s+1),'subject'=>$subject,'title'=>$action,
      'minutes'=>$focus,'break_after_minutes'=>$s<$slots-1 ? 5 : 0,'completed'=>false];
   }
   $days[]=['date'=>$date,'lightened'=>$low,'tasks'=>$tasks];
  }
  return ['version'=>1,'title'=>'خطة دراستي خطوة بخطوة','subjects'=>$subjects,
   'minutes_per_day'=>$minutes,'energy'=>$input['energy'],'days'=>$days,
   'tips'=>'ابدأ بجزء محدد، واختبر تذكّرك بدلاً من إعادة القراءة فقط. الاستراحة جزء من الوقت المحدد. إذا فات يوم، تابع اليوم التالي؛ لا تجمع المهام المتأخرة دفعة واحدة.',
   'advice_source'=>'local','created_on'=>$startDate];
 }
 public function complete(array $plan, string $id, bool $completed): array {
  $found=false;
  foreach ($plan['days'] as &$day) foreach ($day['tasks'] as &$task) if ($task['id']===$id) {
   $task['completed']=$completed; $found=true;
  }
  if (!$found) throw new \InvalidArgumentException('Unknown task');
  return $plan;
 }
 public function lighten(array $plan, string $date): array {
  $found=false;
  foreach ($plan['days'] as &$day) if ($day['date']===$date) {
   $found=true; if ($day['lightened']) return $plan;
   $kept=false; $tasks=[];
   foreach ($day['tasks'] as $task) {
    if ($task['completed']) { $tasks[]=$task; continue; }
    if (!$kept) { $task['minutes']=10; $task['title']='خطوة خفيفة: راجع فكرة واحدة واكتب سؤالاً عنها';
     $task['break_after_minutes']=0; $tasks[]=$task; $kept=true; }
   }
   // Completed work is never removed; pauses only remain between retained tasks.
   foreach ($tasks as $i=>&$task) $task['break_after_minutes']=$i<count($tasks)-1 ? 5 : 0;
   $day['tasks']=$tasks; $day['lightened']=true;
  }
  if (!$found) throw new \InvalidArgumentException('Unknown day');
  return $plan;
 }
 public function summary(array $plan): array {
  $total=0; $done=0;
  foreach ($plan['days'] as $day) foreach ($day['tasks'] as $task) { $total++; if ($task['completed']) $done++; }
  return ['total_tasks'=>$total,'completed_tasks'=>$done,'percent'=>$total ? (int)round(100*$done/$total) : 0];
 }
 public function studyRequest(string $message): bool {
  $text=SupportKnowledge::normalize($message);
  foreach (['خطة دراس','خطه دراس','جدول دراس','ساعدني ادرس','ساعدني ادرس','study plan','study schedule','خطه للمذاكره','خطة مذاكره','خطه مذاكره','بدي ادرس','مخوفني ارسب','خايف ارسب','خايفه ارسب','ما عم اقدر ادرس','مو قادر ادرس'] as $term)
   if (str_contains($text,SupportKnowledge::normalize($term))) return true;
  return false;
 }
 public function quickReply(): array {
  return ['content'=>"خلينا نحول الضغط لخطوة عملية. جرّب هالجلسة اليوم:\n1. دقيقتان: اختر درساً واحداً وحدد المطلوب منه.\n2. 15 دقيقة: ادرس جزءاً صغيراً مع إبعاد الهاتف.\n3. 5 دقائق: استراحة.\n4. 5 دقائق: أغلق الكتاب واكتب 3 أفكار تذكّرتها.\n5. 3 دقائق: صحّح الناقص وحدّد أول خطوة لبكرا.\n\nمن زر «ابني خطة دراسة» فيك تحدّد موادك ووقتك وطاقتك، وتحفظ جلساتك وتعلّم إنجازك. الخطة قابلة للتخفيف وما فيها ضمان للنتيجة.",
   'sources'=>[],'urgent'=>false,'mode'=>'study_coach'];
 }
}
