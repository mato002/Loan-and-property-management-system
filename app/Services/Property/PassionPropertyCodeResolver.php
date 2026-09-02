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

            if ($candidate === $needle || str_contains($candidate, $needle) || str_contains($needle, $candidate)) {
                $score = similar_text($candidate, $needle);
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $best = $property;
                }
            }
        }

        return $best;
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
