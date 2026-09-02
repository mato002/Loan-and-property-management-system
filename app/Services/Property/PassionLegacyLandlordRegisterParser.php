<?php

namespace App\Services\Property;

use Illuminate\Support\Str;

final class PassionLegacyLandlordRegisterParser
{
    /**
     * @return list<array{
     *     code: string,
     *     name: string,
     *     pin: ?string,
     *     address: ?string,
     *     email: ?string,
     *     phone: ?string
     * }>
     */
    public function parse(string $text): array
    {
        $records = [];

        foreach (preg_split('/\R/', $text) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, 'Code Name') || str_starts_with($line, 'Property Register')) {
                continue;
            }
            if (preg_match('/^-- \d+ of \d+ --$/', $line)) {
                continue;
            }
            if (preg_match('/^Sep \d+, \d{4}/', $line)) {
                continue;
            }

            if (! preg_match('/^\d+\s+([A-Z]\d{5})\s+(.+)$/', $line, $match)) {
                continue;
            }

            $code = $match[1];
            $rest = trim($match[2]);

            $email = null;
            if (preg_match('/[\w.+-]+@[\w.-]+\.[A-Za-z]{2,}/', $rest, $emailMatch)) {
                $email = Str::lower($emailMatch[0]);
                $rest = trim(str_replace($emailMatch[0], '', $rest));
            }

            $pin = null;
            $address = null;
            if (preg_match('/\b(\d{5,}-\d{5,})\b/', $rest, $pinMatch)) {
                $pin = $pinMatch[1];
                $rest = trim(str_replace($pinMatch[0], '', $rest));
            }

            $rest = trim(preg_replace('/\b0\b/', '', $rest) ?? $rest);
            $rest = trim(preg_replace('/\s{2,}/', ' ', $rest) ?? $rest);

            $phone = null;
            if (preg_match('/\b(0\d{9})\b/', $rest, $phoneMatch)) {
                $phone = $phoneMatch[1];
                $rest = trim(str_replace($phoneMatch[0], '', $rest));
            }

            $name = trim($rest);
            if (preg_match('/^(.+?)\s+(NRB(?:\s+\d+)?)$/i', $name, $addressMatch)) {
                $name = trim($addressMatch[1]);
                $address = trim($addressMatch[2]);
            }

            if ($name === '') {
                continue;
            }

            $records[] = [
                'code' => $code,
                'name' => $name,
                'pin' => $pin !== '' ? $pin : null,
                'address' => $address !== '' ? $address : null,
                'email' => $email,
                'phone' => $phone,
            ];
        }

        return $records;
    }
}
