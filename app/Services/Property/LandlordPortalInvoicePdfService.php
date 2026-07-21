<?php

namespace App\Services\Property;

use App\Models\PmInvoice;
use App\Models\PropertyPortalSetting;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class LandlordPortalInvoicePdfService
{
    public function stream(PmInvoice $invoice, string $disposition = 'attachment'): StreamedResponse|Response
    {
        $invoice->loadMissing(['tenant', 'unit.property', 'items']);
        $html = view('property.agent.revenue.invoice_print', [
            'invoice' => $invoice,
            'branding' => $this->branding(),
            'payments' => $this->paymentInstructions(),
        ])->render();

        try {
            $options = new Options();
            $options->set('isRemoteEnabled', true);
            $options->set('defaultFont', 'DejaVu Sans');
            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            $binary = $dompdf->output();
        } catch (\Throwable $e) {
            report($e);

            return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
        }

        $filename = 'invoice-'.$invoice->invoice_no.'.pdf';

        return response()->streamDownload(function () use ($binary) {
            echo $binary;
        }, $filename, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition.'; filename="'.$filename.'"',
        ]);
    }

    /** @return array<string, mixed> */
    private function branding(): array
    {
        return [
            'company_name' => PropertyPortalSetting::getValue('company_name', config('app.name')),
            'logo_url' => PropertyPortalSetting::getValue('company_logo_url', ''),
            'address' => PropertyPortalSetting::getValue('contact_address', ''),
            'phone' => PropertyPortalSetting::getValue('contact_phone', ''),
            'email' => PropertyPortalSetting::getValue('contact_email_primary', ''),
        ];
    }

    /** @return array<string, string> */
    private function paymentInstructions(): array
    {
        return [
            'mpesa_paybill' => (string) PropertyPortalSetting::getValue('mpesa_paybill', ''),
            'mpesa_account' => (string) PropertyPortalSetting::getValue('mpesa_account', ''),
            'bank_name' => (string) PropertyPortalSetting::getValue('bank_name', ''),
            'bank_account' => (string) PropertyPortalSetting::getValue('bank_account', ''),
        ];
    }
}
