<?php
namespace App\Services;

class SupportKnowledge
{
    public static function normalize(string $text): string
    {
        $text = preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{0640}]/u', '', $text);
        return trim(preg_replace('/\s+/u', ' ', strtr(strtolower($text), ['أ'=>'ا','إ'=>'ا','آ'=>'ا','ى'=>'ي','ة'=>'ه'])));
    }
    public function urgent(string $text): bool
    {
        $text = self::normalize($text);
        foreach (['انتحار','انتحر','اقتل نفسي','اقتُل نفسي','اذي نفسي','اؤذي نفسي','بدي موت',
            'بدي اموت','ما بدي عيش','ما بدي اعيش','انهي حياتي','جرح نفسي','خطر مباشر',
            'suicid','kill myself','hurt myself','end my life','want to die','self harm',
            'self-harm','اقتل شخص','اؤذي شخص','kill someone','hurt someone',
            'زياده جرعه','جرعه زائده','overdose'] as $term) {
            if (str_contains($text, self::normalize($term))) return true;
        }
        return false; // A simple guard, never a clinical risk assessment.
    }
    public function reply(string $message, array $documents, string $previousUser = '', string $previousAssistant = ''): array
    {
        if ($this->urgent($message)) return ['content'=>
            'سلامتك أهم شي الآن. إذا ممكن تؤذي نفسك أو شخصاً آخر، تواصل فوراً مع الطوارئ المحلية أو روح لأقرب إسعاف، واطلب من شخص موثوق يبقى معك. إذا بتقدر بأمان، ابتعد عن أي شيء ممكن يؤذيك. هل أنت بمكان آمن حالياً؟',
            'sources'=>[], 'urgent'=>true, 'mode'=>'safety'];
        $normalized = self::normalize($message);
        // Recognize a new topic before considering any earlier topic.
        if (preg_match('/ملل|ملان|ملانه|مليت|زهقان|زهقانه|^ماله$/u', $normalized)) {
            return $this->local('إذا قصدك الملل، خلينا نجرّب تغييرًا صغيرًا: اختار شغلة واحدة لخمس دقايق، مثل ترتيب زاوية، مشي قصير، أو كتابة فكرة بدك تجرّبها. شو أقرب خيار إلك؟');
        }
        $followup = preg_match('/^(اي|ايوه|نعم|تمام|طيب|بعدين|كمل|كملي|كيف|ليش|شو اعمل|شو بعمل|ما فهمت|مافهمت|جربت|جربتها|ما نفع|ما نفع معي|ما زبط|ما زبطت|وبعدين)[؟?!.، ]*$/u', $normalized) === 1;
        $best = $this->match($normalized, $documents);
        if ($best === null && $followup) $best = $this->match(self::normalize($previousUser), $documents);
        if ($best !== null) {
            $content = (string)($best['text'] ?? '');
            if ($followup && $previousAssistant !== '' && str_contains(self::normalize($previousAssistant), self::normalize($content))) {
                return $this->local('واضح إن الاقتراح السابق ما كان كافي. خلّينا نحدّد الشي اللي بدك مساعدة فيه: تهدئة الشعور هلّق، ولا ترتيب خطوة عملية؟');
            }
            if ($content !== '') return ['content'=>$content,
                'sources'=>isset($best['title'], $best['url']) ? [['id'=>$best['id'] ?? null,'title'=>$best['title'],'url'=>$best['url']]] : [],
                'urgent'=>false, 'mode'=>'retrieval'];
        }
        return $this->local('ما فهمت قصدك تمامًا، وما بدي أفترض شعورك. فيك توضّح بجملة شو عم يصير معك، أو شو بتحب نساعدك تعمل؟');
    }
    private function match(string $query, array $documents): ?array
    {
        if ($query === '') return null;
        $best = null; $score = 0;
        foreach ($documents as $doc) {
            if (!is_array($doc)) continue;
            $hits = 0;
            foreach ((array)($doc['keywords'] ?? []) as $word) {
                if (!is_string($word)) continue;
                $term = self::normalize($word);
                if ($term !== '' && str_contains($query, $term)) $hits++;
            }
            if ($hits > $score) { $score = $hits; $best = $doc; }
        }
        return $best;
    }
    private function local(string $content): array
    {
        return ['content'=>$content,'sources'=>[],'urgent'=>false,'mode'=>'retrieval'];
    }
}
