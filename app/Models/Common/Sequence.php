<?php

declare(strict_types=1);

namespace App\Models\Common;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Sequence extends Model
{
    use BelongsToTenant;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'type',
        'year',
        'last_number',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'last_number' => 'integer',
        ];
    }
}
