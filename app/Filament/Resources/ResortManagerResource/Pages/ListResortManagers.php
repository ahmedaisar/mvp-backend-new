<?php

namespace App\Filament\Resources\ResortManagerResource\Pages;

use App\Filament\Resources\ResortManagerResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListResortManagers extends ListRecords
{
    protected static string $resource = ResortManagerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
