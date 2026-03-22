<?php

namespace App\Http\Controllers\Property\Agent;

use App\Http\Controllers\Controller;
use App\Models\PropertyPortalSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PropertySettingsStoreWebController extends Controller
{
    public function commission(): View
    {
        return view('property.agent.settings.commission', [
            'defaultPercent' => PropertyPortalSetting::getValue('commission_default_percent', ''),
            'notes' => PropertyPortalSetting::getValue('commission_notes', ''),
        ]);
    }

    public function storeCommission(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'commission_default_percent' => ['nullable', 'string', 'max:32'],
            'commission_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        PropertyPortalSetting::setValue('commission_default_percent', $data['commission_default_percent'] ?? '');
        PropertyPortalSetting::setValue('commission_notes', $data['commission_notes'] ?? '');

        return back()->with('success', __('Commission settings saved.'));
    }

    public function payments(): View
    {
        return view('property.agent.settings.payments', [
            'shortcode' => PropertyPortalSetting::getValue('mpesa_shortcode', ''),
            'consumerKey' => PropertyPortalSetting::getValue('mpesa_consumer_key', ''),
            'callbackUrl' => PropertyPortalSetting::getValue('mpesa_callback_url', ''),
            'notes' => PropertyPortalSetting::getValue('payments_notes', ''),
            'trustAccountLabel' => PropertyPortalSetting::getValue('trust_account_label', ''),
            'trustAccountNumber' => PropertyPortalSetting::getValue('trust_account_number', ''),
            'trustBankName' => PropertyPortalSetting::getValue('trust_bank_name', ''),
            'hasConsumerSecret' => (bool) strlen((string) PropertyPortalSetting::getValue('mpesa_consumer_secret', '')),
            'hasPasskey' => (bool) strlen((string) PropertyPortalSetting::getValue('mpesa_passkey', '')),
        ]);
    }

    public function storePayments(Request $request): RedirectResponse
    {
        if ($request->boolean('save_trust_account')) {
            $data = $request->validate([
                'trust_account_label' => ['nullable', 'string', 'max:128'],
                'trust_account_number' => ['nullable', 'string', 'max:64'],
                'trust_bank_name' => ['nullable', 'string', 'max:128'],
            ]);

            PropertyPortalSetting::setValue('trust_account_label', $data['trust_account_label'] ?? '');
            PropertyPortalSetting::setValue('trust_account_number', $data['trust_account_number'] ?? '');
            PropertyPortalSetting::setValue('trust_bank_name', $data['trust_bank_name'] ?? '');

            return back()->with('success', __('Trust account details saved.'));
        }

        $data = $request->validate([
            'mpesa_shortcode' => ['nullable', 'string', 'max:64'],
            'mpesa_consumer_key' => ['nullable', 'string', 'max:255'],
            'mpesa_consumer_secret' => ['nullable', 'string', 'max:255'],
            'mpesa_passkey' => ['nullable', 'string', 'max:255'],
            'mpesa_callback_url' => ['nullable', 'string', 'max:500'],
            'payments_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $secretKeys = ['mpesa_consumer_secret', 'mpesa_passkey'];

        foreach ($data as $key => $value) {
            if (in_array($key, $secretKeys, true)) {
                if ($request->filled($key)) {
                    PropertyPortalSetting::setValue($key, (string) $value);
                }

                continue;
            }

            PropertyPortalSetting::setValue($key, $value ?? '');
        }

        return back()->with('success', __('Payment settings saved (store secrets carefully — encryption not enabled in this build).'));
    }

    public function rules(): View
    {
        return view('property.agent.settings.rules', [
            'graceDays' => PropertyPortalSetting::getValue('rules_grace_days', '3'),
            'lateFeePercent' => PropertyPortalSetting::getValue('rules_late_fee_percent', '0'),
            'notes' => PropertyPortalSetting::getValue('rules_notes', ''),
        ]);
    }

    public function storeRules(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'rules_grace_days' => ['nullable', 'string', 'max:16'],
            'rules_late_fee_percent' => ['nullable', 'string', 'max:32'],
            'rules_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        PropertyPortalSetting::setValue('rules_grace_days', $data['rules_grace_days'] ?? '');
        PropertyPortalSetting::setValue('rules_late_fee_percent', $data['rules_late_fee_percent'] ?? '');
        PropertyPortalSetting::setValue('rules_notes', $data['rules_notes'] ?? '');

        return back()->with('success', __('Rules saved — wire these values into invoice generation when you automate penalties.'));
    }
}
