<?php

namespace App\Http\Middleware;

use App\Models\AgentSubscription;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class SubscriptionGracePeriod
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

        // Get active subscription for agent
        $activeSubscription = AgentSubscription::getActiveSubscriptionForUser($user->id);

        if (!$activeSubscription) {
            // No subscription at all
            return redirect()->route('subscription.required')
                ->with('error', 'A subscription is required to access this feature. Please choose a package to continue.')
                ->with('subscription_type', 'none');
        }

        // Check if subscription is expiring soon (within 7 days)
        if ($activeSubscription->ends_at && $activeSubscription->ends_at->diffInDays(now()) <= 7) {
            $daysLeft = $activeSubscription->ends_at->diffInDays(now);
            
            if ($daysLeft <= 0) {
                // Expired
                return redirect()->route('subscription.expired')
                    ->with('error', 'Your subscription has expired. Please renew to continue using the service.')
                    ->with('subscription_type', 'expired')
                    ->with('expired_subscription', $activeSubscription);
            } else {
                // Expiring soon
                return redirect()->route('subscription.renewal')
                    ->with('warning', "Your subscription expires in {$daysLeft} day(s). Please renew to avoid service interruption.")
                    ->with('subscription_type', 'expiring')
                    ->with('expiring_subscription', $activeSubscription);
            }
        }

        // Check if payment is missing (for active subscriptions without payment recorded)
        if (!$activeSubscription->price_paid || $activeSubscription->payment_method === null) {
            return redirect()->route('subscription.payment')
                ->with('warning', 'Payment information is required for your subscription. Please complete payment to continue.')
                ->with('subscription_type', 'payment_pending')
                ->with('pending_payment_subscription', $activeSubscription);
        }

        // Add subscription data to request for potential use in controllers
        $request->merge([
            'active_subscription' => $activeSubscription,
            'subscription_package' => $activeSubscription->subscriptionPackage
        ]);

        return $next($request);
    }
}
