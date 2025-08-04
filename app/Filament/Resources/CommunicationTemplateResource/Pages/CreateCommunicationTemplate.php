<?php

namespace App\Filament\Resources\CommunicationTemplateResource\Pages;

use App\Filament\Resources\CommunicationTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateCommunicationTemplate extends CreateRecord
{
    protected static string $resource = CommunicationTemplateResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
