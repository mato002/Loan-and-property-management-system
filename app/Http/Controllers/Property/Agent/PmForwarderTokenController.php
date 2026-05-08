<?php

namespace App\Http\Controllers\Property\Agent;

use App\Http\Controllers\Controller;
use App\Models\PmForwarderToken;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class PmForwarderTokenController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $tokens = collect();
        if (Schema::hasTable('pm_forwarder_tokens')) {
            $tokens = PmForwarderToken::query()
                ->where('user_id', $user->id)
                ->orderByDesc('id')
                ->limit(20)
                ->get();
        }

        $webhookUrl = url('/webhooks/property/payments/sms-ingest');
        $activeTokens = $tokens->whereNull('revoked_at')->values();

        return view('property.agent.settings.my_forwarder', [
            'webhookUrl' => $webhookUrl,
            'tokens' => $tokens,
            'activeTokens' => $activeTokens,
            'tokensTableExists' => Schema::hasTable('pm_forwarder_tokens'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (! Schema::hasTable('pm_forwarder_tokens')) {
            return back()->withErrors([
                'forwarder' => 'Forwarder tokens table is missing. Run migrations first.',
            ]);
        }

        $data = $request->validate([
            'label' => ['nullable', 'string', 'max:80'],
        ]);

        $user = $request->user();

        // Loop until we mint a unique token. Collisions are astronomically
        // unlikely for 48-char random strings, but the unique index would
        // throw on duplicate so we retry safely.
        $attempts = 0;
        do {
            $candidate = PmForwarderToken::generateTokenString();
            $exists = PmForwarderToken::query()->where('token', $candidate)->exists();
            $attempts++;
        } while ($exists && $attempts < 5);

        if ($exists) {
            return back()->withErrors([
                'forwarder' => 'Could not generate a unique token. Please try again.',
            ]);
        }

        PmForwarderToken::query()->create([
            'user_id' => $user->id,
            'token' => $candidate,
            'label' => $data['label'] ?? null,
        ]);

        return back()->with('success', 'Forwarder token generated. Copy and configure it in your SMS forwarder app now.');
    }

    public function revoke(Request $request, PmForwarderToken $pmForwarderToken): RedirectResponse
    {
        if ((int) $pmForwarderToken->user_id !== (int) $request->user()->id) {
            abort(403);
        }

        $pmForwarderToken->update(['revoked_at' => now()]);

        return back()->with('success', 'Forwarder token revoked. Update your SMS forwarder with a new token to keep ingesting.');
    }

    /**
     * Diagnostic counts (matched / unmatched in the last 24h, last_used_at)
     * shown on the same page so an agent can confirm their forwarder is
     * working end-to-end. Self-scoped: never leaks across agents.
     */
    public function diagnostics(Request $request): View
    {
        $user = $request->user();
        $since = now()->subHours(24);

        $matched = 0;
        $unmatched = 0;
        $lastIngestAt = null;
        if (Schema::hasTable('pm_sms_ingests') && Schema::hasColumn('pm_sms_ingests', 'agent_user_id')) {
            $matched = (int) DB::table('pm_sms_ingests')
                ->where('agent_user_id', $user->id)
                ->where('match_status', 'matched')
                ->where('created_at', '>=', $since)
                ->count();
            $unmatched = (int) DB::table('pm_sms_ingests')
                ->where('agent_user_id', $user->id)
                ->where('match_status', 'unmatched')
                ->where('created_at', '>=', $since)
                ->count();
            $lastIngestAt = DB::table('pm_sms_ingests')
                ->where('agent_user_id', $user->id)
                ->max('created_at');
        }

        return view('property.agent.settings.my_forwarder_diagnostics', [
            'matched24h' => $matched,
            'unmatched24h' => $unmatched,
            'lastIngestAt' => $lastIngestAt,
        ]);
    }
}
