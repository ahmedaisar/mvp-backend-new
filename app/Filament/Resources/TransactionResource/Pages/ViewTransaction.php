<?php

namespace App\Filament\Resources\TransactionResource\Pages;

use App\Filament\Resources\TransactionResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewTransaction extends ViewRecord
{
    protected static string $resource = TransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\DeleteAction::make(),
            
            Actions\Action::make('print_receipt')
                ->label('Print Receipt')
                ->icon('heroicon-m-printer')
                ->color('gray')
                ->url(fn (): string => route('transactions.receipt', $this->record))
                ->openUrlInNewTab(),
                
            Actions\Action::make('download_receipt')
                ->label('Download Receipt')
                ->icon('heroicon-m-arrow-down-tray')
                ->color('info')
                ->url(fn (): string => route('transactions.receipt.download', $this->record)),
        ];
    }
}
