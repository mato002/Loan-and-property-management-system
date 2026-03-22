<?php

namespace App\Http\Controllers\Property\Agent;

use App\Http\Controllers\Controller;
use App\Models\PmInvoice;
use App\Models\PropertyUnit;
use App\Services\Property\PropertyDashboardStats;
use App\Services\Property\PropertyMoney;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PropertyAdvisorWebController extends Controller
{
    public function show(): View
    {
        return view('property.agent.advisor', [
            'lastAnswer' => session('advisor_answer'),
        ]);
    }

    public function ask(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'question' => ['required', 'string', 'max:2000'],
        ]);

        $q = mb_strtolower($data['question']);
        $answer = $this->matchAnswer($q);

        return back()->with('advisor_answer', $answer);
    }

    private function matchAnswer(string $q): string
    {
        if (str_contains($q, 'arrear') || str_contains($q, 'owe') || str_contains($q, 'balance')) {
            $out = PropertyDashboardStats::outstandingBalance();

            return 'Outstanding invoice balance across the portfolio is approximately '.PropertyMoney::kes($out).'. Open Revenue → Arrears for tenant detail.';
        }

        if (str_contains($q, 'vacant') || str_contains($q, 'vacancy')) {
            $n = PropertyUnit::query()->where('status', PropertyUnit::STATUS_VACANT)->count();

            return "There are {$n} vacant unit(s). Use Properties → Occupancy or Listings → Vacant units to act on them.";
        }

        if (str_contains($q, 'publish') || str_contains($q, 'listing') || str_contains($q, 'discover')) {
            return 'Public pages come from vacant units with photos under Listings → Setup a listing or Vacant units → Photos & publish.';
        }

        if (str_contains($q, 'invoice')) {
            $c = PmInvoice::query()->count();

            return "The system holds {$c} invoice row(s). Use Revenue → Invoices & billing for issuance and Revenue → Payments for allocation.";
        }

        return 'Try asking about arrears, vacancy, listings, or invoices. Full LLM integration can be added when you configure an API key in `.env`; this screen uses rule-based answers from your live aggregates.';
    }
}
