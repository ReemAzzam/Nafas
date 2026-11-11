<?php
namespace App\Console\Commands;

use App\Services\{GeminiClient, GeminiModelSelection};
use Illuminate\Console\Command;
use Illuminate\Support\Facades\{Artisan, Config, Http};

class RepairNafasAi extends Command
{
    protected $signature = 'nafas:repair-ai {--apply : Save a successfully tested model to the existing .env}';
    protected $description = 'Test current Gemini model, then at most two advertised Flash alternatives with synthetic greetings';
    public function handle(): int
    {
        if (!config('wellness.cloud_enabled') || trim((string)config('wellness.gemini_key')) === '') {
            $this->error('Enable WELLNESS_CLOUD_ENABLED and set GEMINI_API_KEY in the existing .env, then run config:clear.'); return self::FAILURE;
        }
        $original = (string)config('wellness.gemini_model');
        try {
            $current = new GeminiClient;
            $text = $current->generate('Return one short Arabic greeting only.', [], 'مرحبا');
            $code = $current->diagnostics()['code'];
            $this->line('Current model test: '.$code);
            if ($text !== null) { $this->info('Current model works. No .env change needed. Enable Gemini consent in the app and send a NEW message.'); return self::SUCCESS; }
            if (!in_array($code, ['provider_unavailable','model_unavailable','invalid_model','output_limit','empty_or_long_answer','blocked_or_incomplete_answer'], true)) {
                $this->warn('Model switching is not a fix for this failure. Run nafas:check-connections --gemini for the specific guidance.'); return self::FAILURE;
            }
            $response = Http::withHeaders(['x-goog-api-key'=>trim((string)config('wellness.gemini_key'))])
                ->acceptJson()->connectTimeout(6)->timeout(15)->withOptions(['allow_redirects'=>false])
                ->get('https://generativelanguage.googleapis.com/v1beta/models', ['pageSize'=>1000]);
            if (!$response->successful()) { $this->error('Model list unavailable. HTTP: '.$response->status()); return self::FAILURE; }
            $selection = new GeminiModelSelection;
            $candidates = $selection->candidates((array)$response->json('models'), $original);
            foreach ($candidates as $name) {
                Config::set('wellness.gemini_model', $name);
                $client = new GeminiClient;
                $text = $client->generate('Return one short Arabic greeting only.', [], 'مرحبا');
                $this->line('Alternative '.$name.': '.$client->diagnostics()['code']);
                if ($text === null) {
                    if (in_array($client->diagnostics()['code'], ['quota_or_rate_limit','invalid_key','key_blocked','access_denied','region_unavailable'], true)) break;
                    continue;
                }
                if (!$this->option('apply')) {
                    $this->info('A working model was found: '.$name.'. No files changed. Run again with --apply to test and save.'); return self::SUCCESS;
                }
                $path = base_path('.env');
                if (!is_file($path) || !is_readable($path) || !is_writable($path)) throw new \RuntimeException('Cannot edit existing .env');
                $env = file_get_contents($path);
                $updated = $selection->replaceModel($env, $name);
                $backup = base_path('.env.nafas-ai-backup-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(3)));
                if (file_put_contents($backup, $env, LOCK_EX) !== strlen($env)) throw new \RuntimeException('Could not back up .env');
                @chmod($backup, 0600);
                if (file_get_contents($path) !== $env) throw new \RuntimeException('Existing .env changed during testing');
                if (file_put_contents($path, $updated, LOCK_EX) !== strlen($updated)) {
                    file_put_contents($path, $env, LOCK_EX); throw new \RuntimeException('Could not save model setting');
                }
                Artisan::call('config:clear');
                $this->info('Saved tested GEMINI_MODEL='.$name.'. Existing keys and other .env settings were preserved.');
                $this->line('Backup: '.basename($backup));
                $this->line('Restart php artisan serve. Then enable Gemini consent and send a new message in the app.');
                return self::SUCCESS;
            }
            $this->error('No tested model returned a usable answer. No .env change made. The service/account issue still needs resolution.');
            return self::FAILURE;
        } catch (\Throwable $e) {
            // Do not expose provider exception text, env contents or credentials.
            $this->error('Repair did not complete. No credentials printed. Check network, model access and writable .env, then use nafas:check-connections --gemini.');
            return self::FAILURE;
        } finally { Config::set('wellness.gemini_model', $original); }
    }
}
