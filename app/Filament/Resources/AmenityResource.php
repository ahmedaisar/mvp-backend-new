<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AmenityResource\Pages;
use App\Filament\Resources\AmenityResource\RelationManagers;
use App\Models\Amenity;
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

class AmenityResource extends Resource
{
    protected static ?string $model = Amenity::class;

    protected static ?string $navigationGroup = 'Property Management';

    protected static ?string $navigationIcon = 'heroicon-o-star';
    
    protected static ?int $navigationSort = 3;
    
    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('Amenity Information')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('Basic Details')
                            ->schema([
                                Forms\Components\Section::make('Amenity Information')
                                    ->schema([
                                        Forms\Components\TextInput::make('code')
                                            ->label('Amenity Code')
                                            ->required()
                                            ->maxLength(50)
                                            ->unique(ignoreRecord: true)
                                            ->placeholder('e.g., WIFI, SPA, GYM')
                                            ->helperText('Unique identifier for this amenity'),
                                            
                                        Forms\Components\TextInput::make('name')
                                            ->label('Amenity Name')
                                            ->required()
                                            ->maxLength(255)
                                            ->placeholder('e.g., Wi-Fi Internet, Spa & Wellness Center'),
                                            
                                        Forms\Components\Select::make('category')
                                            ->required()
                                            ->options([
                                                'connectivity' => 'Connectivity',
                                                'wellness' => 'Wellness & Spa',
                                                'dining' => 'Dining',
                                                'recreation' => 'Recreation',
                                                'business' => 'Business',
                                                'family' => 'Family & Kids',
                                                'water_sports' => 'Water Sports',
                                                'transportation' => 'Transportation',
                                                'accommodation' => 'Accommodation',
                                                'service' => 'Service',
                                            ])
                                            ->placeholder('Select amenity category'),
                                            
                                        Forms\Components\TextInput::make('icon')
                                            ->label('Icon Class')
                                            ->maxLength(255)
                                            ->placeholder('e.g., fas fa-wifi, heroicon-o-wifi')
                                            ->helperText('CSS class for the amenity icon'),
                                    ])
                                    ->columns(2),
                            ]),
                            
                        Forms\Components\Tabs\Tab::make('Settings')
                            ->schema([
                                Forms\Components\Section::make('Amenity Status')
                                    ->schema([
                                        Forms\Components\Toggle::make('active')
                                            ->label('Active')
                                            ->default(true)
                                            ->helperText('Only active amenities are available for assignment'),
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
                Tables\Columns\TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->weight(FontWeight::Bold)
                    ->badge()
                    ->color('gray'),
                    
                Tables\Columns\TextColumn::make('name')
                    ->label('Amenity Name')
                    ->searchable()
                    ->wrap(),
                    
                Tables\Columns\TextColumn::make('category')
                    ->label('Category')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'connectivity' => 'info',
                        'wellness' => 'success',
                        'dining' => 'warning',
                        'recreation' => 'primary',
                        'water_sports' => 'cyan',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => str_replace('_', ' ', ucwords($state, '_'))),
                    
                Tables\Columns\TextColumn::make('icon')
                    ->label('Icon')
                    ->badge()
                    ->color('gray')
                    ->toggleable(),
                    
                Tables\Columns\TextColumn::make('resorts_count')
                    ->label('Used by Resorts')
                    ->counts('resorts')
                    ->badge()
                    ->color('primary'),
                    
                Tables\Columns\TextColumn::make('room_types_count')
                    ->label('Used by Room Types')
                    ->counts('roomTypes')
                    ->badge()
                    ->color('secondary'),
                    
                Tables\Columns\IconColumn::make('active')
                    ->label('Status')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),
                    
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->options([
                        'connectivity' => 'Connectivity',
                        'wellness' => 'Wellness & Spa',
                        'dining' => 'Dining',
                        'recreation' => 'Recreation',
                        'business' => 'Business',
                        'family' => 'Family & Kids',
                        'water_sports' => 'Water Sports',
                        'transportation' => 'Transportation',
                        'accommodation' => 'Accommodation',
                        'service' => 'Service',
                    ]),
                    
                TernaryFilter::make('active')
                    ->label('Status')
                    ->placeholder('All amenities')
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('activate')
                        ->label('Activate Selected')
                        ->icon('heroicon-o-check')
                        ->action(fn ($records) => $records->each->update(['active' => true]))
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\BulkAction::make('deactivate')
                        ->label('Deactivate Selected')
                        ->icon('heroicon-o-x-mark')
                        ->action(fn ($records) => $records->each->update(['active' => false]))
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->defaultSort('category');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Tabs::make('Amenity Details')
                    ->tabs([
                        Infolists\Components\Tabs\Tab::make('Overview')
                            ->schema([
                                Infolists\Components\Section::make('Amenity Information')
                                    ->schema([
                                        Infolists\Components\TextEntry::make('code')
                                            ->badge()
                                            ->color('gray'),
                                        Infolists\Components\TextEntry::make('name')
                                            ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                                            ->weight(FontWeight::Bold),
                                        Infolists\Components\TextEntry::make('category')
                                            ->badge()
                                            ->color('primary')
                                            ->formatStateUsing(fn (string $state): string => str_replace('_', ' ', ucwords($state, '_'))),
                                        Infolists\Components\TextEntry::make('icon')
                                            ->badge()
                                            ->color('gray'),
                                        Infolists\Components\IconEntry::make('active')
                                            ->label('Status')
                                            ->boolean(),
                                    ])
                                    ->columns(2),
                                    
                                Infolists\Components\Section::make('Usage Statistics')
                                    ->schema([
                                        Infolists\Components\TextEntry::make('resorts_count')
                                            ->label('Used by Resorts')
                                            ->formatStateUsing(fn ($state) => $state . ' resort(s)'),
                                        Infolists\Components\TextEntry::make('room_types_count')
                                            ->label('Used by Room Types')
                                            ->formatStateUsing(fn ($state) => $state . ' room type(s)'),
                                    ])
                                    ->columns(2),
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
            'index' => Pages\ListAmenities::route('/'),
            'create' => Pages\CreateAmenity::route('/create'),
            'view' => Pages\ViewAmenity::route('/{record}'),
            'edit' => Pages\EditAmenity::route('/{record}/edit'),
        ];
    }
    
    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }
}
