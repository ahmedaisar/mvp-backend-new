<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BookingResource\Pages;
use App\Filament\Resources\BookingResource\RelationManagers;
use App\Models\Booking;
use App\Models\Resort;
use App\Models\RoomType;
use App\Models\RatePlan;
use App\Models\GuestProfile;
use App\Models\Transfer;
use App\Models\Promotion;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TernaryFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Support\Enums\FontWeight;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Carbon\Carbon;

class BookingResource extends Resource
{
    protected static ?string $model = Booking::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationGroup = 'Reservations';

    protected static ?int $navigationSort = 1;    protected static ?string $recordTitleAttribute = 'booking_reference';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Booking Information')
                    ->schema([
                        Forms\Components\TextInput::make('booking_reference')
                            ->required()
                            ->maxLength(10)
                            ->default(fn () => 'BK' . strtoupper(uniqid()))
                            ->disabled()
                            ->dehydrated(),
                            
                        Forms\Components\Select::make('status')
                            ->required()
                            ->options([
                                'pending' => 'Pending',
                                'confirmed' => 'Confirmed',
                                'cancelled' => 'Cancelled',
                                'completed' => 'Completed',
                                'no_show' => 'No Show',
                            ])
                            ->default('pending')
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                if ($state === 'cancelled') {
                                    $set('cancelled_at', now());
                                } else {
                                    $set('cancelled_at', null);
                                }
                            }),
                    ])
                    ->columns(2),
                    
                Forms\Components\Section::make('Guest Information')
                    ->schema([
                        Forms\Components\Select::make('guest_profile_id')
                            ->label('Guest Profile')
                            ->relationship('guestProfile', 'full_name')
                            ->searchable()
                            ->preload()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('full_name')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('email')
                                    ->email()
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('phone')
                                    ->tel()
                                    ->maxLength(255),
                                Forms\Components\Select::make('country')
                                    ->options([
                                        'MV' => 'Maldives',
                                        'US' => 'United States',
                                        'GB' => 'United Kingdom',
                                        'DE' => 'Germany',
                                        'FR' => 'France',
                                        'IT' => 'Italy',
                                        'ES' => 'Spain',
                                        'RU' => 'Russia',
                                        'CN' => 'China',
                                        'JP' => 'Japan',
                                        'AU' => 'Australia',
                                        'IN' => 'India',
                                    ])
                                    ->searchable(),
                            ])
                            ->required(),
                    ]),
                    
                Forms\Components\Section::make('Accommodation Details')
                    ->schema([
                        Forms\Components\Select::make('resort_id')
                            ->label('Resort')
                            ->relationship('resort', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn ($state, Forms\Set $set) => $set('room_type_id', null)),
                            
                        Forms\Components\Select::make('room_type_id')
                            ->label('Room Type')
                            ->options(function (Forms\Get $get) {
                                $resortId = $get('resort_id');
                                if (!$resortId) return [];
                                
                                return RoomType::where('resort_id', $resortId)
                                    ->where('active', true)
                                    ->pluck('name', 'id');
                            })
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn ($state, Forms\Set $set) => $set('rate_plan_id', null)),
                            
                        Forms\Components\Select::make('rate_plan_id')
                            ->label('Rate Plan')
                            ->options(function (Forms\Get $get) {
                                $roomTypeId = $get('room_type_id');
                                if (!$roomTypeId) return [];
                                
                                return RatePlan::where('room_type_id', $roomTypeId)
                                    ->where('active', true)
                                    ->pluck('name', 'id');
                            })
                            ->required(),
                    ])
                    ->columns(3),
                    
                Forms\Components\Section::make('Stay Details')
                    ->schema([
                        Forms\Components\DatePicker::make('check_in')
                            ->required()
                            ->minDate(now())
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                $checkOut = $get('check_out');
                                if ($state && $checkOut) {
                                    $nights = Carbon::parse($state)->diffInDays(Carbon::parse($checkOut));
                                    $set('nights', $nights);
                                }
                            }),
                            
                        Forms\Components\DatePicker::make('check_out')
                            ->required()
                            ->minDate(fn (Forms\Get $get) => $get('check_in') ? Carbon::parse($get('check_in'))->addDay() : now()->addDay())
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                $checkIn = $get('check_in');
                                if ($checkIn && $state) {
                                    $nights = Carbon::parse($checkIn)->diffInDays(Carbon::parse($state));
                                    $set('nights', $nights);
                                }
                            }),
                            
                        Forms\Components\TextInput::make('nights')
                            ->required()
                            ->numeric()
                            ->disabled()
                            ->dehydrated(),
                            
                        Forms\Components\TextInput::make('adults')
                            ->required()
                            ->numeric()
                            ->default(2)
                            ->minValue(1)
                            ->maxValue(8),
                            
                        Forms\Components\TextInput::make('children')
                            ->required()
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->maxValue(6),
                    ])
                    ->columns(5),
                    
                Forms\Components\Section::make('Additional Services')
                    ->schema([
                        Forms\Components\Select::make('transfer_id')
                            ->label('Transfer Service')
                            ->relationship('transfer', 'name')
                            ->searchable()
                            ->preload(),
                            
                        Forms\Components\Select::make('promotion_id')
                            ->label('Promotion')
                            ->relationship('promotion', 'code')
                            ->searchable()
                            ->preload(),
                    ])
                    ->columns(2),
                    
                Forms\Components\Section::make('Pricing')
                    ->schema([
                        Forms\Components\TextInput::make('subtotal_usd')
                            ->label('Subtotal (USD)')
                            ->required()
                            ->numeric()
                            ->prefix('$'),
                            
                        Forms\Components\TextInput::make('discount_amount')
                            ->label('Discount Amount')
                            ->required()
                            ->numeric()
                            ->default(0.00)
                            ->prefix('$'),
                            
                        Forms\Components\TextInput::make('total_price_usd')
                            ->label('Total Price (USD)')
                            ->required()
                            ->numeric()
                            ->prefix('$'),
                            
                        Forms\Components\TextInput::make('currency_rate_usd')
                            ->label('USD Exchange Rate')
                            ->required()
                            ->numeric()
                            ->default(1.00)
                            ->step(0.01),
                    ])
                    ->columns(4),
                    
                Forms\Components\Section::make('Special Requests & Notes')
                    ->schema([
                        Forms\Components\Textarea::make('special_requests')
                            ->rows(3)
                            ->columnSpanFull(),
                            
                        Forms\Components\Textarea::make('cancellation_reason')
                            ->rows(3)
                            ->columnSpanFull()
                            ->visible(fn (Forms\Get $get) => $get('status') === 'cancelled'),
                            
                        Forms\Components\DateTimePicker::make('cancelled_at')
                            ->visible(fn (Forms\Get $get) => $get('status') === 'cancelled'),
                    ])
                    ->columns(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('booking_reference')
                    ->label('Reference')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold)
                    ->copyable(),
                    
                Tables\Columns\TextColumn::make('guestProfile.full_name')
                    ->label('Guest')
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('resort.name')
                    ->label('Resort')
                    ->searchable()
                    ->sortable()
                    ->limit(20),
                    
                Tables\Columns\TextColumn::make('roomType.name')
                    ->label('Room Type')
                    ->searchable()
                    ->sortable()
                    ->limit(15),
                    
                Tables\Columns\TextColumn::make('check_in')
                    ->label('Check In')
                    ->date()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('check_out')
                    ->label('Check Out')
                    ->date()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('nights')
                    ->sortable()
                    ->alignCenter(),
                    
                Tables\Columns\TextColumn::make('adults')
                    ->alignCenter()
                    ->toggleable(),
                    
                Tables\Columns\TextColumn::make('children')
                    ->alignCenter()
                    ->toggleable(),
                    
                Tables\Columns\TextColumn::make('total_price_usd')
                    ->label('Total Price')
                    ->money('USD')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'confirmed' => 'success',
                        'cancelled' => 'danger',
                        'completed' => 'info',
                        'no_show' => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Booked')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                    
                Tables\Columns\TextColumn::make('cancelled_at')
                    ->label('Cancelled')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'confirmed' => 'Confirmed',
                        'cancelled' => 'Cancelled',
                        'completed' => 'Completed',
                        'no_show' => 'No Show',
                    ]),
                    
                SelectFilter::make('resort')
                    ->relationship('resort', 'name')
                    ->searchable()
                    ->preload(),
                    
                Filter::make('check_in')
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
                                fn (Builder $query, $date): Builder => $query->whereDate('check_in', '>=', $date),
                            )
                            ->when(
                                $data['check_in_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('check_in', '<=', $date),
                            );
                    }),
                    
                Filter::make('total_price_usd')
                    ->form([
                        Forms\Components\TextInput::make('price_from')
                            ->label('Price From (USD)')
                            ->numeric(),
                        Forms\Components\TextInput::make('price_to')
                            ->label('Price To (USD)')
                            ->numeric(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['price_from'],
                                fn (Builder $query, $price): Builder => $query->where('total_price_usd', '>=', $price),
                            )
                            ->when(
                                $data['price_to'],
                                fn (Builder $query, $price): Builder => $query->where('total_price_usd', '<=', $price),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('confirm')
                    ->label('Confirm')
                    ->icon('heroicon-m-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(fn (Booking $record) => $record->update(['status' => 'confirmed']))
                    ->visible(fn (Booking $record): bool => $record->status === 'pending'),
                Tables\Actions\Action::make('cancel')
                    ->label('Cancel')
                    ->icon('heroicon-m-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('cancellation_reason')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (Booking $record, array $data) {
                        $record->update([
                            'status' => 'cancelled',
                            'cancelled_at' => now(),
                            'cancellation_reason' => $data['cancellation_reason'],
                        ]);
                    })
                    ->visible(fn (Booking $record): bool => in_array($record->status, ['pending', 'confirmed'])),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('confirm')
                        ->label('Confirm Selected')
                        ->icon('heroicon-m-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(fn ($records) => $records->each(fn ($record) => $record->update(['status' => 'confirmed'])))
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Booking Overview')
                    ->schema([
                        Infolists\Components\TextEntry::make('booking_reference')
                            ->label('Reference')
                            ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                            ->weight(FontWeight::Bold)
                            ->copyable(),
                        Infolists\Components\TextEntry::make('status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'pending' => 'warning',
                                'confirmed' => 'success',
                                'cancelled' => 'danger',
                                'completed' => 'info',
                                'no_show' => 'gray',
                                default => 'gray',
                            }),
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Booking Date')
                            ->dateTime(),
                    ])
                    ->columns(3),
                    
                Infolists\Components\Section::make('Guest Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('guestProfile.full_name')
                            ->label('Name'),
                        Infolists\Components\TextEntry::make('guestProfile.email')
                            ->label('Email')
                            ->copyable()
                            ->icon('heroicon-m-envelope'),
                        Infolists\Components\TextEntry::make('guestProfile.phone')
                            ->label('Phone')
                            ->copyable()
                            ->icon('heroicon-m-phone'),
                        Infolists\Components\TextEntry::make('guestProfile.country')
                            ->label('Country')
                            ->badge(),
                    ])
                    ->columns(2),
                    
                Infolists\Components\Section::make('Accommodation Details')
                    ->schema([
                        Infolists\Components\TextEntry::make('resort.name')
                            ->label('Resort')
                            ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                            ->weight(FontWeight::Bold),
                        Infolists\Components\TextEntry::make('roomType.name')
                            ->label('Room Type'),
                        Infolists\Components\TextEntry::make('ratePlan.name')
                            ->label('Rate Plan'),
                        Infolists\Components\TextEntry::make('ratePlan.meal_plan')
                            ->label('Meal Plan')
                            ->badge()
                            ->color('info'),
                    ])
                    ->columns(2),
                    
                Infolists\Components\Section::make('Stay Details')
                    ->schema([
                        Infolists\Components\TextEntry::make('check_in')
                            ->label('Check In')
                            ->date()
                            ->icon('heroicon-m-calendar'),
                        Infolists\Components\TextEntry::make('check_out')
                            ->label('Check Out')
                            ->date()
                            ->icon('heroicon-m-calendar'),
                        Infolists\Components\TextEntry::make('nights')
                            ->label('Nights')
                            ->icon('heroicon-m-moon'),
                        Infolists\Components\TextEntry::make('adults')
                            ->label('Adults')
                            ->icon('heroicon-m-user'),
                        Infolists\Components\TextEntry::make('children')
                            ->label('Children')
                            ->icon('heroicon-m-user'),
                    ])
                    ->columns(5),
                    
                Infolists\Components\Section::make('Pricing Breakdown')
                    ->schema([
                        Infolists\Components\TextEntry::make('subtotal_usd')
                            ->label('Subtotal')
                            ->money('USD'),
                        Infolists\Components\TextEntry::make('discount_amount')
                            ->label('Discount')
                            ->money('USD')
                            ->color('success')
                            ->visible(fn ($record) => $record->discount_amount > 0),
                        Infolists\Components\TextEntry::make('total_price_usd')
                            ->label('Total Price')
                            ->money('USD')
                            ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                            ->weight(FontWeight::Bold),
                    ])
                    ->columns(3),
                    
                Infolists\Components\Section::make('Additional Services')
                    ->schema([
                        Infolists\Components\TextEntry::make('transfer.name')
                            ->label('Transfer Service')
                            ->placeholder('No transfer selected'),
                        Infolists\Components\TextEntry::make('promotion.code')
                            ->label('Promotion Code')
                            ->badge()
                            ->color('success')
                            ->placeholder('No promotion applied'),
                    ])
                    ->columns(2)
                    ->visible(fn ($record) => $record->transfer_id || $record->promotion_id),
                    
                Infolists\Components\Section::make('Special Requests')
                    ->schema([
                        Infolists\Components\TextEntry::make('special_requests')
                            ->prose()
                            ->placeholder('No special requests'),
                    ])
                    ->visible(fn ($record) => $record->special_requests),
                    
                Infolists\Components\Section::make('Cancellation Details')
                    ->schema([
                        Infolists\Components\TextEntry::make('cancelled_at')
                            ->label('Cancelled At')
                            ->dateTime(),
                        Infolists\Components\TextEntry::make('cancellation_reason')
                            ->label('Reason')
                            ->prose(),
                    ])
                    ->columns(1)
                    ->visible(fn ($record) => $record->status === 'cancelled'),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            // RelationManagers will be created separately
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBookings::route('/'),
            'create' => Pages\CreateBooking::route('/create'),
            'view' => Pages\ViewBooking::route('/{record}'),
            'edit' => Pages\EditBooking::route('/{record}/edit'),
        ];
    }
    
    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }
}
