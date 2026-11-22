<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\Tenancy\Domain;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidTenantSlug implements ValidationRule
{
    public function __construct(
        private readonly ?int $ignoreDomainId = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail(__('tenancy.slug_invalid'));

            return;
        }

        $slug = strtolower(trim($value));

        if (! preg_match('/^[a-z0-9](?:[a-z0-9-]{1,30}[a-z0-9])?$/', $slug)) {
            $fail(__('tenancy.slug_invalid'));

            return;
        }

        $reserved = array_map(
            'strtolower',
            (array) config('billing.reserved_slugs', [])
        );

        if (in_array($slug, $reserved, true)) {
            $fail(__('tenancy.slug_reserved'));

            return;
        }

        $exists = Domain::query()
            ->where('domain', $slug)
            ->when($this->ignoreDomainId !== null, fn ($q) => $q->where('id', '!=', $this->ignoreDomainId))
            ->exists();

        if ($exists) {
            $fail(__('tenancy.slug_taken'));
        }
    }
}
