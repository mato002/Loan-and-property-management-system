<?php

namespace App\Http\Controllers\Property\Agent;

use App\Http\Controllers\Controller;
use App\Models\PmInvoice;
use App\Models\PmMaintenanceRequest;
use App\Models\PmMessageLog;
use App\Models\PmPayment;
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
            'lastQuestion' => session('advisor_question'),
            'history' => (array) session('advisor_history', []),
        ]);
    }

    public function ask(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'question' => ['required', 'string', 'max:2000'],
        ]);

        $q = mb_strtolower($data['question']);
        $answer = $this->matchAnswer($q);
        $history = (array) $request->session()->get('advisor_history', []);
        $history[] = [
            'q' => $data['question'],
            'a' => $answer,
            'at' => now()->format('Y-m-d H:i'),
        ];
        $history = array_slice($history, -8);

        $request->session()->put('advisor_question', $data['question']);
        $request->session()->put('advisor_answer', $answer);
        $request->session()->put('advisor_history', $history);

        return back();
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

        if (str_contains($q, 'unmatched') || str_contains($q, 'equity') || str_contains($q, 'mpesa')) {
            $unmatched = PmPayment::query()->where('status', 'unmatched')->count();

            return "There are {$unmatched} unmatched payment(s). Use Revenue → Equity → Unmatched to assign them to tenants.";
        }

        if (str_contains($q, 'maintenance') || str_contains($q, 'repair') || str_contains($q, 'issue')) {
            $open = PmMaintenanceRequest::query()
                ->whereIn('status', ['open', 'pending', 'in_progress'])
                ->count();

            return "There are {$open} open/pending maintenance request(s). Use Maintenance → Requests to assign and track progress.";
        }

        if (str_contains($q, 'failed message') || str_contains($q, 'failed sms') || str_contains($q, 'failed email') || str_contains($q, 'communication')) {
            $failed = PmMessageLog::query()->where('delivery_status', 'failed')->count();

            return "You currently have {$failed} failed communication log(s). Open Communications → SMS / email and filter Status = FAILED for details.";
        }

        if (str_contains($q, 'rent collection') || str_contains($q, 'report') || str_contains($q, 'landlord report')) {
            return 'For rent collection analytics, open Reports → Landlord → Rent collection. You can filter dates, paginate, and export CSV/XLS/PDF from that page.';
        }

        return 'Try asking about arrears, vacancy, listings, or invoices. Full LLM integration can be added when you configure an API key in `.env`; this screen uses rule-based answers from your live aggregates.';
    }
}
