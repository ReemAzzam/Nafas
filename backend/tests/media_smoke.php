<?php
namespace Illuminate\Support\Facades {
    class Storage {
        public static function disk($name) { return new class {
            public function url($path) { return 'http://localhost:8000/storage/'.$path; }
        }; }
    }
}
namespace {
    require_once __DIR__.'/../app/Services/ImageStorageService.php';
    $service = new \App\Services\ImageStorageService;
    $cases = [
        'articles/a.jpg' => 'http://localhost:8000/storage/articles/a.jpg',
        '/storage/articles/a.jpg' => 'http://localhost:8000/storage/articles/a.jpg',
        'public/storage/exercises/a.jpg' => 'http://localhost:8000/storage/exercises/a.jpg',
        'storage/app/public/sos-counselors/a.jpg' => 'http://localhost:8000/storage/sos-counselors/a.jpg',
        'public/articles/a.jpg' => 'http://localhost:8000/storage/articles/a.jpg',
        'articles\\a.jpg' => 'http://localhost:8000/storage/articles/a.jpg',
        '//cdn.example/a.jpg' => 'https://cdn.example/a.jpg',
        'https://cdn.example/a.jpg?key=a' => 'https://cdn.example/a.jpg?key=a',
    ];
    foreach ($cases as $input => $expected) {
        if ($service->url($input) !== $expected) throw new \RuntimeException('Wrong URL: '.$input);
        echo 'PASS: '.$input."\n";
    }
    if ($service->url(null) !== null) throw new \RuntimeException('null must stay null');
    echo "PASS: null image URL\n";
    if ($service->url('   ') !== null) throw new \RuntimeException('blank must stay null');
    echo "PASS: blank image URL\n";
    echo "Completed 10 media URL behavior checks.\n";
}
