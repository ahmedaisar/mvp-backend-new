<?php

namespace App\Filament\Resources\ResortManagerResource\Pages;

use App\Filament\Resources\ResortManagerResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditResortManager extends EditRecord
{
    protected static string $resource = ResortManagerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
