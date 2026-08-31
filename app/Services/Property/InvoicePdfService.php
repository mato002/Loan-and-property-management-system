<?php

namespace App\Services\Property;

use App\Models\PmInvoice;
use App\Models\PropertyPortalSetting;
use Dompdf\Dompdf;
use Dompdf\Options;

class InvoicePdfService
{
    public function buildBinary(PmInvoice $invoice): string
    {
        $invoice->loadMissing(['tenant', 'unit.property', 'items']);
        $html = view('property.agent.revenue.invoice_print', $this->printViewData($invoice))->render();

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return (string) $dompdf->output();
    }

    /**
     * @return array{invoice: PmInvoice, branding: array<string, mixed>, payments: array<string, string>}
     */
    public function printViewData(PmInvoice $invoice): array
    {
        return [
            'invoice' => $invoice,
            'branding' => $this->branding(),
            'payments' => $this->paymentInstructions(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function branding(): array
    {
        $b = PropertyPortalSetting::query()->where('key', 'branding')->value('value');
        $decoded = is_string($b) ? json_decode($b, true) : (is_array($b) ? $b : []);

        $defaults = [
            'company_name' => PropertyPortalSetting::getValue('company_name', 'Property Manager'),
            'address' => '',
            'phone' => '',
            'email' => PropertyPortalSetting::getValue('contact_email_primary', ''),
            'logo_url' => PropertyPortalSetting::getValue('company_logo_url', ''),
            'colour' => '#0f766e',
            'footer_note' => 'Thank you for your business.',
        ];
        $merged = array_merge($defaults, is_array($decoded) ? $decoded : []);
        if (trim((string) ($merged['logo_url'] ?? '')) === '') {
            $merged['logo_url'] = PropertyPortalSetting::getValue('company_logo_url', '');
        }

        return $merged;
    }

    /**
     * @return array<string, string>
     */
    public function paymentInstructions(): array
    {
        return [
            'mpesa_shortcode' => trim((string) PropertyPortalSetting::getValue('mpesa_shortcode', '')),
            'trust_bank_name' => trim((string) PropertyPortalSetting::getValue('trust_bank_name', '')),
            'trust_account_number' => trim((string) PropertyPortalSetting::getValue('trust_account_number', '')),
            'trust_account_label' => trim((string) PropertyPortalSetting::getValue('trust_account_label', '')),
            'payments_notes' => trim((string) PropertyPortalSetting::getValue('payments_notes', '')),
            'rules_notes' => trim((string) PropertyPortalSetting::getValue('rules_notes', '')),
            'late_fee_percent' => trim((string) PropertyPortalSetting::getValue('rules_late_fee_percent', '')),
            'grace_days' => trim((string) PropertyPortalSetting::getValue('rules_grace_days', '')),
        ];
    }
}
