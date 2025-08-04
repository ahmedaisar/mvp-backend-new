<?php

namespace App\Filament\Resources\AuditLogResource\Pages;

use App\Filament\Resources\AuditLogResource;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewAuditLog extends ViewRecord
{
    protected static string $resource = AuditLogResource::class;

    // No header actions since audit logs should be read-only
    protected function getHeaderActions(): array
    {
        return [
            // Only viewing and potentially printing/exporting individual logs
        ];
    }
}
