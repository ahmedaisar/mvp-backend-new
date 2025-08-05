<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BookingItemResource\Pages;
use App\Models\BookingItem;
use App\Models\Booking;
use App\Models\RoomType;
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

class BookingItemResource extends Resource
{
    protected static ?string $model = BookingItem::class;

    protected static ?string $navigationIcon = 'heroicon-o-list-bullet';

    protected static ?string $navigationGroup = 'Reservations';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'item_name';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Booking Item Details')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('booking_id')
                                    ->label('Booking')
                                    ->relationship('booking', 'booking_reference')
                                    ->searchable()
                                    ->preload()
                                    ->required(),

                                Forms\Components\Select::make('room_type_id')
                                    ->label('Room Type')
                                    ->relationship('roomType', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->nullable(),
                            ]),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('item_name')
                                    ->label('Item Name')
                                    ->required()
                                    ->maxLength(100)
                                    ->placeholder('Room, Service, or Add-on name'),

                                Forms\Components\Select::make('item_type')
                                    ->label('Item Type')
                                    ->options([
                                        'accommodation' => 'Accommodation',
                                        'service' => 'Service',
                                        'addon' => 'Add-on',
                                        'fee' => 'Fee',
                                        'tax' => 'Tax',
                                        'discount' => 'Discount',
                                        'package' => 'Package',
                                    ])
                                    ->required()
                                    ->default('accommodation'),
                            ]),

                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->rows(3)
                            ->columnSpanFull()
                            ->placeholder('Detailed description of the item'),
                    ]),

                Forms\Components\Section::make('Stay Details')
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\DatePicker::make('check_in_date')
                                    ->label('Check-in Date')
                                    ->required(),

                                Forms\Components\DatePicker::make('check_out_date')
                                    ->label('Check-out Date')
                                    ->required()
                                    ->after('check_in_date'),

                                Forms\Components\TextInput::make('nights')
                                    ->label('Number of Nights')
                                    ->numeric()
                                    ->default(1)
                                    ->minValue(1),
                            ]),

                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('quantity')
                                    ->label('Quantity')
                                    ->numeric()
                                    ->default(1)
                                    ->minValue(1)
                                    ->required(),

                                Forms\Components\TextInput::make('adults')
                                    ->label('Adults')
                                    ->numeric()
                                    ->default(2)
                                    ->minValue(1),

                                Forms\Components\TextInput::make('children')
                                    ->label('Children')
                                    ->numeric()
                                    ->default(0)
                                    ->minValue(0),
                            ]),
                    ])
                    ->visible(fn (Forms\Get $get): bool => in_array($get('item_type'), ['accommodation', 'package'])),

                Forms\Components\Section::make('Pricing')
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('unit_price')
                                    ->label('Unit Price')
                                    ->numeric()
                                    ->required()
                                    ->prefix('$')
                                    ->step(0.01),

                                Forms\Components\TextInput::make('total_price')
                                    ->label('Total Price')
                                    ->numeric()
                                    ->required()
                                    ->prefix('$')
                                    ->step(0.01),

                                Forms\Components\TextInput::make('discount_amount')
                                    ->label('Discount Amount')
                                    ->numeric()
                                    ->default(0)
                                    ->prefix('$')
                                    ->step(0.01),
                            ]),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('rate_plan')
                                    ->label('Rate Plan')
                                    ->options([
                                        'standard' => 'Standard Rate',
                                        'advance_purchase' => 'Advance Purchase',
                                        'last_minute' => 'Last Minute',
                                        'corporate' => 'Corporate Rate',
                                        'group' => 'Group Rate',
                                        'promotional' => 'Promotional Rate',
                                    ])
                                    ->nullable(),

                                Forms\Components\TextInput::make('currency')
                                    ->label('Currency')
                                    ->default('USD')
                                    ->maxLength(3),
                            ]),
                    ]),

                Forms\Components\Section::make('Status & Preferences')
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\Select::make('status')
                                    ->label('Status')
                                    ->options([
                                        'confirmed' => 'Confirmed',
                                        'pending' => 'Pending',
                                        'cancelled' => 'Cancelled',
                                        'modified' => 'Modified',
                                        'no_show' => 'No Show',
                                    ])
                                    ->required()
                                    ->default('confirmed'),

                                Forms\Components\Toggle::make('is_refundable')
                                    ->label('Refundable')
                                    ->default(true),

                                Forms\Components\Toggle::make('is_modifiable')
                                    ->label('Modifiable')
                                    ->default(true),
                            ]),

                        Forms\Components\Textarea::make('special_requests')
                            ->label('Special Requests')
                            ->rows(3)
                            ->columnSpanFull(),

                        Forms\Components\KeyValue::make('preferences')
                            ->label('Guest Preferences')
                            ->keyLabel('Preference')
                            ->valueLabel('Value')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('booking.booking_reference')
                    ->label('Booking Ref')
                    ->searchable()
                    ->sortable()
                    ->url(fn (BookingItem $record): string => route('filament.admin.resources.bookings.view', $record->booking)),

                Tables\Columns\TextColumn::make('item_name')
                    ->label('Item Name')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold),

                Tables\Columns\BadgeColumn::make('item_type')
                    ->label('Type')
                    ->colors([
                        'primary' => 'accommodation',
                        'success' => 'service',
                        'warning' => 'addon',
                        'danger' => 'fee',
                        'gray' => 'tax',
                        'info' => 'discount',
                        'purple' => 'package',
                    ]),

                Tables\Columns\TextColumn::make('roomType.name')
                    ->label('Room Type')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('check_in_date')
                    ->label('Check-in')
                    ->date('M j, Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('check_out_date')
                    ->label('Check-out')
                    ->date('M j, Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('nights')
                    ->label('Nights')
                    ->alignCenter()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('quantity')
                    ->label('Qty')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('unit_price')
                    ->label('Unit Price')
                    ->money('USD')
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('total_price')
                    ->label('Total')
                    ->money('USD')
                    ->alignEnd()
                    ->weight(FontWeight::Bold),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'success' => 'confirmed',
                        'warning' => 'pending',
                        'danger' => 'cancelled',
                        'info' => 'modified',
                        'gray' => 'no_show',
                    ]),

                Tables\Columns\IconColumn::make('is_refundable')
                    ->label('Refundable')
                    ->boolean()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('item_type')
                    ->multiple()
                    ->options([
                        'accommodation' => 'Accommodation',
                        'service' => 'Service',
                        'addon' => 'Add-on',
                        'fee' => 'Fee',
                        'tax' => 'Tax',
                        'discount' => 'Discount',
                        'package' => 'Package',
                    ]),

                Tables\Filters\SelectFilter::make('status')
                    ->multiple()
                    ->options([
                        'confirmed' => 'Confirmed',
                        'pending' => 'Pending',
                        'cancelled' => 'Cancelled',
                        'modified' => 'Modified',
                        'no_show' => 'No Show',
                    ]),

                Tables\Filters\SelectFilter::make('room_type_id')
                    ->label('Room Type')
                    ->relationship('roomType', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload(),

                Tables\Filters\Filter::make('date_range')
                    ->form([
                        Forms\Components\DatePicker::make('check_in_from')
                            ->label('Check-in From'),
                        Forms\Components\DatePicker::make('check_in_until')
                            ->label('Check-in Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['check_in_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('check_in_date', '>=', $date),
                            )
                            ->when(
                                $data['check_in_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('check_in_date', '<=', $date),
                            );
                    }),

                Tables\Filters\Filter::make('price_range')
                    ->form([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('price_from')
                                    ->label('Price From')
                                    ->numeric()
                                    ->prefix('$'),
                                Forms\Components\TextInput::make('price_to')
                                    ->label('Price To')
                                    ->numeric()
                                    ->prefix('$'),
                            ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['price_from'],
                                fn (Builder $query, $price): Builder => $query->where('total_price', '>=', $price),
                            )
                            ->when(
                                $data['price_to'],
                                fn (Builder $query, $price): Builder => $query->where('total_price', '<=', $price),
                            );
                    }),

                Tables\Filters\TernaryFilter::make('is_refundable')
                    ->label('Refundable'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                
                Tables\Actions\Action::make('cancel')
                    ->label('Cancel')
                    ->icon('heroicon-m-x-circle')
                    ->color('danger')
                    ->visible(fn (BookingItem $record): bool => $record->status !== 'cancelled')
                    ->form([
                        Forms\Components\Textarea::make('cancellation_reason')
                            ->label('Cancellation Reason')
                            ->required(),
                    ])
                    ->action(function (BookingItem $record, array $data): void {
                        $record->update([
                            'status' => 'cancelled',
                            'cancelled_at' => now(),
                            'cancellation_reason' => $data['cancellation_reason'],
                        ]);
                    })
                    ->requiresConfirmation(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    
                    Tables\Actions\BulkAction::make('confirm')
                        ->label('Confirm Items')
                        ->icon('heroicon-m-check-circle')
                        ->color('success')
                        ->action(fn (Collection $records) => $records->each(fn (BookingItem $record) => $record->update(['status' => 'confirmed']))),
                        
                    Tables\Actions\BulkAction::make('cancel')
                        ->label('Cancel Items')
                        ->icon('heroicon-m-x-circle')
                        ->color('danger')
                        ->form([
                            Forms\Components\Textarea::make('cancellation_reason')
                                ->label('Cancellation Reason')
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $records->each(fn (BookingItem $record) => $record->update([
                                'status' => 'cancelled',
                                'cancelled_at' => now(),
                                'cancellation_reason' => $data['cancellation_reason'],
                            ]));
                        })
                        ->requiresConfirmation(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Booking Item Details')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('item_name')
                                    ->weight(FontWeight::Bold),
                                    
                                Infolists\Components\TextEntry::make('item_type')
                                    ->badge(),

                                Infolists\Components\TextEntry::make('booking.booking_reference')
                                    ->label('Booking Reference')
                                    ->url(fn (BookingItem $record): string => route('filament.admin.resources.bookings.view', $record->booking)),

                                Infolists\Components\TextEntry::make('status')
                                    ->badge(),
                            ]),

                        Infolists\Components\TextEntry::make('description')
                            ->columnSpanFull()
                            ->visible(fn (BookingItem $record): bool => !empty($record->description)),
                    ]),

                Infolists\Components\Section::make('Stay Information')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('check_in_date')
                                    ->date(),

                                Infolists\Components\TextEntry::make('check_out_date')
                                    ->date(),

                                Infolists\Components\TextEntry::make('nights')
                                    ->suffix(' nights'),
                            ]),

                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('quantity'),

                                Infolists\Components\TextEntry::make('adults'),

                                Infolists\Components\TextEntry::make('children'),
                            ]),
                    ])
                    ->visible(fn (BookingItem $record): bool => in_array($record->item_type, ['accommodation', 'package'])),

                Infolists\Components\Section::make('Pricing Details')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('unit_price')
                                    ->money('USD'),

                                Infolists\Components\TextEntry::make('discount_amount')
                                    ->money('USD'),

                                Infolists\Components\TextEntry::make('total_price')
                                    ->money('USD')
                                    ->weight(FontWeight::Bold),
                            ]),
                    ]),

                Infolists\Components\Section::make('Special Requests')
                    ->schema([
                        Infolists\Components\TextEntry::make('special_requests')
                            ->columnSpanFull(),
                    ])
                    ->visible(fn (BookingItem $record): bool => !empty($record->special_requests))
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
            'index' => Pages\ListBookingItems::route('/'),
            'create' => Pages\CreateBookingItem::route('/create'),
            'view' => Pages\ViewBookingItem::route('/{record}'),
            'edit' => Pages\EditBookingItem::route('/{record}/edit'),
        ];
    }
    
    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }
}
