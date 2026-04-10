<?php

namespace App\Http\Middleware;

use App\Models\AgentSubscription;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class CheckAgentSubscription
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        // Skip check for non-agents or super admins
        if (!$user || $user->is_super_admin || $user->property_portal_role !== 'agent') {
            return $next($request);
        }

        // Skip check if subscription tables don't exist yet
        if (!Schema::hasTable('agent_subscriptions') || !Schema::hasTable('subscription_packages')) {
            return $next($request);
        }

        // Get active subscription for the agent
        $activeSubscription = AgentSubscription::getActiveSubscriptionForUser($user->id);

        if (!$activeSubscription) {
            // Redirect to subscription page or show subscription required message
            return redirect()->route('subscription.required')->with('error', 'An active subscription is required to access this feature.');
        }

        // Check if subscription has expired
        if ($activeSubscription->isExpired()) {
            return redirect()->route('subscription.expired')->with('error', 'Your subscription has expired. Please renew to continue using the service.');
        }

        // Add subscription data to request for potential use in controllers
        $request->merge([
            'active_subscription' => $activeSubscription,
            'subscription_package' => $activeSubscription->subscriptionPackage
        ]);

        return $next($request);
    }
}
