<?php

declare(strict_types=1);

namespace App\Enums\Billing;

enum FeatureKey: string
{
    case Invoicing = 'invoicing';
    case Inventory = 'inventory';
    case DataExport = 'data_export';
    case MultiWarehouse = 'multi_warehouse';
    case AdvancedReports = 'advanced_reports';
    case BarcodePrinting = 'barcode_printing';
    case MaxUsers = 'max_users';
    case MaxProducts = 'max_products';

    public function type(): FeatureType
    {
        return match ($this) {
            self::MaxUsers, self::MaxProducts => FeatureType::Limit,
            default => FeatureType::Boolean,
        };
    }

    public function defaultExpiryBehavior(): ExpiryBehavior
    {
        return match ($this) {
            self::Invoicing, self::Inventory => ExpiryBehavior::ReadOnly,
            self::DataExport, self::MaxUsers, self::MaxProducts => ExpiryBehavior::Keep,
            self::MultiWarehouse, self::AdvancedReports, self::BarcodePrinting => ExpiryBehavior::Lock,
        };
    }

    public function label(): string
    {
        return __('billing.feature.' . $this->value);
    }
}
