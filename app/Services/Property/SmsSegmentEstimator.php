<?php

namespace App\Services\Property;

class SmsSegmentEstimator
{
    /**
     * Estimate GSM-7 SMS segments (standard Latin billing).
     *
     * @return array{chars:int,segments:int,encoding:string}
     */
    public function estimate(string $text): array
    {
        $chars = mb_strlen($text);
        $isGsm = $this->isGsm7($text);
        $singleLimit = $isGsm ? 160 : 70;
        $multiLimit = $isGsm ? 153 : 67;

        if ($chars <= $singleLimit) {
            return [
                'chars' => $chars,
                'segments' => $chars === 0 ? 0 : 1,
                'encoding' => $isGsm ? 'GSM-7' : 'UCS-2',
            ];
        }

        return [
            'chars' => $chars,
            'segments' => (int) ceil($chars / $multiLimit),
            'encoding' => $isGsm ? 'GSM-7' : 'UCS-2',
        ];
    }

    public function estimatedCost(string $text, float $costPerSms): float
    {
        $segments = (int) ($this->estimate($text)['segments'] ?? 0);

        return round(max(0, $segments) * $costPerSms, 4);
    }

    private function isGsm7(string $text): bool
    {
        return preg_match('/[^\x00-\x7F€£¥èéùìòÇØøÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ!\"#¤%&\'()*+,\-.\/0-9:;<=>?@A-Z\[\\\\\]^\`a-z{|}~\s]/u', $text) !== 1;
    }
}
