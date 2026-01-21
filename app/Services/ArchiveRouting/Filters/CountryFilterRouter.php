<?php

namespace App\Services\ArchiveRouting\Filters;

use App\Services\ArchiveRouting\AllowedFormatter;
use App\Services\ArchiveRouting\RouterClient;

class CountryFilterRouter
{
    public function __construct(
        private readonly RouterClient $client,
        private readonly AllowedFormatter $formatter,
    ) {
    }

    /**
     * @param  array<int,string>  $allowedValues
     * @return array{value:?string,used:bool}
     */
    public function route(string $content, array $allowedValues, string $model, array $options): array
    {
        $max = (int) config('rag.auto_router.max_values', 60);
        $countryMax = (int) config('rag.auto_router.max_values_country', 0);
        if ($countryMax > 0) {
            $max = $countryMax;
        }

        $allowedList = $this->formatter->formatAllowed($allowedValues, $max);
        $prompt = $this->buildPrompt($content, $allowedList);
        $parsed = $this->client->call($prompt, $model, $options);

        return [
            'value' => $this->extractValue($parsed),
            'used' => is_array($parsed),
        ];
    }

    private function buildPrompt(string $content, string $allowedList): string
    {
        return <<<PROMPT
        أنت مسؤول عن اختيار أفضل فلتر "الدولة" لعملية البحث داخل أرشيف غرفة الأخبار.

        أعد النتيجة بصيغة JSON صحيحة فقط:
        {"value": string|null}

        القواعد:
        - اختر قيمة واحدة كحد أقصى من القائمة المسموح بها.
        - إذا لم يوجد تطابق واضح، أعد القيمة null.
        - ممنوع اختراع أو تخمين أي قيمة غير موجودة في القائمة.
        - فلتر الدولة هو الأعلى أولوية: إذا وُجد أي تلميح جغرافي، اختر الدولة الأنسب.

        قائمة الدول المسموح بها:
        {$allowedList}

        سؤال المستخدم:
        {$content}
        PROMPT;
    }

    /**
     * @param  array<string,mixed>|null  $parsed
     */
    private function extractValue(?array $parsed): ?string
    {
        if (!is_array($parsed) || !array_key_exists('value', $parsed)) {
            return null;
        }

        $raw = $parsed['value'];
        if ($raw === null || $raw === '') {
            return null;
        }

        return trim((string) $raw);
    }
}
