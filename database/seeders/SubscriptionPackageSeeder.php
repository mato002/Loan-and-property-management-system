<?php

namespace Database\Seeders;

use App\Models\SubscriptionPackage;
use Illuminate\Database\Seeder;

class SubscriptionPackageSeeder extends Seeder
{
    public function run(): void
    {
        $packages = [
            [
                'name' => 'Starter',
                'description' => 'Perfect for new property managers with small portfolios',
                'min_units' => 1,
                'max_units' => 50,
                'monthly_price_ksh' => 2500.00,
                'annual_price_ksh' => 27000.00, // 10% discount
                'is_active' => true,
                'sort_order' => 1,
                'features' => [
                    'Up to 50 property units',
                    'Basic property management',
                    'Tenant management',
                    'Rent collection tracking',
                    'Basic reporting',
                    'Email support'
                ]
            ],
            [
                'name' => 'Professional',
                'description' => 'Ideal for growing property management businesses',
                'min_units' => 51,
                'max_units' => 150,
                'monthly_price_ksh' => 6500.00,
                'annual_price_ksh' => 70200.00, // 10% discount
                'is_active' => true,
                'sort_order' => 2,
                'features' => [
                    'Up to 150 property units',
                    'Advanced property management',
                    'Tenant portal access',
                    'Automated rent reminders',
                    'Financial reporting',
                    'Maintenance tracking',
                    'Document management',
                    'Priority email support'
                ]
            ],
            [
                'name' => 'Business',
                'description' => 'Comprehensive solution for established property managers',
                'min_units' => 151,
                'max_units' => 300,
                'monthly_price_ksh' => 12000.00,
                'annual_price_ksh' => 129600.00, // 10% discount
                'is_active' => true,
                'sort_order' => 3,
                'features' => [
                    'Up to 300 property units',
                    'All Professional features',
                    'Multi-agent support',
                    'Advanced analytics',
                    'Custom branding',
                    'API access',
                    'Phone & email support',
                    'Monthly training sessions'
                ]
            ],
            [
                'name' => 'Enterprise',
                'description' => 'Full-featured solution for large property management companies',
                'min_units' => 301,
                'max_units' => null, // Unlimited
                'monthly_price_ksh' => 20000.00,
                'annual_price_ksh' => 216000.00, // 10% discount
                'is_active' => true,
                'sort_order' => 4,
                'features' => [
                    'Unlimited property units',
                    'All Business features',
                    'Dedicated account manager',
                    'Custom integrations',
                    'White-label options',
                    'On-premise deployment option',
                    '24/7 phone support',
                    'Quarterly business reviews'
                ]
            ]
        ];

        foreach ($packages as $package) {
            SubscriptionPackage::create($package);
        }
    }
}
