<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\SuperAdminResource\Pages;

use App\Filament\Admin\Resources\SuperAdminResource;
use Filament\Resources\Pages\EditRecord;

class EditSuperAdmin extends EditRecord
{
    protected static string $resource = SuperAdminResource::class;
}
