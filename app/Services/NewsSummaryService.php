<?php

namespace App\Services;

class NewsSummaryService
{
    private const DEFAULT_INPUT_LIMIT = 12000;
    private const DEFAULT_MAX_CHARS = 1200;

    public function __construct(private readonly LlmClient $llm)
    {
    }

    /**
     * Summarize a news item into a compact, labeled plain-text block.
     *
     * @return array{summary:string,model:string}
     */
    public function summarize(string $content): array
    {
        $input = $this->prepareInput($content);
        $modelName = $this->modelName();

        if ($input === '') {
            return [
                'summary' => $this->emptySummary(),
                'model' => $modelName,
            ];
        }

        $messages = [
            ['role' => 'system', 'content' => $this->systemPrompt()],
            ['role' => 'user', 'content' => $input],
        ];

        $raw = trim($this->llm->chat($messages, null, $this->summaryOverrides()));
        $summary = $this->normalizeSummary($raw);
        $summary = $this->limitSummary($summary);

        return [
            'summary' => $summary,
            'model' => $modelName,
        ];
    }

    private function systemPrompt(): string
    {
        return implode("\n", [
            "تلخّص خبراً عربياً في فقرة عربية قصيرة جداً وواضحة، تدمج العناصر التالية داخل نص واحد متماسك بدون أي عناوين أو أقسام:",
            "- الفكرة الرئيسية",
            "- أهم الأحداث",
            "- الحقائق المذكورة في الخبر",
            "- الكلمات المفتاحية الأكثر دلالة",
            "القواعد:",
            "- اجعل النص موجزاً جداً ومركّزاً.",
            "- لا تكرّر المعلومات ولا تستخدم حشو.",
            "- استخدم فقط المعلومات الموجودة في الخبر.",
            "- لا تخترع أسماء أو أرقام أو تواريخ أو أماكن.",
            "- إذا كانت معلومة غير موجودة، تجاهلها ولا تخترع بديلاً.",
            "- اجعل اللغة عربية فصيحة مختصرة ومباشرة.",
            "- ضمّن الكلمات المفتاحية داخل الجملة بشكل طبيعي بدلاً من كتابتها كقائمة منفصلة.",
            "- الإخراج نص عربي فقط، بدون عناوين، بدون قوائم، بدون أي صيغة تنسيق.",
        ]);
    }

    private function summaryOverrides(): array
    {
        $overrides = config('llm.personas.summarizer.overrides', []);
        return is_array($overrides) ? $overrides : [];
    }

    private function modelName(): string
    {
        return trim((string) config('llm.model'));
    }

    private function prepareInput(string $content): string
    {
        $value = trim((string) $content);
        if ($value === '') {
            return '';
        }

        $limit = (int) config('rag.summary.input_char_limit', self::DEFAULT_INPUT_LIMIT);
        if ($limit < 1000) {
            $limit = 1000;
        }

        if (mb_strlen($value) > $limit) {
            $value = rtrim(mb_substr($value, 0, $limit));
        }

        return $value;
    }

    private function normalizeSummary(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return $this->emptySummary();
        }

        $required = ['MAIN IDEA:', 'KEY EVENTS:', 'FACTS:', 'KEYWORDS:'];
        $hasAll = true;
        foreach ($required as $label) {
            if (stripos($raw, $label) === false) {
                $hasAll = false;
                break;
            }
        }

        if ($hasAll) {
            return $raw;
        }

        $firstLine = trim((string) strtok($raw, "\n"));
        $main = $firstLine !== '' ? $firstLine : 'Unknown';

        return "MAIN IDEA: {$main}\n"
            . "KEY EVENTS:\n- Unknown\n"
            . "FACTS:\n- Unknown\n"
            . "KEYWORDS: Unknown";
    }

    private function limitSummary(string $summary): string
    {
        $summary = trim($summary);
        $limit = (int) config('rag.summary.max_chars', self::DEFAULT_MAX_CHARS);
        if ($limit < 200) {
            $limit = 200;
        }

        if (mb_strlen($summary) <= $limit) {
            return $summary;
        }

        return rtrim(mb_substr($summary, 0, $limit));
    }

    private function emptySummary(): string
    {
        return "MAIN IDEA: Unknown\n"
            . "KEY EVENTS:\n- Unknown\n"
            . "FACTS:\n- Unknown\n"
            . "KEYWORDS: Unknown";
    }
}
