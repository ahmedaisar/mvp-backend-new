<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GuestProfileResource\Pages;
use App\Filament\Resources\GuestProfileResource\RelationManagers;
use App\Models\GuestProfile;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Support\Enums\FontWeight;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class GuestProfileResource extends Resource
{
    protected static ?string $model = GuestProfile::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'Reservations';

    protected static ?int $navigationSort = 3;    protected static ?string $recordTitleAttribute = 'full_name';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('Guest Information')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('Personal Details')
                            ->schema([
                                Forms\Components\Section::make('Basic Information')
                                    ->schema([
                                        Forms\Components\TextInput::make('full_name')
                                            ->required()
                                            ->maxLength(255)
                                            ->placeholder('John Doe'),
                                            
                                        Forms\Components\TextInput::make('email')
                                            ->email()
                                            ->required()
                                            ->maxLength(255)
                                            ->unique(ignoreRecord: true),
                                            
                                        Forms\Components\TextInput::make('phone')
                                            ->tel()
                                            ->maxLength(255)
                                            ->placeholder('+960-7123456'),
                                            
                                        Forms\Components\Select::make('country')
                                            ->options([
                                                'MV' => 'Maldives',
                                                'IN' => 'India',
                                                'US' => 'United States',
                                                'GB' => 'United Kingdom',
                                                'DE' => 'Germany',
                                                'FR' => 'France',
                                                'AU' => 'Australia',
                                                'SG' => 'Singapore',
                                                'AE' => 'UAE',
                                                'CN' => 'China',
                                            ])
                                            ->searchable()
                                            ->placeholder('Select country'),
                                    ])
                                    ->columns(2),
                                    
                                Forms\Components\Section::make('Personal Details')
                                    ->schema([
                                        Forms\Components\DatePicker::make('date_of_birth')
                                            ->maxDate(now())
                                            ->displayFormat('d/m/Y'),
                                            
                                        Forms\Components\Select::make('gender')
                                            ->options([
                                                'male' => 'Male',
                                                'female' => 'Female',
                                                'other' => 'Other',
                                                'prefer_not_to_say' => 'Prefer not to say',
                                            ])
                                            ->placeholder('Select gender'),
                                    ])
                                    ->columns(2),
                            ]),
                            
                        Forms\Components\Tabs\Tab::make('Preferences')
                            ->schema([
                                Forms\Components\Section::make('Guest Preferences')
                                    ->schema([
                                        Forms\Components\KeyValue::make('preferences')
                                            ->label('Preferences')
                                            ->keyLabel('Preference')
                                            ->valueLabel('Details')
                                            ->default([
                                                'dietary_requirements' => '',
                                                'room_preference' => '',
                                                'special_requests' => '',
                                            ])
                                            ->helperText('Store guest preferences and special requirements')
                                            ->columnSpanFull(),
                                    ]),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('full_name')
                    ->label('Name')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold),
                    
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->icon('heroicon-m-envelope'),
                    
                Tables\Columns\TextColumn::make('phone')
                    ->searchable()
                    ->copyable()
                    ->icon('heroicon-m-phone')
                    ->placeholder('Not provided'),
                    
                Tables\Columns\TextColumn::make('country')
                    ->badge()
                    ->color('info')
                    ->formatStateUsing(function ($state) {
                        return match($state) {
                            'MV' => 'Maldives',
                            'IN' => 'India',
                            'US' => 'United States',
                            'GB' => 'United Kingdom',
                            'DE' => 'Germany',
                            'FR' => 'France',
                            'AU' => 'Australia',
                            'SG' => 'Singapore',
                            'AE' => 'UAE',
                            'CN' => 'China',
                            default => $state,
                        };
                    })
                    ->placeholder('Not specified'),
                    
                Tables\Columns\TextColumn::make('age')
                    ->label('Age')
                    ->sortable()
                    ->placeholder('N/A'),
                    
                Tables\Columns\TextColumn::make('gender')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn ($state) => ucfirst(str_replace('_', ' ', $state)))
                    ->placeholder('Not specified'),
                    
                Tables\Columns\TextColumn::make('total_bookings')
                    ->label('Bookings')
                    ->badge()
                    ->color('success'),
                    
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Registered')
                    ->dateTime()
                    ->sortable()
                    ->since()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('country')
                    ->options([
                        'MV' => 'Maldives',
                        'IN' => 'India',
                        'US' => 'United States',
                        'GB' => 'United Kingdom',
                        'DE' => 'Germany',
                        'FR' => 'France',
                        'AU' => 'Australia',
                        'SG' => 'Singapore',
                        'AE' => 'UAE',
                        'CN' => 'China',
                    ]),
                    
                SelectFilter::make('gender')
                    ->options([
                        'male' => 'Male',
                        'female' => 'Female',
                        'other' => 'Other',
                        'prefer_not_to_say' => 'Prefer not to say',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
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
                Infolists\Components\Tabs::make('Guest Profile')
                    ->tabs([
                        Infolists\Components\Tabs\Tab::make('Personal Information')
                            ->schema([
                                Infolists\Components\Section::make('Guest Details')
                                    ->schema([
                                        Infolists\Components\TextEntry::make('full_name')
                                            ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                                            ->weight(FontWeight::Bold),
                                        Infolists\Components\TextEntry::make('email')
                                            ->copyable()
                                            ->icon('heroicon-m-envelope'),
                                        Infolists\Components\TextEntry::make('phone')
                                            ->copyable()
                                            ->icon('heroicon-m-phone'),
                                        Infolists\Components\TextEntry::make('country')
                                            ->badge()
                                            ->color('info'),
                                        Infolists\Components\TextEntry::make('date_of_birth')
                                            ->date()
                                            ->icon('heroicon-m-calendar'),
                                        Infolists\Components\TextEntry::make('age')
                                            ->suffix(' years old'),
                                        Infolists\Components\TextEntry::make('gender')
                                            ->formatStateUsing(fn ($state) => ucfirst(str_replace('_', ' ', $state)))
                                            ->badge()
                                            ->color('gray'),
                                    ])
                                    ->columns(2),
                            ]),
                            
                        Infolists\Components\Tabs\Tab::make('Preferences')
                            ->schema([
                                Infolists\Components\Section::make('Guest Preferences')
                                    ->schema([
                                        Infolists\Components\KeyValueEntry::make('preferences')
                                            ->label('Preferences')
                                            ->hiddenLabel(),
                                    ]),
                            ]),
                            
                        Infolists\Components\Tabs\Tab::make('Booking History')
                            ->schema([
                                Infolists\Components\Section::make('Statistics')
                                    ->schema([
                                        Infolists\Components\TextEntry::make('total_bookings')
                                            ->label('Total Bookings')
                                            ->badge()
                                            ->color('success'),
                                        Infolists\Components\TextEntry::make('total_spent')
                                            ->label('Total Spent')
                                            ->money('USD')
                                            ->badge()
                                            ->color('warning'),
                                        Infolists\Components\TextEntry::make('created_at')
                                            ->label('Member Since')
                                            ->dateTime()
                                            ->since(),
                                    ])
                                    ->columns(3),
                            ]),
                    ])
                    ->columnSpanFull(),
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
            'index' => Pages\ListGuestProfiles::route('/'),
            'create' => Pages\CreateGuestProfile::route('/create'),
            'view' => Pages\ViewGuestProfile::route('/{record}'),
            'edit' => Pages\EditGuestProfile::route('/{record}/edit'),
        ];
    }
    
    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }
}
