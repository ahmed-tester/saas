<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\TenantResource\Pages;

use App\Filament\Admin\Resources\TenantResource;
use App\Models\Admin\SuperAdmin;
use App\Services\Tenancy\TenantProvisioner;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class CreateTenant extends CreateRecord
{
    protected static string $resource = TenantResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        /** @var SuperAdmin|null $actor */
        $actor = Auth::guard('admin')->user();

        $result = app(TenantProvisioner::class)->provision($data, $actor);

        Notification::make()
            ->title(__('admin.notifications.tenant_created'))
            ->body(__('admin.notifications.owner_credentials', [
                'email' => $result['owner']->email,
                'password' => $result['temporary_password'],
            ]))
            ->success()
            ->persistent()
            ->send();

        return $result['tenant'];
    }
}
