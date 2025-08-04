<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers;
use App\Models\User;
use App\Models\Resort;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Hash;
use Filament\Support\Enums\FontWeight;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Administration';

    protected static ?int $navigationSort = 1;    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('User Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                            
                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(User::class, 'email', ignoreRecord: true),
                            
                        Forms\Components\DateTimePicker::make('email_verified_at')
                            ->label('Email Verified At'),
                    ])
                    ->columns(2),
                    
                Forms\Components\Section::make('Password')
                    ->schema([
                        Forms\Components\TextInput::make('password')
                            ->password()
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->minLength(8)
                            ->same('password_confirmation')
                            ->dehydrated(fn ($state) => filled($state))
                            ->dehydrateStateUsing(fn ($state) => Hash::make($state)),
                            
                        Forms\Components\TextInput::make('password_confirmation')
                            ->password()
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->minLength(8)
                            ->dehydrated(false),
                    ])
                    ->columns(2)
                    ->hiddenOn('view'),
                    
                Forms\Components\Section::make('Role & Permissions')
                    ->schema([
                        Forms\Components\Select::make('role')
                            ->required()
                            ->options([
                                'admin' => 'Administrator',
                                'resort_manager' => 'Resort Manager',
                                'agency_operator' => 'Agency Operator',
                                'customer' => 'Customer',
                            ])
                            ->default('customer')
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                if ($state !== 'resort_manager') {
                                    $set('resort_access', []);
                                }
                            }),
                            
                        Forms\Components\CheckboxList::make('resort_access')
                            ->label('Resort Access')
                            ->options(function () {
                                return Resort::where('active', true)->pluck('name', 'id');
                            })
                            ->columns(2)
                            ->visible(fn (Forms\Get $get) => $get('role') === 'resort_manager')
                            ->helperText('Select resorts this manager can access'),
                    ])
                    ->columns(1),
                    
                Forms\Components\Section::make('Profile Information')
                    ->schema([
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
                            
                        Forms\Components\Select::make('language')
                            ->options([
                                'en' => 'English',
                                'fr' => 'French',
                                'ru' => 'Russian',
                                'de' => 'German',
                                'es' => 'Spanish',
                                'it' => 'Italian',
                            ])
                            ->default('en'),
                    ])
                    ->columns(3),
                    
                Forms\Components\Section::make('Status')
                    ->schema([
                        Forms\Components\Toggle::make('active')
                            ->default(true)
                            ->helperText('Toggle to activate/deactivate the user'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold),
                    
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                    
                Tables\Columns\TextColumn::make('role')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'admin' => 'danger',
                        'resort_manager' => 'warning',
                        'agency_operator' => 'info',
                        'customer' => 'success',
                        default => 'gray',
                    })
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('phone')
                    ->searchable()
                    ->toggleable(),
                    
                Tables\Columns\TextColumn::make('country')
                    ->badge()
                    ->color('gray')
                    ->toggleable(),
                    
                Tables\Columns\TextColumn::make('language')
                    ->badge()
                    ->color('info')
                    ->toggleable(),
                    
                Tables\Columns\IconColumn::make('email_verified_at')
                    ->label('Verified')
                    ->boolean()
                    ->sortable(),
                    
                Tables\Columns\IconColumn::make('active')
                    ->boolean()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Joined')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                    
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->options([
                        'admin' => 'Administrator',
                        'resort_manager' => 'Resort Manager',
                        'agency_operator' => 'Agency Operator',
                        'customer' => 'Customer',
                    ]),
                    
                // SelectFilter::make('country')
                //     ->options(
                //         User::query()
                //             ->whereNotNull('country')
                //             ->distinct()
                //             ->pluck('country', 'country')
                //             ->toArray()
                //     ),
                    
                TernaryFilter::make('email_verified_at')
                    ->label('Email Verified')
                    ->placeholder('All users')
                    ->trueLabel('Verified only')
                    ->falseLabel('Unverified only'),
                    
                TernaryFilter::make('active')
                    ->label('Status')
                    ->placeholder('All users')
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('toggleStatus')
                    ->label(fn (User $record): string => $record->active ? 'Deactivate' : 'Activate')
                    ->icon(fn (User $record): string => $record->active ? 'heroicon-m-user-minus' : 'heroicon-m-user-plus')
                    ->color(fn (User $record): string => $record->active ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->action(fn (User $record) => $record->update(['active' => !$record->active])),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('activate')
                        ->label('Activate Selected')
                        ->icon('heroicon-m-user-plus')
                        ->color('success')
                        ->action(fn ($records) => $records->each(fn ($record) => $record->update(['active' => true])))
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\BulkAction::make('deactivate')
                        ->label('Deactivate Selected')
                        ->icon('heroicon-m-user-minus')
                        ->color('danger')
                        ->action(fn ($records) => $records->each(fn ($record) => $record->update(['active' => false])))
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('User Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('name')
                            ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                            ->weight(FontWeight::Bold),
                        Infolists\Components\TextEntry::make('email')
                            ->copyable()
                            ->icon('heroicon-m-envelope'),
                        Infolists\Components\TextEntry::make('phone')
                            ->copyable()
                            ->icon('heroicon-m-phone')
                            ->placeholder('Not provided'),
                        Infolists\Components\IconEntry::make('email_verified_at')
                            ->label('Email Verified')
                            ->boolean(),
                    ])
                    ->columns(2),
                    
                Infolists\Components\Section::make('Role & Access')
                    ->schema([
                        Infolists\Components\TextEntry::make('role')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'admin' => 'danger',
                                'resort_manager' => 'warning',
                                'agency_operator' => 'info',
                                'customer' => 'success',
                                default => 'gray',
                            }),
                        Infolists\Components\TextEntry::make('resort_access')
                            ->label('Resort Access')
                            ->formatStateUsing(function ($state) {
                                if (!$state || !is_array($state)) return 'No access';
                                $resorts = Resort::whereIn('id', $state)->pluck('name');
                                return $resorts->isEmpty() ? 'No access' : $resorts->join(', ');
                            })
                            ->visible(fn ($record) => $record->role === 'resort_manager'),
                    ])
                    ->columns(1),
                    
                Infolists\Components\Section::make('Profile')
                    ->schema([
                        Infolists\Components\TextEntry::make('country')
                            ->badge()
                            ->placeholder('Not specified'),
                        Infolists\Components\TextEntry::make('language')
                            ->badge()
                            ->color('info'),
                        Infolists\Components\IconEntry::make('active')
                            ->boolean(),
                    ])
                    ->columns(3),
                    
                Infolists\Components\Section::make('Account Details')
                    ->schema([
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Joined')
                            ->dateTime(),
                        Infolists\Components\TextEntry::make('updated_at')
                            ->label('Last Updated')
                            ->dateTime(),
                    ])
                    ->columns(2),
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'view' => Pages\ViewUser::route('/{record}'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
