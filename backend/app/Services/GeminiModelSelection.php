<?php
namespace App\Services;

class GeminiModelSelection
{
    /** Only advertised text-generation Flash models; never invent a model name. */
    public function candidates(array $models, string $current): array
    {
        $current = preg_replace('#^models/#', '', trim($current));
        $names = [];
        foreach ($models as $model) {
            $name = $model['name'] ?? '';
            if (!is_string($name) || !preg_match('#^models/(gemini-[a-z0-9.-]+)$#D', $name, $match)) continue;
            $name = $match[1];
            if ($name === $current || !str_contains($name, 'flash')) continue;
            if (preg_match('/image|audio|tts|live|robot|computer|exp|thinking/', $name)) continue;
            if (!in_array('generateContent', $model['supportedGenerationMethods'] ?? [], true)) continue;
            $names[$name] = $name;
        }
        $names = array_values($names);
        usort($names, function ($a, $b) {
            $priority = fn ($name) => (str_contains($name, 'preview') ? 4 : 0) + (str_contains($name, 'lite') ? 0 : 1);
            return ($priority($a) <=> $priority($b)) ?: strnatcmp($b, $a);
        });
        return array_slice($names, 0, 2);
    }
    public function replaceModel(string $env, string $model): string
    {
        if (!preg_match('/^gemini-[a-z0-9.-]+$/D', $model)) throw new \InvalidArgumentException('Invalid model');
        preg_match_all('/^[\t ]*GEMINI_MODEL[\t ]*=[^\r\n]*/m', $env, $matches);
        if (count($matches[0]) > 1) throw new \RuntimeException('Duplicate GEMINI_MODEL settings; edit .env manually.');
        if (count($matches[0]) === 1) return preg_replace('/^[\t ]*GEMINI_MODEL[\t ]*=[^\r\n]*/m', 'GEMINI_MODEL='.$model, $env, 1);
        $eol = str_contains($env, "\r\n") ? "\r\n" : "\n";
        return $env.(str_ends_with($env, "\n") ? '' : $eol).'GEMINI_MODEL='.$model.$eol;
    }
}
