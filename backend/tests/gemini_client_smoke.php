<?php
namespace Illuminate\Support\Facades {
    class Http {
        public static array $request = [];
        public static int $status = 200;
        public static array $response = [];
        public static bool $throw = false;
        public static int $calls = 0;
        public static function withHeaders($headers) { self::$request=['headers'=>$headers]; return new self; }
        public function asJson() { return $this; }
        public function connectTimeout($value) { self::$request['connect_timeout']=$value; return $this; }
        public function timeout($value) { self::$request['timeout']=$value; return $this; }
        public function withOptions($value) { self::$request['options']=$value; return $this; }
        public function post($url, $body) {
            self::$calls++;self::$request['url']=$url;self::$request['body']=$body;
            if(self::$throw) throw new \RuntimeException('SIMULATED private provider exception');
            return $this;
        }
        public function successful() { return self::$status>=200 && self::$status<300; }
        public function json() { return self::$response; }
    }
}
namespace {
    $settings = [
        'wellness.gemini_key'=>'TEST_KEY_NOT_REAL', 'wellness.gemini_model'=>'gemini-3.5-flash-lite',
        'wellness.cloud_enabled'=>true, 'wellness.knowledge_path'=>__DIR__.'/../resources/wellness/knowledge.json',
    ];
    function config($key, $default = null) { global $settings; return $settings[$key] ?? $default; }
    require_once __DIR__.'/../app/Services/GeminiProtocol.php';
    require_once __DIR__.'/../app/Services/GeminiClient.php';
    require_once __DIR__.'/../app/Services/SupportKnowledge.php';
    require_once __DIR__.'/../app/Services/SupportChatService.php';
    require_once __DIR__.'/../app/Services/StudyPlanner.php';
    $n=0;
    function testCheck($condition,$label) { global $n;if(!$condition)throw new \RuntimeException('FAIL: '.$label);$n++;echo 'PASS: '.$label."\n"; }
    $client = new \App\Services\GeminiClient;
    \Illuminate\Support\Facades\Http::$response=['candidates'=>[['finishReason'=>'STOP','content'=>['parts'=>[['text'=>'مرحبا']]]]]];
    testCheck($client->generate('system',[],'hello')==='مرحبا','client accepts valid Gemini response');
    $request=\Illuminate\Support\Facades\Http::$request;
    testCheck($request['headers']['x-goog-api-key']==='TEST_KEY_NOT_REAL','key is sent as header');
    testCheck(!str_contains($request['url'],'TEST_KEY_NOT_REAL') && !str_contains($request['url'],'?key='),'key is absent from URL');
    testCheck($request['options']['allow_redirects']===false,'provider redirects disabled');
    testCheck($request['timeout']===12,'network timeout bounded');
    foreach([400,401,403,404,429,500] as $code) {
        \Illuminate\Support\Facades\Http::$status=$code;
        testCheck($client->generate('s',[],'x')===null,'HTTP '.$code.' returns safe fallback signal');
    }
    \Illuminate\Support\Facades\Http::$status=200;
    \Illuminate\Support\Facades\Http::$throw=true;
    testCheck($client->generate('s',[],'x')===null,'network exceptions redacted');
    \Illuminate\Support\Facades\Http::$throw=false;
    $settings['wellness.gemini_key']='';$before=\Illuminate\Support\Facades\Http::$calls;
    testCheck($client->generate('s',[],'x')===null && \Illuminate\Support\Facades\Http::$calls===$before,'empty key never makes HTTP request');
    $settings['wellness.gemini_key']='TEST_KEY_NOT_REAL';
    class StubGemini extends \App\Services\GeminiClient {
        public int $calls=0; public ?string $answer='رد اختبار';
        public function generate(string $system,array $history,string $message): ?string { $this->calls++; return $this->answer; }
    }
    $fake=new StubGemini;$service=new \App\Services\SupportChatService($fake);
    $reply=$service->respond('عندي قلق',[],false);
    testCheck($fake->calls===0 && $reply['mode']==='retrieval','no cloud transfer without user consent');
    $settings['wellness.cloud_enabled']=false;
    $service->respond('عندي قلق',[],true);
    testCheck($fake->calls===0,'server cloud disable overrides consent');
    $settings['wellness.cloud_enabled']=true;
    $reply=$service->respond('بدي أموت',[],true);
    testCheck($fake->calls===0 && $reply['urgent'] && $reply['mode']==='safety','crisis bypasses Gemini entirely');
    $reply=$service->respond('اليوم صعب',[],true);
    testCheck($fake->calls===1 && $reply['mode']==='gemini','consented request uses Gemini');
    testCheck($reply['sources']===[],'generated response does not inherit unsupported citations');
    $fake->answer=null;$reply=$service->respond('عندي قلق',[],true);
    testCheck($reply['mode']==='local_fallback' && str_contains($reply['content'],'الضغط'),'provider failure retains local supportive response');
    $before=$fake->calls;
    $reply=$service->respond('اعطيني خطة دراسة لخفف الضغط',[],true);
    testCheck($reply['mode']==='study_coach' && str_contains($reply['content'],'15 دقيقة'),'study request gets concrete steps when provider is unavailable');
    testCheck($fake->calls===$before,'study schedule does not depend on cloud transfer');
    $reply=$service->respond('بدي انتحر واعطيني خطة دراسة',[],true);
    testCheck($reply['urgent'] && $reply['mode']==='safety','crisis precedes study intent');
    echo "Completed {$n} client and consent orchestration checks with a fake HTTP facade.\n";
}
