<?php

declare(strict_types=1);

namespace App\Models\Billing;

use App\Enums\Billing\SubscriptionEventType;
use App\Models\Admin\SuperAdmin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionEvent extends Model
{
    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'subscription_id',
        'type',
        'performed_by',
        'note',
        'meta',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => SubscriptionEventType::class,
            'meta' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class, 'subscription_id');
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(SuperAdmin::class, 'performed_by');
    }
}
