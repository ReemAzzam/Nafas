<?php
// Offline behavioral regression checks. Run: php tests/nafas_connections_smoke.php
namespace Illuminate\Support\Facades {
    class Config { public static function set($key,$value) { global $settings; $settings[$key]=$value; } }
    class Artisan { public static array $calls=[]; public static function call($name) { self::$calls[]=$name; return 0; } }
    class Log { public static array $events=[]; public static function warning($name,$data) { self::$events[]=[$name,$data]; } }
    class Http {
        public static array $queue=[], $calls=[];
        private array $headers=[];
        public static function withHeaders($headers) { $r=new self; $r->headers=$headers; return $r; }
        public static function acceptJson() { return new self; }
        public function asJson() { return $this; }
        public function connectTimeout($v) { return $this; }
        public function timeout($v) { return $this; }
        public function withOptions($v) { return $this; }
        public function post($url,$data) { return $this->request($url,$data); }
        public function get($url,$data) { return $this->request($url,$data); }
        private function request($url,$data) {
            self::$calls[]=[$url,$data,$this->headers];
            if (!self::$queue) throw new \RuntimeException('Unexpected HTTP call');
            $next=array_shift(self::$queue);
            if ($next instanceof \Throwable) throw $next;
            return new FakeResponse($next[0],$next[1]);
        }
    }
    class FakeResponse {
        public function __construct(private int $statusCode,private array $data) {}
        public function successful() { return $this->statusCode>=200 && $this->statusCode<300; }
        public function status() { return $this->statusCode; }
        public function json($key = null) { return $key === null ? $this->data : ($this->data[$key] ?? null); }
    }
    class Cache {
        public static array $records=[];
        public static int $releases=0;
        public static bool $locked=false;
        public static function store($name) { return new self; }
        public function get($key) { return self::$records[$key]??null; }
        public function put($key,$value,$ttl) { self::$records[$key]=$value; }
        public function has($key) { return isset(self::$records[$key]); }
        public function forget($key) { unset(self::$records[$key]); }
        public function lock($key,$ttl) { return new FakeLock; }
    }
    class FakeLock {
        public function get() { return !Cache::$locked; }
        public function release() { Cache::$releases++; }
    }
}
namespace Illuminate\Console {
    class Command {
        public const SUCCESS=0, FAILURE=1;
        public array $options=[], $output=[];
        public function option($name) { return $this->options[$name] ?? false; }
        public function line($message) { $this->output[]=$message; }
        public function info($message) { $this->output[]=$message; }
        public function warn($message) { $this->output[]=$message; }
        public function error($message) { $this->output[]=$message; }
    }
}
namespace Illuminate\Foundation\Http { class FormRequest {} }
namespace {
    $settings=[
        'wellness.gemini_key'=>'SYNTHETIC_SECRET_KEY', 'wellness.gemini_model'=>'gemini-test',
        'nafas_videos.key'=>'SYNTHETIC_VIDEO_KEY', 'nafas_videos.timezone'=>'Asia/Damascus',
        'nafas_videos.playlists'=>['PLabcdefghijk0001'], 'nafas_videos.count'=>4,
    ];
    $testRoot=sys_get_temp_dir().'/nafas-ai-test-'.bin2hex(random_bytes(6));
    mkdir($testRoot,0700,true);
    function base_path($path='') { global $testRoot; return $testRoot.($path!==''?'/'.$path:''); }
    function config($key,$default=null) { global $settings; return $settings[$key]??$default; }
    require __DIR__.'/../app/Services/GeminiProtocol.php';
    require __DIR__.'/../app/Services/GeminiClient.php';
    require __DIR__.'/../app/Services/DailyVideoPolicy.php';
    require __DIR__.'/../app/Services/DailyVideosService.php';
    require __DIR__.'/../app/Services/GeminiModelSelection.php';
    require __DIR__.'/../app/Services/SupportKnowledge.php';
    require __DIR__.'/../app/Services/SupportChatService.php';
    require __DIR__.'/../app/Services/StudyPlanner.php';
    require __DIR__.'/../app/Console/Commands/RepairNafasAi.php';
    require __DIR__.'/../app/Console/Commands/WatchNafasVideos.php';
    require __DIR__.'/../app/Http/Requests/Api/Mood/StoreMoodLogRequest.php';
    use Illuminate\Support\Facades\{Http,Cache,Log};
    $checks=0;
    function check($value,$label) { global $checks; if(!$value) throw new \RuntimeException('FAIL: '.$label); $checks++; echo 'PASS '.$label."\n"; }
    function queue($status,$data) { Http::$queue[]=[$status,$data]; }
    function video($id) { return ['id'=>$id, 'snippet'=>['title'=>'فيديو تنفّس','channelTitle'=>'Test Channel', 'thumbnails'=>['high'=>['url'=>'https://i.ytimg.com/vi/'.$id.'/hqdefault.jpg']]], 'status'=>['privacyStatus'=>'public','uploadStatus'=>'processed'], 'contentDetails'=>['duration'=>'PT3M']]; }
    function playlist($ids) { queue(200,['items'=>array_map(fn($id)=>['contentDetails'=>['videoId'=>$id]],$ids)]); }
    $client=new \App\Services\GeminiClient;
    $protocol=new \App\Services\GeminiProtocol;
    queue(200,['candidates'=>[['finishReason'=>'STOP','content'=>['parts'=>[['text'=>'private thought','thought'=>true],['text'=>'مرحبا']]]]]]);
    check($client->generate('system',[],'private user text')==='مرحبا','Gemini accepts complete visible answer and excludes thoughts');
    check($client->diagnostics()['code']==='ok','Gemini success diagnostic');
    check(!str_contains(Http::$calls[0][0],'SYNTHETIC_SECRET_KEY'),'Gemini key is absent from URL');
    check(Http::$calls[0][1]['generationConfig']['maxOutputTokens']===2048,'thinking-capable output budget is enlarged');
    queue(429,['error'=>['message'=>'SYNTHETIC_SECRET_KEY private provider text']]);
    check($client->generate('s',[],'x')===null && $client->diagnostics()['code']==='quota_or_rate_limit','quota failure classified');
    queue(404,['error'=>[]]); $client->generate('s',[],'x');
    check($client->diagnostics()['code']==='model_unavailable','invalid model access classified without silent model switch');
    Http::$queue[]=new \RuntimeException('cURL error 28: Operation timed out with SYNTHETIC_SECRET_KEY');
    $client->generate('s',[],'x'); check($client->diagnostics()['code']==='timeout','network timeout classified');
    Http::$queue[]=new \RuntimeException('cURL error 60: private cert URL');
    $client->generate('s',[],'x'); check($client->diagnostics()['code']==='tls_certificate','TLS failure classified without disabling verification');
    queue(200,['candidates'=>[['finishReason'=>'MAX_TOKENS','content'=>['parts'=>[['text'=>'partial advice']]]]]]);
    check($client->generate('s',[],'x')===null && $client->diagnostics()['code']==='output_limit','incomplete advice not passed off as complete');
    check($protocol->text(['promptFeedback'=>['blockReason'=>'SAFETY']])===null,'blocked output remains blocked');
    check($protocol->endpoint(' models/gemini-test ')==='https://generativelanguage.googleapis.com/v1beta/models/gemini-test:generateContent','pasted model prefix normalized');
    $settings['wellness.gemini_key']=''; $before=count(Http::$calls); $client->generate('s',[],'x');
    check(count(Http::$calls)===$before && $client->diagnostics()['code']==='missing_key','missing key never sends network request');
    $settings['wellness.gemini_key']='SYNTHETIC_SECRET_KEY';
    $videos=new \App\Services\DailyVideosService;
    $ids=['abcdefghij1','abcdefghij2','abcdefghij3','abcdefghij4','abcdefghij5'];
    playlist($ids); queue(200,['items'=>array_map('video',$ids)]);
    $feed=$videos->feed(true);
    check($feed['meta']['state']==='ready' && count($feed['items'])===4,'public daily feed yields four normalized videos');
    $before=count(Http::$calls); $cached=$videos->feed();
    check(count(Http::$calls)===$before && $cached===$feed,'successful same-day refresh cached');
    queue(403,['error'=>['errors'=>[['reason'=>'quotaExceeded']], 'message'=>'SYNTHETIC_VIDEO_KEY']]);
    $stale=$videos->feed(true);
    check($stale['meta']['state']==='stale' && $stale['items']===$feed['items'],'failed refresh preserves last usable videos');
    check($videos->diagnostics()['reason']==='quotaExceeded','YouTube quota error reported safely');
    $before=count(Http::$calls); $pending=$videos->feed(); check(count(Http::$calls)===$before && $pending['meta']['state']==='stale','failed refresh has a bounded cooldown and remains visibly stale');
    foreach(array_keys(Cache::$records) as $cacheKey) if(str_ends_with($cacheKey, ':retry')) unset(Cache::$records[$cacheKey]);
    playlist($ids); queue(200,['items'=>array_map('video',$ids)]);
    check($videos->feed()['meta']['state']==='ready' && count(Http::$calls)===$before+2, 'same-day failed refresh retries automatically after cooldown');
    playlist($ids); queue(200,['items'=>array_map('video',$ids)]); $feed=$videos->feed(true);
    check($feed['meta']['state']==='ready','force bypasses cooldown');
    // One invalid playlist must not prevent a valid second playlist from working.
    Cache::$records=[]; $settings['nafas_videos.playlists']=['PLabcdefghijk0001','PLabcdefghijk0002'];
    queue(404,['error'=>['errors'=>[['reason'=>'playlistNotFound']]]]);
    playlist($ids); queue(200,['items'=>array_map('video',$ids)]);
    $partial=$videos->feed(true);
    check(count($partial['items'])===4 && $partial['meta']['state']==='stale','second playlist survives first playlist failure');
    check($partial['meta']['refresh_after_seconds']===60,'partial refresh retries instead of freezing all day');
    playlist($ids); playlist($ids); queue(200,['items'=>array_map('video',$ids)]);
    check($videos->feed(true)['meta']['state']==='ready','recovered partial refresh becomes ready');
    $settings['nafas_videos.key']='REPLACEMENT_KEY'; $before=count(Http::$calls);
    playlist($ids); playlist($ids); queue(200,['items'=>array_map('video',$ids)]); $videos->feed();
    check(count(Http::$calls)===$before+3,'key change bypasses old successful cache');
    Cache::$locked=true; $locked=$videos->feed(true); Cache::$locked=false;
    check($videos->diagnostics()['code']==='refresh_in_progress','concurrent refresh does not send duplicate requests');
    Cache::$records=[]; $settings['nafas_videos.playlists']=['https://www.youtube.com/playlist?list=PLabcdefghijk0001'];
    playlist($ids); queue(200,['items'=>array_map('video',$ids)]);
    check($videos->feed(true)['meta']['state']==='ready','pasted playlist URL accepted');
    $settings['nafas_videos.key']=''; $before=count(Http::$calls);
    check($videos->feed(true)['meta']['state']==='not_configured' && count(Http::$calls)===$before,'missing YouTube key sends no request');
    $log=json_encode(Log::$events);
    check(!str_contains($log,'SYNTHETIC_SECRET_KEY') && !str_contains($log,'SYNTHETIC_VIDEO_KEY') && !str_contains($log,'private user text'), 'logs contain no credentials or conversation bodies');
    // A real 503 is retried once, within the original wall-clock budget.
    $settings['wellness.gemini_key']='SYNTHETIC_SECRET_KEY';
    $before=count(Http::$calls);
    queue(503,['error'=>[]]);
    queue(200,['candidates'=>[['finishReason'=>'STOP','content'=>['parts'=>[['text'=>'مرحبا']]]]]]);
    check($client->generate('s',[],'x')==='مرحبا' && count(Http::$calls)===$before+2,'503 can recover after one bounded retry');
    queue(503,['error'=>[]]); queue(503,['error'=>[]]); $before=count(Http::$calls);
    check($client->generate('s',[],'x')===null && count(Http::$calls)===$before+2,'persistent 503 retries only once');
    $knowledge=new \App\Services\SupportKnowledge;
    $docs=[['id'=>'stress','title'=>'مرجع','url'=>'https://example.org','keywords'=>['قلق','ضغط'],'text'=>'اقتراح قديم للضغط']];
    $reply=$knowledge->reply('مالة',$docs,'عندي قلق');
    check(str_contains($reply['content'],'الملل') && !str_contains($reply['content'],'اقتراح قديم'),'new boredom topic does not inherit old stress response');
    $reply=$knowledge->reply('شو بعمل؟',$docs,'عندي قلق');
    check($reply['content']==='اقتراح قديم للضغط','genuine short followup can use preceding topic');
    $reply=$knowledge->reply('شو بعمل؟',$docs,'عندي قلق','اقتراح قديم للضغط');
    check($reply['content']!=='اقتراح قديم للضغط','followup does not repeat the same advice verbatim');
    $reply=$knowledge->reply('فنجان',$docs,'عندي قلق');
    check(str_contains($reply['content'],'ما فهمت') && $reply['sources']===[],'ambiguous new message asks for clarification');
    check($knowledge->reply('بدي انتحر وانا ملان',$docs)['urgent']===true,'safety routing precedes boredom');
    $selection=new \App\Services\GeminiModelSelection;
    $models=[];
    foreach(['gemini-9-flash','gemini-8-flash-lite','gemini-10-flash-image','gemini-11-flash-tts','gemini-12-pro','gemini-13-flash-preview'] as $name)
        $models[]=['name'=>'models/'.$name,'supportedGenerationMethods'=>['generateContent']];
    $selected=$selection->candidates($models,'gemini-9-flash');
    check($selected===['gemini-8-flash-lite','gemini-13-flash-preview'],'repair only selects advertised compatible models and excludes current/image/audio/pro');
    $env="GEMINI_API_KEY=KEEP_SECRET\r\nAPP_KEY=KEEP_APP_KEY\r\nGEMINI_MODEL=old-model\r\nDB_PASSWORD=KEEP_DATABASE\r\n";
    $changed=$selection->replaceModel($env,'gemini-8-flash-lite');
    check($changed===str_replace('GEMINI_MODEL=old-model','GEMINI_MODEL=gemini-8-flash-lite',$env),'model repair preserves all other env lines and CRLF');
    try { $selection->replaceModel("GEMINI_MODEL=a\nGEMINI_MODEL=b\n",'gemini-test'); check(false,'duplicate env must fail'); }
    catch(\RuntimeException $e) { check(true,'duplicate model settings are not silently rewritten'); }
    $request=new \App\Http\Requests\Api\Mood\StoreMoodLogRequest;
    check($request->rules()['factor_ids']==='nullable|array|max:3','backend limits factor selection to three');
    check($request->rules()['mood_score']==='required|integer|min:1|max:5','factor change preserves five-point mood scale');
    // Test the actual repair command using a synthetic .env in an isolated temporary folder.
    $settings['wellness.cloud_enabled']=true;
    $settings['wellness.gemini_model']='gemini-test';
    $originalEnv="GEMINI_API_KEY=SYNTHETIC_SECRET_KEY\r\nAPP_KEY=UNCHANGED\r\nGEMINI_MODEL=gemini-test\r\n";
    file_put_contents(base_path('.env'),$originalEnv);
    queue(503,['error'=>[]]); queue(503,['error'=>[]]);
    queue(200,['models'=>[['name'=>'models/gemini-8-flash-lite','supportedGenerationMethods'=>['generateContent']]]]);
    queue(200,['candidates'=>[['finishReason'=>'STOP','content'=>['parts'=>[['text'=>'مرحبا']]]]]]);
    $repair=new \App\Console\Commands\RepairNafasAi;
    $repair->options=['apply'=>true];
    check($repair->handle()===0,'repair command saves only an alternative with a successful synthetic response');
    check(file_get_contents(base_path('.env'))===str_replace('GEMINI_MODEL=gemini-test','GEMINI_MODEL=gemini-8-flash-lite',$originalEnv),'repair changes only GEMINI_MODEL');
    $backups=glob(base_path('.env.nafas-ai-backup-*'));
    check(count($backups)===1 && file_get_contents($backups[0])===$originalEnv,'repair takes an exact env backup before writing');
    check(\Illuminate\Support\Facades\Artisan::$calls===['config:clear'],'successful repair clears config cache');
    check(config('wellness.gemini_model')==='gemini-test','temporary probe settings are reset after command');
    $before=count(Http::$calls);
    queue(429,['error'=>[]]);
    $quotaRepair=new \App\Console\Commands\RepairNafasAi;
    $quotaRepair->options=['apply'=>true];
    $savedEnv=file_get_contents(base_path('.env'));
    check($quotaRepair->handle()===1 && count(Http::$calls)===$before+1,'repair does not probe more models after a quota failure');
    check(file_get_contents(base_path('.env'))===$savedEnv,'failed repair leaves env unchanged');
    $settings['wellness.knowledge_path']=base_path('knowledge.json');
    file_put_contents(config('wellness.knowledge_path'),json_encode($docs));
    class FakeChatGemini extends \App\Services\GeminiClient {
        public int $count=0;
        public ?string $answer='جواب مخصص';
        public function generate(string $system,array $history,string $message): ?string { $this->count++; return $this->answer; }
    }
    $fake=new FakeChatGemini; $chat=new \App\Services\SupportChatService($fake);
    $chat->respond('عندي قلق',[],false);
    check($fake->count===0,'no conversation transfer without explicit cloud consent');
    $settings['wellness.cloud_enabled']=false; $chat->respond('عندي قلق',[],true);
    check($fake->count===0,'server cloud disable still overrides client consent');
    $settings['wellness.cloud_enabled']=true;
    $reply=$chat->respond('اعطيني خطة دراسة لامتحانين',[],true);
    check($fake->count===1 && $reply['mode']==='gemini','consented study questions reach the model instead of always returning the static schedule');
    $fake->answer=null;
    $reply=$chat->respond('اعطيني خطة دراسة',[],true);
    check($reply['mode']==='local_fallback' && str_contains($reply['content'],'15 دقيقة'),'study fallback remains actionable when model fails');
    $before=$fake->count; $reply=$chat->respond('بدي انتحر واعطيني خطة دراسة',[],true);
    check($reply['urgent'] && $fake->count===$before,'urgent support does not wait for provider availability');
    foreach(glob(base_path('*')) as $tempFile) if(is_file($tempFile)) unlink($tempFile);
    foreach(glob(base_path('.*')) as $tempFile) if(is_file($tempFile)) unlink($tempFile);
    rmdir($testRoot);
    $settings['nafas_videos.key']='SYNTHETIC_VIDEO_KEY';
    $settings['nafas_videos.playlists']=['PLabcdefghijk0001'];
    Cache::$records=[];
    $before=count(Http::$calls);
    $cold=$videos->feed(false,true);
    check(count(Http::$calls)===$before && $cold['meta']['state']==='refreshing','cold phone feed never blocks on Google requests');
    check($cold['meta']['refresh_after_seconds']===5,'cold phone feed polls cache without waiting for a daily interval');
    playlist($ids); queue(200,['items'=>array_map('video',$ids)]);
    check($videos->feed()['meta']['state']==='ready','separate CLI refresh warms the existing daily cache');
    $before=count(Http::$calls);
    $phone=$videos->feed(false,true);
    check(count(Http::$calls)===$before && count($phone['items'])===4 && $phone['meta']['state']==='ready','warm phone feed serves four items with zero provider calls');
    foreach(Cache::$records as $cacheKey=>$record) {
        if(is_array($record) && isset($record['synced_at'])) { $record['day']='2000-01-01'; Cache::$records[$cacheKey]=$record; }
    }
    $old=$videos->feed(false,true);
    check(count(Http::$calls)===$before && count($old['items'])===4 && $old['meta']['state']==='stale','expired daily selection is returned immediately while worker refreshes');
    playlist($ids); queue(200,['items'=>array_map('video',$ids)]);
    $worker=new \App\Console\Commands\WatchNafasVideos;
    $worker->options=['once'=>true];
    check($worker->handle($videos)===0,'dedicated worker once mode refreshes stale selection');
    check($videos->feed(false,true)['meta']['state']==='ready','worker refresh is visible to subsequent phone reads');
    check(Http::$queue===[], 'all expected fake HTTP responses were consumed');
    echo "Completed {$checks} offline connection checks. No live API requests sent.\n";
}
