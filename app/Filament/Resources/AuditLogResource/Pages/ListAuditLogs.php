<?php

namespace App\Filament\Resources\AuditLogResource\Pages;

use App\Filament\Resources\AuditLogResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Pages\Concerns\ExposesTableToWidgets;

class ListAuditLogs extends ListRecords
{
    use ExposesTableToWidgets;

    protected static string $resource = AuditLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // No create action since audit logs are system-generated
            Actions\Action::make('export_all')
                ->label('Export All')
                ->icon('heroicon-m-arrow-down-tray')
                ->color('info')
                ->action(function (): void {
                    // Export logic would go here
                }),
        ];
    }
}
