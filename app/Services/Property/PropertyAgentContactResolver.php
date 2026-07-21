<?php

namespace App\Services\Property;

use App\Models\PmInvoice;
use App\Models\PmTenant;
use App\Models\PropertyPortalSetting;
use App\Models\User;

class PropertyAgentContactResolver
{
    /**
     * @return array{name:string,phone:string,email:string}
     */
    public function resolveForInvoice(PmInvoice $invoice): array
    {
        $invoice->loadMissing([
            'unit.property.agentUser:id,name,phone,email',
            'tenant:id,agent_user_id',
        ]);

        $fromProperty = $this->fromUser($invoice->unit?->property?->agentUser);
        if ($fromProperty !== null) {
            return $fromProperty;
        }

        if ($invoice->tenant?->agent_user_id) {
            $tenantAgent = User::query()
                ->whereKey((int) $invoice->tenant->agent_user_id)
                ->first(['id', 'name', 'phone', 'email']);
            $fromTenantAgent = $this->fromUser($tenantAgent);
            if ($fromTenantAgent !== null) {
                return $fromTenantAgent;
            }
        }

        return $this->officeFallback();
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function mergeIntoContext(array $context, ?PmInvoice $invoice = null): array
    {
        if ($this->hasAgent($context)) {
            return $context;
        }

        if ($invoice !== null) {
            $context['agent'] = $this->resolveForInvoice($invoice);

            return $context;
        }

        $context['agent'] = $this->officeFallback();

        return $context;
    }

    public function formatSmsPhoneLine(?array $contact): string
    {
        $contact = $contact ?? $this->officeFallback();
        $phone = trim((string) ($contact['phone'] ?? ''));
        if ($phone === '') {
            return 'For assistance, contact the property office.';
        }

        return 'For assistance, call '.$phone.'.';
    }

    /**
     * @param  array{name?:string,phone?:string,email?:string}|null  $contact
     */
    public function formatSmsBlock(?array $contact): string
    {
        $contact = $contact ?? $this->officeFallback();
        $name = trim((string) ($contact['name'] ?? ''));
        $phone = trim((string) ($contact['phone'] ?? ''));
        $email = trim((string) ($contact['email'] ?? ''));

        $lines = ['Need help? Contact your property agent:'];
        if ($name !== '') {
            $lines[] = $name;
        }
        if ($phone !== '') {
            $lines[] = 'Phone: '.$phone;
        }
        if ($email !== '') {
            $lines[] = 'Email: '.$email;
        }

        if (count($lines) === 1) {
            return 'Need help? Contact the property office.';
        }

        return implode("\n", $lines);
    }

    /**
     * @return array{name:string,phone:string,email:string}
     */
    public function officeFallback(): array
    {
        $company = trim((string) PropertyPortalSetting::getValue('company_name', ''));
        $phone = trim((string) PropertyPortalSetting::getValue('contact_phone', ''));
        $email = trim((string) PropertyPortalSetting::getValue('contact_email_primary', ''));

        return [
            'name' => $company !== '' ? $company : 'Property office',
            'phone' => $phone,
            'email' => $email,
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function hasAgent(array $context): bool
    {
        $agent = (array) ($context['agent'] ?? []);

        return trim((string) ($agent['name'] ?? '')) !== ''
            || trim((string) ($agent['phone'] ?? '')) !== ''
            || trim((string) ($agent['email'] ?? '')) !== '';
    }

    /**
     * @return array{name:string,phone:string,email:string}|null
     */
    private function fromUser(?User $user): ?array
    {
        if (! $user) {
            return null;
        }

        $name = trim((string) ($user->name ?? ''));
        $phone = trim((string) ($user->phone ?? ''));
        $email = trim((string) ($user->email ?? ''));

        if ($name === '' && $phone === '' && $email === '') {
            return null;
        }

        return [
            'name' => $name !== '' ? $name : 'Property agent',
            'phone' => $phone,
            'email' => $email,
        ];
    }
}
