<?php

declare(strict_types=1);

namespace App\Services\Common;

use App\Exceptions\Tenancy\TenantContextMissingException;
use App\Models\Common\Sequence;
use Illuminate\Database\QueryException;
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

        return DB::transaction(function () use ($tenantId, $type, $resolvedYear): int {
            $sequence = Sequence::query()
                ->where('type', $type)
                ->where('year', $resolvedYear)
                ->lockForUpdate()
                ->first();

            if ($sequence === null) {
                try {
                    Sequence::query()->create([
                        'type' => $type,
                        'year' => $resolvedYear,
                        'last_number' => 0,
                    ]);
                } catch (QueryException) {
                    // Concurrent first-time creation for this (tenant_id, type, year)
                }

                $sequence = Sequence::query()
                    ->where('tenant_id', $tenantId)
                    ->where('type', $type)
                    ->where('year', $resolvedYear)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            $sequence->last_number = $sequence->last_number + 1;
            $sequence->save();

            return $sequence->last_number;
        });
    }
}
