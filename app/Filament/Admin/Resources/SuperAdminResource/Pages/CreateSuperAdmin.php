<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\SuperAdminResource\Pages;

use App\Filament\Admin\Resources\SuperAdminResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSuperAdmin extends CreateRecord
{
    protected static string $resource = SuperAdminResource::class;
}
