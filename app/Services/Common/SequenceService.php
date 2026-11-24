<?php

declare(strict_types=1);

namespace App\Services\Common;

use App\Exceptions\Tenancy\TenantContextMissingException;
use App\Models\Common\Sequence;
use Illuminate\Support\Facades\DB;

class SequenceService
{
    public function next(string $type, ?int $year = null): int
    {
        if (! tenancy()->initialized) {
            throw TenantContextMissingException::forModel(Sequence::class);
        }

        $resolvedYear = $year ?? 0;
        $tenantId = (int) tenant()->getTenantKey();
        $now = now();

        Sequence::query()->insertOrIgnore([
            'tenant_id' => $tenantId,
            'type' => $type,
            'year' => $resolvedYear,
            'last_number' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return DB::transaction(function () use ($type, $resolvedYear): int {
            $sequence = Sequence::query()
                ->where('type', $type)
                ->where('year', $resolvedYear)
                ->lockForUpdate()
                ->firstOrFail();

            $sequence->last_number = $sequence->last_number + 1;
            $sequence->save();

            return $sequence->last_number;
        }, attempts: 3);
    }
}
