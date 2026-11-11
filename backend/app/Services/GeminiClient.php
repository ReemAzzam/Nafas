<?php
namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiClient
{
    private array $diagnostic = ['code'=>'not_called', 'http_status'=>null, 'finish_reason'=>null];

    public function diagnostics(): array { return $this->diagnostic; }

    public function generate(string $system, array $history, string $message): ?string
    {
        $this->diagnostic = ['code'=>'not_called', 'http_status'=>null, 'finish_reason'=>null];
        $key = trim((string)config('wellness.gemini_key'));
        if ($key === '') return $this->fail('missing_key');
        try {
            $protocol = new GeminiProtocol;
            $endpoint = $protocol->endpoint((string)config('wellness.gemini_model'));
            $budget = max(10, min(25, (int)config('nafas_connections.gemini_timeout', 25)));
            $started = microtime(true);
            $send = function (int $timeout) use ($key, $endpoint, $protocol, $system, $history, $message) {
                return Http::withHeaders(['x-goog-api-key'=>$key, 'Accept'=>'application/json'])
                    ->asJson()->connectTimeout(min($timeout, max(1, min(8, (int)config('nafas_connections.gemini_connect_timeout', 6)))))
                    ->timeout($timeout)->withOptions(['allow_redirects'=>false])
                    ->post($endpoint, $protocol->payload($system, $history, $message));
            };
            $response = $send($budget);
            // One bounded retry for an explicit 503. Never retry ambiguous network timeouts or quota failures.
            if ($response->status() === 503 && $budget - (microtime(true) - $started) >= 6) {
                usleep(400000);
                $remaining = (int)floor($budget - (microtime(true) - $started));
                if ($remaining >= 5) $response = $send($remaining);
            }
            $status = $response->status();
            $data = $response->json();
            if (!$response->successful()) return $this->fail($this->httpFailure($status, is_array($data) ? $data : []), $status);
            if (!is_array($data)) return $this->fail('invalid_response', $status);
            $finish = $data['candidates'][0]['finishReason'] ?? null;
            // Only known enum values may reach logs/diagnostics. Never raw provider strings.
            $finish = in_array($finish, ['STOP','MAX_TOKENS','SAFETY','RECITATION','OTHER','BLOCKLIST',
                'PROHIBITED_CONTENT','SPII','MALFORMED_FUNCTION_CALL','UNEXPECTED_TOOL_CALL'], true) ? $finish : null;
            $text = $protocol->text($data);
            if ($text === null) {
                $code = !empty($data['promptFeedback']['blockReason']) ? 'blocked_prompt'
                    : ($finish === 'MAX_TOKENS' ? 'output_limit' : ($finish === 'STOP' ? 'empty_or_long_answer' : 'blocked_or_incomplete_answer'));
                return $this->fail($code, $status, $finish);
            }
            $this->diagnostic = ['code'=>'ok', 'http_status'=>$status, 'finish_reason'=>$finish];
            return $text;
        } catch (\InvalidArgumentException $e) { return $this->fail('invalid_model'); }
        catch (\Throwable $e) {
            // Inspect only to classify. Never log URLs, keys, messages, history or exception text.
            $raw = strtolower($e->getMessage());
            $code = str_contains($raw, 'curl error 60') ? 'tls_certificate'
                : (str_contains($raw, 'curl error 6:') ? 'dns'
                : (str_contains($raw, 'curl error 28') || str_contains($raw, 'timed out') ? 'timeout' : 'connection_failed'));
            return $this->fail($code);
        }
    }
    private function httpFailure(int $status, array $data): string
    {
        $message = strtolower((string)($data['error']['message'] ?? ''));
        if (str_contains($message, 'location is not supported') || str_contains($message, 'region is not supported')) return 'region_unavailable';
        if (str_contains($message, 'reported as leaked')) return 'key_blocked';
        if (str_contains($message, 'api key not valid') || str_contains($message, 'api_key_invalid')) return 'invalid_key';
        return match ($status) {
            400 => 'invalid_request', 401 => 'invalid_key', 403 => 'access_denied',
            404 => 'model_unavailable', 429 => 'quota_or_rate_limit',
            500, 502, 503, 504 => 'provider_unavailable', default => 'http_failure',
        };
    }
    private function fail(string $code, ?int $status = null, ?string $finish = null): ?string
    {
        $this->diagnostic = ['code'=>$code, 'http_status'=>$status, 'finish_reason'=>$finish];
        Log::warning('nafas.gemini', $this->diagnostic);
        return null;
    }
}
