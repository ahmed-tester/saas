<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\SuperAdminResource\Pages;

use App\Filament\Admin\Resources\SuperAdminResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSuperAdmins extends ListRecords
{
    protected static string $resource = SuperAdminResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
