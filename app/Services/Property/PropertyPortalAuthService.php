<?php

namespace App\Services\Property;

use App\Models\User;
use App\Services\LoanClientIdentifierNormalizer;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class PropertyPortalAuthService
{
    public function __construct(
        private LoanClientIdentifierNormalizer $normalizer,
        private LandlordPortalOnboardingService $landlordOnboarding,
    ) {}

    public function attempt(string $login, string $password, bool $remember = false): bool
    {
        $login = trim($login);
        if ($login === '' || $password === '') {
            return false;
        }

        $user = str_contains($login, '@')
            ? User::query()->whereRaw('LOWER(email) = ?', [strtolower($login)])->first()
            : $this->landlordOnboarding->findLandlordByLogin($login);

        if (! $user instanceof User || ! Hash::check($password, (string) $user->password)) {
            return false;
        }

        Auth::login($user, $remember);

        return true;
    }

    public function normalizeLoginIdentifier(string $login): string
    {
        $login = trim($login);
        if ($login === '' || str_contains($login, '@')) {
            return strtolower($login);
        }

        return $this->normalizer->normalizePhone($login);
    }
}
