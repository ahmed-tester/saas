<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Settings\SettingGroup;
use App\Enums\Settings\SettingType;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $table = 'settings';

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    public const CREATED_AT = null;

    public const UPDATED_AT = 'updated_at';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'key',
        'group',
        'type',
        'value',
        'updated_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'group' => SettingGroup::class,
            'type' => SettingType::class,
            'updated_at' => 'datetime',
        ];
    }
}
