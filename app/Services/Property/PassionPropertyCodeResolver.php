<?php

namespace App\Services\Property;

use App\Models\Property;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

final class PassionPropertyCodeResolver
{
    public function resolveOne(?string $code): ?Property
    {
        $matches = $this->resolveMany($code);

        return $matches->first();
    }

    /**
     * @return Collection<int, Property>
     */
    public function resolveMany(?string $code): Collection
    {
        $code = $this->normalizeCode($code);
        if ($code === '') {
            return new Collection;
        }

        $exact = Property::query()
            ->withoutGlobalScopes()
            ->where('code', $code)
            ->orderBy('code')
            ->get();

        if ($exact->isNotEmpty()) {
            return $exact;
        }

        return Property::query()
            ->withoutGlobalScopes()
            ->where('code', 'like', $code.'%')
            ->orderBy('code')
            ->get();
    }

    public function resolveByName(?string $name): ?Property
    {
        $needle = $this->normalizeName($name);
        if ($needle === '') {
            return null;
        }

        $properties = Property::query()->withoutGlobalScopes()->get(['id', 'code', 'name']);

        $best = null;
        $bestScore = 0;

        foreach ($properties as $property) {
            $candidate = $this->normalizeName((string) $property->name);
            if ($candidate === '') {
                continue;
            }

            $score = $this->nameMatchScore($needle, $candidate);
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $property;
            }
        }

        return $bestScore >= 20 ? $best : null;
    }

    private function nameMatchScore(string $needle, string $candidate): int
    {
        if ($candidate === $needle) {
            return 1000;
        }

        if (str_contains($candidate, $needle) || str_contains($needle, $candidate)) {
            return 500 + similar_text($candidate, $needle);
        }

        $needleWords = array_values(array_filter(explode(' ', $needle)));
        $candidateWords = array_values(array_filter(explode(' ', $candidate)));

        if ($needleWords !== [] && $candidateWords !== []) {
            $shorter = count($needleWords) <= count($candidateWords) ? $needleWords : $candidateWords;
            $longer = count($needleWords) <= count($candidateWords) ? $candidateWords : $needleWords;
            $longerSet = array_flip($longer);
            $matched = 0;

            foreach ($shorter as $word) {
                if (isset($longerSet[$word])) {
                    $matched++;
                }
            }

            if ($matched > 0 && $matched === count($shorter)) {
                return 300 + ($matched * 10) + similar_text($candidate, $needle);
            }
        }

        return similar_text($candidate, $needle);
    }

    public function normalizeCode(?string $code): string
    {
        return Str::upper(trim((string) $code));
    }

    public function normalizeName(?string $name): string
    {
        $name = Str::upper(trim((string) $name));
        $name = preg_replace('/\s+/', ' ', $name) ?? $name;
        $name = preg_replace('/[^A-Z0-9 ()\-&\'.]/', '', $name) ?? $name;

        return trim($name);
    }
}
