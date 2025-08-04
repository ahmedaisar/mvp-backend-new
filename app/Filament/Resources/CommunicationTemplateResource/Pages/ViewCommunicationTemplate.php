<?php

namespace App\Filament\Resources\CommunicationTemplateResource\Pages;

use App\Filament\Resources\CommunicationTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewCommunicationTemplate extends ViewRecord
{
    protected static string $resource = CommunicationTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
