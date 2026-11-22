<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Billing\FeatureKey;
use App\Models\Billing\Feature;
use Illuminate\Database\Seeder;

class FeatureSeeder extends Seeder
{
    public function run(): void
    {
        foreach (FeatureKey::cases() as $featureKey) {
            Feature::query()->updateOrCreate(
                ['key' => $featureKey->value],
                [
                    'name' => $featureKey->label(),
                    'type' => $featureKey->type(),
                    'expiry_behavior' => $featureKey->defaultExpiryBehavior(),
                ]
            );
        }
    }
}
