<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Billing\BillingPeriod;
use App\Enums\Billing\FeatureKey;
use App\Models\Billing\Feature;
use App\Models\Billing\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $basic = Plan::query()->updateOrCreate(
            ['slug' => 'basic'],
            [
                'name' => 'Basic',
                'price' => '0.00',
                'billing_period' => BillingPeriod::Monthly,
                'is_active' => true,
                'sort_order' => 1,
            ]
        );

        $pro = Plan::query()->updateOrCreate(
            ['slug' => 'pro'],
            [
                'name' => 'Pro',
                'price' => '0.00',
                'billing_period' => BillingPeriod::Monthly,
                'is_active' => true,
                'sort_order' => 2,
            ]
        );

        $features = Feature::query()->get()->keyBy(fn (Feature $f): string => $f->key->value);

        $basic->features()->sync([
            $features[FeatureKey::Invoicing->value]->id => ['value' => null, 'expiry_behavior' => null],
            $features[FeatureKey::Inventory->value]->id => ['value' => null, 'expiry_behavior' => null],
            $features[FeatureKey::DataExport->value]->id => ['value' => null, 'expiry_behavior' => null],
            $features[FeatureKey::MaxUsers->value]->id => ['value' => 3, 'expiry_behavior' => null],
            $features[FeatureKey::MaxProducts->value]->id => ['value' => 500, 'expiry_behavior' => null],
        ]);

        $pro->features()->sync([
            $features[FeatureKey::Invoicing->value]->id => ['value' => null, 'expiry_behavior' => null],
            $features[FeatureKey::Inventory->value]->id => ['value' => null, 'expiry_behavior' => null],
            $features[FeatureKey::DataExport->value]->id => ['value' => null, 'expiry_behavior' => null],
            $features[FeatureKey::MultiWarehouse->value]->id => ['value' => null, 'expiry_behavior' => null],
            $features[FeatureKey::AdvancedReports->value]->id => ['value' => null, 'expiry_behavior' => null],
            $features[FeatureKey::BarcodePrinting->value]->id => ['value' => null, 'expiry_behavior' => null],
            $features[FeatureKey::MaxUsers->value]->id => ['value' => 15, 'expiry_behavior' => null],
            $features[FeatureKey::MaxProducts->value]->id => ['value' => null, 'expiry_behavior' => null],
        ]);
    }
}
