<?php
namespace App\Services;

/** Pure protocol helpers, independently testable without credentials/network. */
class GeminiProtocol
{
    public function endpoint(string $model): string
    {
        $model = preg_replace('#^models/#', '', trim($model));
        if (!preg_match('/^gemini-[a-z0-9.-]+$/', $model)) throw new \InvalidArgumentException('Invalid Gemini model name.');
        return 'https://generativelanguage.googleapis.com/v1beta/models/'.$model.':generateContent';
    }
    public function payload(string $system, array $history, string $message): array
    {
        $contents = [];
        $remaining = 6000;
        foreach (array_reverse(array_slice($history, -8)) as $turn) {
            if (!in_array($turn['role'] ?? '', ['user', 'assistant'], true)) continue;
            $text = mb_substr((string) ($turn['content'] ?? ''), 0, min(2000, $remaining));
            if ($text === '') continue;
            $remaining -= mb_strlen($text);
            array_unshift($contents, ['role'=>$turn['role']==='assistant' ? 'model' : 'user', 'parts'=>[['text'=>$text]]]);
            if ($remaining <= 0) break;
        }
        // Truncating history may leave an orphan assistant at the start.
        while ($contents && $contents[0]['role'] === 'model') array_shift($contents);
        $contents[] = ['role'=>'user', 'parts'=>[['text'=>$message]]];
        return ['systemInstruction'=>['parts'=>[['text'=>$system]]], 'contents'=>$contents,
            'generationConfig'=>['temperature'=>0.3, 'maxOutputTokens'=>max(600, min(4096, (int)config('nafas_connections.gemini_output_tokens', 2048)))]];
    }
    public function text(array $response): ?string
    {
        if (!empty($response['promptFeedback']['blockReason'])) return null;
        $candidate = $response['candidates'][0] ?? [];
        if (($candidate['finishReason'] ?? '') !== 'STOP') return null;
        $parts = [];
        foreach ($candidate['content']['parts'] ?? [] as $part) {
            if (empty($part['thought']) && isset($part['text']) && is_string($part['text'])) $parts[] = $part['text'];
        }
        $text = trim(implode("\n", $parts));
        return $text !== '' && mb_strlen($text) <= 4000 ? $text : null;
    }
}
