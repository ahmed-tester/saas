<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\TenantResource\Pages;

use App\Filament\Admin\Resources\TenantResource;
use App\Models\Tenancy\Tenant;
use App\Services\Tenancy\UserService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditTenant extends EditRecord
{
    protected static string $resource = TenantResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('resetOwnerPassword')
                ->label(__('admin.actions.reset_owner_password'))
                ->color('warning')
                ->requiresConfirmation()
                ->action(function (): void {
                    /** @var Tenant $tenant */
                    $tenant = $this->getRecord();
                    $result = app(UserService::class)->resetOwnerPassword($tenant);

                    Notification::make()
                        ->title(__('admin.notifications.owner_password_reset'))
                        ->body(__('admin.notifications.owner_credentials', [
                            'email' => $result['user']->email,
                            'password' => $result['password'],
                        ]))
                        ->warning()
                        ->persistent()
                        ->send();
                }),
            DeleteAction::make()->requiresConfirmation(),
            RestoreAction::make(),
        ];
    }
}
