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
                        Forms\Components\Select::make('booking_id')
                            ->label('Booking')
                            ->relationship('booking', 'booking_reference')
                            ->searchable()
                            ->preload()
                            ->required(),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('item_name')
                                    ->label('Item Name')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('Item name'),

                                Forms\Components\Select::make('item_type')
                                    ->label('Item Type')
                                    ->options([
                                        'resort' => 'Resort',
                                        'transfer' => 'Transfer',
                                        'extra_bed' => 'Extra Bed',
                                        'tax' => 'Tax',
                                        'service_fee' => 'Service Fee',
                                        'room' => 'Room',
                                        'discount' => 'Discount',
                                    ])
                                    ->required(),
                            ]),

                        Forms\Components\Textarea::make('item_description')
                            ->label('Description')
                            ->rows(3)
                            ->columnSpanFull()
                            ->placeholder('Detailed description of the item'),
                    ]),

                Forms\Components\Section::make('Pricing')
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('quantity')
                                    ->label('Quantity')
                                    ->numeric()
                                    ->default(1)
                                    ->minValue(1)
                                    ->required(),

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
                            ]),

                        Forms\Components\TextInput::make('currency')
                            ->label('Currency')
                            ->default('USD')
                            ->maxLength(3)
                            ->required(),
                    ]),

                Forms\Components\Section::make('Additional Information')
                    ->schema([
                        Forms\Components\KeyValue::make('metadata')
                            ->label('Metadata')
                            ->keyLabel('Key')
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
                        'primary' => 'resort',
                        'success' => 'transfer',
                        'warning' => 'extra_bed',
                        'danger' => 'tax',
                        'gray' => 'service_fee',
                        'info' => 'room',
                        'purple' => 'discount',
                    ]),

                Tables\Columns\TextColumn::make('item_description')
                    ->label('Description')
                    ->limit(50)
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

                Tables\Columns\TextColumn::make('currency')
                    ->label('Currency')
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
                        'resort' => 'Resort',
                        'transfer' => 'Transfer',
                        'extra_bed' => 'Extra Bed',
                        'tax' => 'Tax',
                        'service_fee' => 'Service Fee',
                        'room' => 'Room',
                        'discount' => 'Discount',
                    ]),

                Tables\Filters\SelectFilter::make('booking_id')
                    ->label('Booking')
                    ->relationship('booking', 'booking_reference')
                    ->searchable()
                    ->preload(),

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
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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

                                Infolists\Components\TextEntry::make('currency'),
                            ]),

                        Infolists\Components\TextEntry::make('item_description')
                            ->label('Description')
                            ->columnSpanFull()
                            ->visible(fn (BookingItem $record): bool => !empty($record->item_description)),
                    ]),

                Infolists\Components\Section::make('Pricing Details')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('quantity'),

                                Infolists\Components\TextEntry::make('unit_price')
                                    ->money('USD'),

                                Infolists\Components\TextEntry::make('total_price')
                                    ->money('USD')
                                    ->weight(FontWeight::Bold),
                            ]),
                    ]),

                Infolists\Components\Section::make('Metadata')
                    ->schema([
                        Infolists\Components\KeyValueEntry::make('metadata')
                            ->columnSpanFull(),
                    ])
                    ->visible(fn (BookingItem $record): bool => !empty($record->metadata))
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
