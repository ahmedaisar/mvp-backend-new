<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TransactionResource\Pages;
use App\Models\Transaction;
use App\Models\Booking;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Database\Eloquent\Collection;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Colors\Color;

class TransactionResource extends Resource
{
    protected static ?string $model = Transaction::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Financial Reports';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'transaction_id';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Transaction Details')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('transaction_id')
                                    ->label('Transaction ID')
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(50)
                                    ->placeholder('TXN-20250803-001'),

                                Forms\Components\Select::make('booking_id')
                                    ->label('Booking')
                                    ->relationship('booking', 'booking_reference')
                                    ->searchable()
                                    ->preload()
                                    ->required(),

                                Forms\Components\Select::make('user_id')
                                    ->label('User')
                                    ->relationship('user', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required(),

                                Forms\Components\Select::make('payment_method')
                                    ->label('Payment Method')
                                    ->options([
                                        'credit_card' => 'Credit Card',
                                        'debit_card' => 'Debit Card',
                                        'bank_transfer' => 'Bank Transfer',
                                        'paypal' => 'PayPal',
                                        'stripe' => 'Stripe',
                                        'cash' => 'Cash',
                                        'check' => 'Check',
                                    ])
                                    ->required(),
                            ]),

                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('amount')
                                    ->label('Amount')
                                    ->numeric()
                                    ->required()
                                    ->prefix('$')
                                    ->step(0.01),

                                Forms\Components\TextInput::make('currency')
                                    ->label('Currency')
                                    ->default('USD')
                                    ->required()
                                    ->maxLength(3),

                                Forms\Components\Select::make('status')
                                    ->label('Status')
                                    ->options([
                                        'pending' => 'Pending',
                                        'processing' => 'Processing',
                                        'completed' => 'Completed',
                                        'failed' => 'Failed',
                                        'cancelled' => 'Cancelled',
                                        'refunded' => 'Refunded',
                                        'partially_refunded' => 'Partially Refunded',
                                    ])
                                    ->required()
                                    ->default('pending'),
                            ]),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('type')
                                    ->label('Transaction Type')
                                    ->options([
                                        'payment' => 'Payment',
                                        'refund' => 'Refund',
                                        'partial_refund' => 'Partial Refund',
                                        'deposit' => 'Deposit',
                                        'fee' => 'Fee',
                                        'adjustment' => 'Adjustment',
                                    ])
                                    ->required()
                                    ->default('payment'),

                                Forms\Components\TextInput::make('gateway_transaction_id')
                                    ->label('Gateway Transaction ID')
                                    ->maxLength(100)
                                    ->placeholder('External payment gateway ID'),
                            ]),
                    ]),

                Forms\Components\Section::make('Additional Information')
                    ->schema([
                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->rows(3)
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('gateway_response')
                            ->label('Gateway Response')
                            ->rows(4)
                            ->columnSpanFull()
                            ->hint('JSON response from payment gateway'),

                        Forms\Components\KeyValue::make('metadata')
                            ->label('Metadata')
                            ->keyLabel('Key')
                            ->valueLabel('Value')
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Timestamps')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\DateTimePicker::make('processed_at')
                                    ->label('Processed At'),

                                Forms\Components\DateTimePicker::make('refunded_at')
                                    ->label('Refunded At'),
                            ]),
                    ])
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('transaction_id')
                    ->label('Transaction ID')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('booking.booking_reference')
                    ->label('Booking')
                    ->searchable()
                    ->sortable()
                    ->url(fn (Transaction $record): string => route('filament.admin.resources.bookings.view', $record->booking)),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('User')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Amount')
                    ->money('USD')
                    ->sortable()
                    ->alignEnd(),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'warning' => 'pending',
                        'info' => 'processing',
                        'success' => 'completed',
                        'danger' => 'failed',
                        'gray' => 'cancelled',
                        'warning' => 'refunded',
                        'warning' => 'partially_refunded',
                    ]),

                Tables\Columns\BadgeColumn::make('type')
                    ->label('Type')
                    ->colors([
                        'success' => 'payment',
                        'warning' => 'refund',
                        'warning' => 'partial_refund',
                        'info' => 'deposit',
                        'gray' => 'fee',
                        'purple' => 'adjustment',
                    ]),

                Tables\Columns\TextColumn::make('payment_method')
                    ->label('Payment Method')
                    ->badge()
                    ->searchable(),

                Tables\Columns\TextColumn::make('processed_at')
                    ->label('Processed')
                    ->dateTime('M j, Y g:i A')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M j, Y g:i A')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->multiple()
                    ->options([
                        'pending' => 'Pending',
                        'processing' => 'Processing',
                        'completed' => 'Completed',
                        'failed' => 'Failed',
                        'cancelled' => 'Cancelled',
                        'refunded' => 'Refunded',
                        'partially_refunded' => 'Partially Refunded',
                    ]),

                Tables\Filters\SelectFilter::make('type')
                    ->multiple()
                    ->options([
                        'payment' => 'Payment',
                        'refund' => 'Refund',
                        'partial_refund' => 'Partial Refund',
                        'deposit' => 'Deposit',
                        'fee' => 'Fee',
                        'adjustment' => 'Adjustment',
                    ]),

                Tables\Filters\SelectFilter::make('payment_method')
                    ->multiple()
                    ->options([
                        'credit_card' => 'Credit Card',
                        'debit_card' => 'Debit Card',
                        'bank_transfer' => 'Bank Transfer',
                        'paypal' => 'PayPal',
                        'stripe' => 'Stripe',
                        'cash' => 'Cash',
                        'check' => 'Check',
                    ]),

                Tables\Filters\Filter::make('amount_range')
                    ->form([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('amount_from')
                                    ->label('Amount From')
                                    ->numeric()
                                    ->prefix('$'),
                                Forms\Components\TextInput::make('amount_to')
                                    ->label('Amount To')
                                    ->numeric()
                                    ->prefix('$'),
                            ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['amount_from'],
                                fn (Builder $query, $amount): Builder => $query->where('amount', '>=', $amount),
                            )
                            ->when(
                                $data['amount_to'],
                                fn (Builder $query, $amount): Builder => $query->where('amount', '<=', $amount),
                            );
                    }),

                Tables\Filters\Filter::make('date_range')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')
                            ->label('Created From'),
                        Forms\Components\DatePicker::make('created_until')
                            ->label('Created Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                
                Tables\Actions\Action::make('refund')
                    ->label('Refund')
                    ->icon('heroicon-m-arrow-uturn-left')
                    ->color('warning')
                    ->visible(fn (Transaction $record): bool => in_array($record->status, ['completed']))
                    ->form([
                        Forms\Components\TextInput::make('refund_amount')
                            ->label('Refund Amount')
                            ->numeric()
                            ->required()
                            ->prefix('$')
                            ->default(fn (Transaction $record) => $record->amount),
                        Forms\Components\Textarea::make('refund_reason')
                            ->label('Refund Reason')
                            ->required(),
                    ])
                    ->action(function (Transaction $record, array $data): void {
                        // Create refund transaction logic would go here
                        $record->update([
                            'status' => $data['refund_amount'] == $record->amount ? 'refunded' : 'partially_refunded',
                            'refunded_at' => now(),
                        ]);
                    })
                    ->requiresConfirmation(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    
                    Tables\Actions\BulkAction::make('mark_completed')
                        ->label('Mark as Completed')
                        ->icon('heroicon-m-check-circle')
                        ->color('success')
                        ->action(fn (Collection $records) => $records->each(fn (Transaction $record) => $record->update([
                            'status' => 'completed',
                            'processed_at' => now(),
                        ])))
                        ->requiresConfirmation(),
                        
                    Tables\Actions\BulkAction::make('mark_failed')
                        ->label('Mark as Failed')
                        ->icon('heroicon-m-x-circle')
                        ->color('danger')
                        ->action(fn (Collection $records) => $records->each(fn (Transaction $record) => $record->update(['status' => 'failed'])))
                        ->requiresConfirmation(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Transaction Information')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('transaction_id')
                                    ->label('Transaction ID')
                                    ->copyable(),
                                
                                Infolists\Components\TextEntry::make('status')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'pending' => 'warning',
                                        'processing' => 'info',
                                        'completed' => 'success',
                                        'failed' => 'danger',
                                        'cancelled' => 'gray',
                                        'refunded', 'partially_refunded' => 'warning',
                                        default => 'gray',
                                    }),

                                Infolists\Components\TextEntry::make('booking.booking_reference')
                                    ->label('Booking Reference')
                                    ->url(fn (Transaction $record): string => route('filament.admin.resources.bookings.view', $record->booking)),

                                Infolists\Components\TextEntry::make('user.name')
                                    ->label('User'),

                                Infolists\Components\TextEntry::make('amount')
                                    ->label('Amount')
                                    ->money('USD')
                                    ->weight(FontWeight::Bold),

                                Infolists\Components\TextEntry::make('type')
                                    ->badge(),
                            ]),
                    ]),

                Infolists\Components\Section::make('Payment Details')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('payment_method')
                                    ->label('Payment Method'),

                                Infolists\Components\TextEntry::make('gateway_transaction_id')
                                    ->label('Gateway Transaction ID')
                                    ->copyable(),

                                Infolists\Components\TextEntry::make('processed_at')
                                    ->label('Processed At')
                                    ->dateTime(),

                                Infolists\Components\TextEntry::make('refunded_at')
                                    ->label('Refunded At')
                                    ->dateTime(),
                            ]),
                    ]),

                Infolists\Components\Section::make('Description')
                    ->schema([
                        Infolists\Components\TextEntry::make('description')
                            ->columnSpanFull(),
                    ])
                    ->visible(fn (Transaction $record): bool => !empty($record->description)),

                Infolists\Components\Section::make('Gateway Response')
                    ->schema([
                        Infolists\Components\TextEntry::make('gateway_response')
                            ->columnSpanFull()
                            ->formatStateUsing(fn (string $state): string => json_encode(json_decode($state), JSON_PRETTY_PRINT)),
                    ])
                    ->visible(fn (Transaction $record): bool => !empty($record->gateway_response))
                    ->collapsed(),

                Infolists\Components\Section::make('Metadata')
                    ->schema([
                        Infolists\Components\KeyValueEntry::make('metadata')
                            ->columnSpanFull(),
                    ])
                    ->visible(fn (Transaction $record): bool => !empty($record->metadata))
                    ->collapsed(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTransactions::route('/'),
            'create' => Pages\CreateTransaction::route('/create'),
            'view' => Pages\ViewTransaction::route('/{record}'),
            'edit' => Pages\EditTransaction::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', 'pending')->count();
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'warning';
    }
}
