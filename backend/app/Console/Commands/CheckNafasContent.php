<?php
namespace App\Console\Commands;

use App\Models\{Article, Exercise};
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckNafasContent extends Command
{
    protected $signature = 'nafas:check-content';
    protected $description = 'Read-only database and published-content check; does not import YouTube as exercises';
    public function handle(): int
    {
        try {
            DB::connection()->getPdo();
            $this->info('Database: connected');
            $this->line('Published exercises: '.Exercise::where('is_published', true)->count());
            $this->line('Published articles: '.Article::where('is_published', true)->count());
            $this->line('Exercises/articles come from published app content. YouTube daily videos are a separate feed.');
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Content query failed. Start MySQL, check DB_PORT in your existing .env and run config:clear. If it persists, inspect storage/logs/laravel.log locally. No credentials printed.');
            return self::FAILURE;
        }
    }
}
