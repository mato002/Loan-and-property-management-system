<?php

namespace App\Services\Property;

use App\Mail\LandlordPortalCredentialsMail;
use App\Models\PmPortalAction;
use App\Models\User;
use App\Support\Property\LandlordWorkspaceScope;
use App\Services\BulkSmsService;
use App\Services\LoanClientIdentifierNormalizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class LandlordPortalOnboardingService
{
    public function __construct(
        private LoanClientIdentifierNormalizer $normalizer,
        private BulkSmsService $bulkSms,
    ) {}

    /**
     * @param  array<string,array{enabled:bool,required:bool}>  $landlordFields
     * @param  callable(string): bool  $isFieldRequired
     * @return array{name: string, email: ?string, phone: ?string, password: string}
     */
    public function validateOnboardPayload(Request $request, array $landlordFields, callable $isFieldRequired): array
    {
        $emailRaw = trim((string) $request->input('email', ''));
        $phoneRaw = trim((string) $request->input('phone', ''));
        $request->merge([
            'email' => $emailRaw !== '' ? strtolower($emailRaw) : null,
            'phone' => $phoneRaw !== '' ? $this->normalizer->normalizePhone($phoneRaw) : null,
        ]);

        $data = $request->validate([
            'name' => [Rule::requiredIf($isFieldRequired('name')), 'nullable', 'string', 'max:255'],
            'email' => [
                Rule::requiredIf($isFieldRequired('email') && ! $isFieldRequired('phone')),
                'nullable',
                'email',
                'max:255',
                Rule::unique('users', 'email'),
            ],
            'phone' => [
                Rule::requiredIf($isFieldRequired('phone') && ! $isFieldRequired('email')),
                'nullable',
                'string',
                'max:32',
                Rule::unique('users', 'phone'),
            ],
        ]);

        $email = $data['email'] ?? null;
        $phone = $data['phone'] ?? null;

        if ($email === null && $phone === null) {
            throw ValidationException::withMessages([
                'email' => __('Provide an email address or phone number.'),
                'phone' => __('Provide an email address or phone number.'),
            ]);
        }

        return [
            'name' => (string) $data['name'],
            'email' => $email,
            'phone' => $phone,
            'password' => Str::password(12, symbols: false),
        ];
    }

    /**
     * @param  array{name: string, email: ?string, phone: ?string, password: string}  $data
     */
    public function createLandlordUser(array $data, ?int $agentUserId = null): User
    {
        $attributes = [
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'password' => Hash::make($data['password']),
            'property_portal_role' => 'landlord',
            'email_verified_at' => $data['email'] !== null ? now() : null,
        ];

        if ($agentUserId !== null && Schema::hasColumn('users', 'agent_user_id')) {
            $attributes['agent_user_id'] = $agentUserId;
        }

        $landlord = User::query()->create($attributes);

        if ($agentUserId !== null) {
            $this->recordAgentOnboarding($landlord, $agentUserId);
        }

        return $landlord;
    }

    /**
     * @param  array{
     *     legacy_landlord_code?: ?string,
     *     id_number?: ?string,
     *     kra_pin?: ?string,
     *     address_line?: ?string
     * }  $data
     */
    public function syncLandlordProfile(User $landlord, array $data): void
    {
        if (! Schema::hasTable('pm_landlord_portal_profiles')) {
            return;
        }

        $profile = \App\Models\PmLandlordPortalProfile::forUser($landlord);
        $payload = [];

        foreach (['legacy_landlord_code', 'id_number', 'kra_pin', 'address_line'] as $key) {
            if (! array_key_exists($key, $data)) {
                continue;
            }
            $value = trim((string) ($data[$key] ?? ''));
            $payload[$key] = $value !== '' ? $value : null;
        }

        if ($payload !== []) {
            $profile->update($payload);
        }
    }

    public function recordAgentOnboarding(User $landlord, int $agentUserId): void
    {
        if (! Schema::hasTable('pm_portal_actions')) {
            return;
        }

        $exists = PmPortalAction::query()
            ->where('user_id', $agentUserId)
            ->where('action_key', LandlordWorkspaceScope::ONBOARD_ACTION_KEY)
            ->where('portal_role', 'agent')
            ->where('context->landlord_user_id', (int) $landlord->id)
            ->exists();

        if ($exists) {
            return;
        }

        PmPortalAction::query()->create([
            'user_id' => $agentUserId,
            'portal_role' => 'agent',
            'action_key' => LandlordWorkspaceScope::ONBOARD_ACTION_KEY,
            'context' => ['landlord_user_id' => (int) $landlord->id],
        ]);
    }

    public function stampAgentOwnership(User $landlord, int $agentUserId): void
    {
        if (
            Schema::hasColumn('users', 'agent_user_id')
            && (int) ($landlord->agent_user_id ?? 0) !== $agentUserId
        ) {
            $landlord->forceFill(['agent_user_id' => $agentUserId])->save();
        }

        $this->recordAgentOnboarding($landlord->fresh(), $agentUserId);
    }

    /**
     * Remove agent-workspace visibility for a landlord (super-admin only).
     *
     * @param  int|null  $agentUserId  When set, only remove links/audit rows for this agent.
     */
    public function releaseAgentOwnership(User $landlord, ?int $agentUserId = null, bool $detachProperties = true): void
    {
        if (
            Schema::hasColumn('users', 'agent_user_id')
            && ($agentUserId === null || (int) ($landlord->agent_user_id ?? 0) === $agentUserId)
        ) {
            $landlord->forceFill(['agent_user_id' => null])->save();
        }

        if (Schema::hasTable('pm_portal_actions')) {
            $query = PmPortalAction::query()
                ->where('action_key', LandlordWorkspaceScope::ONBOARD_ACTION_KEY)
                ->where('portal_role', 'agent')
                ->where('context->landlord_user_id', (int) $landlord->id);

            if ($agentUserId !== null) {
                $query->where('user_id', $agentUserId);
            }

            $query->delete();
        }

        if (
            $detachProperties
            && Schema::hasTable('property_landlord')
            && Schema::hasTable('properties')
            && Schema::hasColumn('properties', 'agent_user_id')
        ) {
            $propertyIds = DB::table('property_landlord as pl')
                ->join('properties as p', 'p.id', '=', 'pl.property_id')
                ->where('pl.user_id', $landlord->id)
                ->when($agentUserId !== null, fn ($q) => $q->where('p.agent_user_id', $agentUserId))
                ->pluck('pl.property_id');

            if ($propertyIds->isNotEmpty()) {
                DB::table('property_landlord')
                    ->where('user_id', $landlord->id)
                    ->whereIn('property_id', $propertyIds->all())
                    ->delete();
            }
        }
    }

    /**
     * @return array{email_sent: bool, sms_sent: bool, summary: string}
     */
    public function deliverCredentials(User $landlord, string $plainPassword, ?int $agentUserId = null): array
    {
        $email = trim((string) ($landlord->email ?? ''));
        $phone = trim((string) ($landlord->phone ?? ''));
        $loginUrl = route('property.landlord.login');
        $homeUrl = route('property.landlord.portfolio');

        $emailSent = false;
        $smsSent = false;

        if ($email !== '') {
            try {
                Mail::to($email)->send(new LandlordPortalCredentialsMail(
                    landlordName: $landlord->name,
                    email: $email,
                    phone: $phone !== '' ? $phone : null,
                    plainPassword: $plainPassword,
                    loginUrl: $loginUrl,
                    landlordHomeUrl: $homeUrl,
                ));
                $emailSent = true;
            } catch (Throwable) {
                $emailSent = false;
            }
        }

        if ($phone !== '') {
            $loginHint = $email !== ''
                ? __('email :email or phone :phone', ['email' => $email, 'phone' => $phone])
                : __('phone number :phone', ['phone' => $phone]);

            $message = __('Hello :name, your landlord portal login is ready. Sign in at :url using your :loginHint. Temporary password: :password', [
                'name' => $landlord->name,
                'url' => $loginUrl,
                'loginHint' => $loginHint,
                'password' => $plainPassword,
            ]);

            try {
                $result = $this->bulkSms->sendNow(
                    message: $message,
                    phones: [$phone],
                    userId: $agentUserId,
                    module: 'property',
                    verifyBalance: false,
                );
                $smsSent = (bool) ($result['ok'] ?? false);
            } catch (Throwable) {
                $smsSent = false;
            }
        }

        $summary = match (true) {
            $emailSent && $smsSent => __('Credentials sent by email and SMS.'),
            $emailSent => __('Credentials email sent.'),
            $smsSent => __('Credentials sent by SMS.'),
            $email !== '' && $phone !== '' => __('Landlord created, but credentials could not be sent (check mail and SMS settings).'),
            $email !== '' => __('Landlord created, but the credential email was not sent (check mail settings).'),
            $phone !== '' => __('Landlord created, but the credential SMS was not sent (check SMS settings). Share the password manually.'),
            default => __('Landlord created. Share portal credentials manually.'),
        };

        return [
            'email_sent' => $emailSent,
            'sms_sent' => $smsSent,
            'summary' => $summary,
        ];
    }

    public function findLandlordByLogin(string $login): ?User
    {
        $login = trim($login);
        if ($login === '') {
            return null;
        }

        if (str_contains($login, '@')) {
            return User::query()
                ->whereRaw('LOWER(email) = ?', [strtolower($login)])
                ->first();
        }

        $normalized = $this->normalizer->normalizePhone($login);
        if ($normalized === '') {
            return null;
        }

        return User::query()
            ->where('phone', $normalized)
            ->first();
    }

    /**
     * @return array{email: ?string, phone: ?string}
     */
    public function resolveContactChannels(User $landlord): array
    {
        $email = trim((string) ($landlord->email ?? ''));

        return [
            'email' => $email !== '' ? $email : null,
            'phone' => trim((string) ($landlord->phone ?? '')) ?: null,
        ];
    }
}
