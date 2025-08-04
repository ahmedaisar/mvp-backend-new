<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ResortResource\Pages;
use App\Filament\Resources\ResortResource\RelationManagers;
use App\Models\Resort;
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
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;

class ResortResource extends Resource
{
    protected static ?string $model = Resort::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationGroup = 'Property Management';

    protected static ?int $navigationSort = 1;    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('Resort Information')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('Basic Information')
                            ->schema([
                                Forms\Components\Section::make('Resort Details')
                                    ->schema([
                                        Forms\Components\TextInput::make('name')
                                            ->required()
                                            ->maxLength(255)
                                            ->live(true)
                                            ->afterStateUpdated(function (string $operation, $state, Forms\Set $set) {
                                                if ($operation !== 'create') {
                                                    return;
                                                }
                                                $set('slug', \Illuminate\Support\Str::slug($state));
                                            }),
                                            
                                        Forms\Components\TextInput::make('slug')
                                            ->required()
                                            ->maxLength(255)
                                            ->unique(Resort::class, 'slug', ignoreRecord: true)
                                            ->rules(['alpha_dash']),
                                            
                                        Forms\Components\Select::make('star_rating')
                                            ->required()
                                            ->options([
                                                3 => '3 Stars',
                                                4 => '4 Stars',
                                                5 => '5 Stars',
                                            ])
                                            ->default(5),
                                            
                                        Forms\Components\Select::make('resort_type')
                                            ->required()
                                            ->options([
                                                'resort' => 'Resort',
                                                'hotel' => 'Hotel',
                                                'villa' => 'Villa',
                                                'guesthouse' => 'Guesthouse',
                                            ])
                                            ->default('resort'),
                                            
                                        Forms\Components\Select::make('currency')
                                            ->required()
                                            ->options([
                                                'USD' => 'US Dollar (USD)',
                                                'EUR' => 'Euro (EUR)',
                                                'MVR' => 'Maldivian Rufiyaa (MVR)',
                                            ])
                                            ->default('USD'),
                                    ])
                                    ->columns(2),
                                    
                                Forms\Components\Section::make('Description')
                                    ->schema([
                                        Forms\Components\RichEditor::make('description')
                                            ->label('Description')
                                            ->columnSpanFull()
                                            ->required(),
                                    ]),
                            ]),
                            
                        Forms\Components\Tabs\Tab::make('Location')
                            ->schema([
                                Forms\Components\Section::make('Location Details')
                                    ->schema([
                                        Forms\Components\TextInput::make('location')
                                            ->required()
                                            ->maxLength(255)
                                            ->helperText('General location (e.g., Maldives, Indian Ocean)'),
                                            
                                        Forms\Components\TextInput::make('atoll')
                                            ->required()
                                            ->maxLength(255)
                                            ->helperText('Atoll name (e.g., North Male Atoll, Baa Atoll)'),
                                            
                                        Forms\Components\TextInput::make('island')
                                            ->required()
                                            ->maxLength(255)
                                            ->helperText('Island name'),
                                            
                                        Forms\Components\TextInput::make('coordinates')
                                            ->maxLength(255)
                                            ->placeholder('4.1755, 73.5093')
                                            ->helperText('GPS coordinates (latitude, longitude)'),
                                    ])
                                    ->columns(2),
                            ]),
                            
                        Forms\Components\Tabs\Tab::make('Contact & Timing')
                            ->schema([
                                Forms\Components\Section::make('Contact Information')
                                    ->schema([
                                        Forms\Components\TextInput::make('contact_email')
                                            ->email()
                                            ->required()
                                            ->maxLength(255),
                                            
                                        Forms\Components\TextInput::make('contact_phone')
                                            ->tel()
                                            ->required()
                                            ->maxLength(255),
                                    ])
                                    ->columns(2),
                                    
                                Forms\Components\Section::make('Check-in/Check-out Times')
                                    ->schema([
                                        Forms\Components\TimePicker::make('check_in_time')
                                            ->default('14:00')
                                            ->required(),
                                            
                                        Forms\Components\TimePicker::make('check_out_time')
                                            ->default('12:00')
                                            ->required(),
                                    ])
                                    ->columns(2),
                            ]),
                            
                        Forms\Components\Tabs\Tab::make('Amenities')
                            ->schema([
                                Forms\Components\Section::make('Resort Amenities')
                                    ->schema([
                                        Forms\Components\CheckboxList::make('amenities')
                                            ->relationship('amenities', 'name')
                                            ->columns(3)
                                            ->searchable(),
                                    ]),
                            ]),
                            
                        Forms\Components\Tabs\Tab::make('Tax & Pricing')
                            ->schema([
                                Forms\Components\Section::make('Tax Configuration')
                                    ->schema([
                                        Forms\Components\KeyValue::make('tax_rules')
                                            ->label('Tax Rules')
                                            ->keyLabel('Tax Type')
                                            ->valueLabel('Percentage (%)')
                                            ->default([
                                                'gst' => 8,
                                                'service_fee' => 12,
                                            ])
                                            ->helperText('Configure tax rules for this resort (e.g., GST, Service Fee)')
                                            ->columnSpanFull(),
                                    ]),
                            ]),
                            
                        Forms\Components\Tabs\Tab::make('Media')
                            ->schema([
                                Forms\Components\Section::make('Resort Images')
                                    ->schema([
                                        SpatieMediaLibraryFileUpload::make('featured_image')
                                            ->label('Featured Image')
                                            ->collection('featured')
                                            ->image()
                                            ->imageEditor()
                                            ->imageCropAspectRatio('16:9')
                                            ->imageResizeTargetWidth('1200')
                                            ->imageResizeTargetHeight('675')
                                            ->maxFiles(1)
                                            ->helperText('Main resort image displayed in listings (recommended: 1200x675px)'),
                                            
                                        SpatieMediaLibraryFileUpload::make('gallery')
                                            ->label('Gallery Images')
                                            ->collection('gallery')
                                            ->image()
                                            ->imageEditor()
                                            ->imageCropAspectRatio('4:3')
                                            ->imageResizeTargetWidth('800')
                                            ->imageResizeTargetHeight('600')
                                            ->multiple()
                                            ->reorderable()
                                            ->maxFiles(25)
                                            ->helperText('Resort gallery images (recommended: 800x600px, max 25 images)')
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(1),
                            ]),
                            
                        Forms\Components\Tabs\Tab::make('Settings')
                            ->schema([
                                Forms\Components\Section::make('Resort Status')
                                    ->schema([
                                        Forms\Components\Toggle::make('active')
                                            ->label('Active')
                                            ->default(true)
                                            ->helperText('Toggle to activate/deactivate the resort'),
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
                Tables\Columns\ImageColumn::make('featured_image')
                    ->label('Image')
                    ->size(60)
                    ->circular()
                    ->defaultImageUrl(url('/images/placeholder.png')),
                    
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold),
                    
                Tables\Columns\TextColumn::make('island')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('info'),
                    
                Tables\Columns\TextColumn::make('atoll')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('gray'),
                    
                Tables\Columns\TextColumn::make('star_rating')
                    ->label('Rating')
                    ->sortable()
                    ->formatStateUsing(fn (string $state): string => str_repeat('⭐', (int) $state)),
                    
                Tables\Columns\TextColumn::make('resort_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'resort' => 'success',
                        'hotel' => 'warning',
                        'villa' => 'info',
                        'guesthouse' => 'gray',
                        default => 'gray',
                    }),
                    
                Tables\Columns\TextColumn::make('currency')
                    ->label('Currency')
                    ->badge()
                    ->color('warning'),
                    
                Tables\Columns\TextColumn::make('room_types_count')
                    ->label('Room Types')
                    ->counts('roomTypes')
                    ->badge()
                    ->color('primary'),
                    
                Tables\Columns\TextColumn::make('bookings_count')
                    ->label('Bookings')
                    ->counts('bookings')
                    ->badge()
                    ->color('success'),
                    
                Tables\Columns\IconColumn::make('active')
                    ->label('Status')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->since()
                    ->toggleable(isToggledHiddenByDefault: true),
                    
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable()
                    ->since()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('island')
                    ->options(
                        Resort::query()
                            ->distinct()
                            ->pluck('island', 'island')
                            ->toArray()
                    ),
                    
                SelectFilter::make('atoll')
                    ->options(
                        Resort::query()
                            ->distinct()
                            ->pluck('atoll', 'atoll')
                            ->toArray()
                    ),
                    
                SelectFilter::make('star_rating')
                    ->options([
                        3 => '3 Stars',
                        4 => '4 Stars', 
                        5 => '5 Stars',
                    ]),
                    
                SelectFilter::make('resort_type')
                    ->options([
                        'resort' => 'Resort',
                        'hotel' => 'Hotel',
                        'villa' => 'Villa',
                        'guesthouse' => 'Guesthouse',
                    ]),
                    
                SelectFilter::make('currency')
                    ->options([
                        'MVR' => 'Maldivian Rufiyaa',
                        'USD' => 'US Dollar',
                        'EUR' => 'Euro',
                    ]),
                    
                TernaryFilter::make('active')
                    ->label('Status')
                    ->placeholder('All resorts')
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('toggleStatus')
                    ->label(fn (Resort $record): string => $record->active ? 'Deactivate' : 'Activate')
                    ->icon(fn (Resort $record): string => $record->active ? 'heroicon-m-eye-slash' : 'heroicon-m-eye')
                    ->color(fn (Resort $record): string => $record->active ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->action(fn (Resort $record) => $record->update(['active' => !$record->active]))
                    ->successNotificationTitle(fn (Resort $record): string => 
                        'Resort ' . ($record->active ? 'activated' : 'deactivated') . ' successfully.'
                    ),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('activate')
                        ->label('Activate Selected')
                        ->icon('heroicon-m-eye')
                        ->color('success')
                        ->action(fn ($records) => $records->each(fn ($record) => $record->update(['active' => true])))
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\BulkAction::make('deactivate')
                        ->label('Deactivate Selected')
                        ->icon('heroicon-m-eye-slash')
                        ->color('danger')
                        ->action(fn ($records) => $records->each(fn ($record) => $record->update(['active' => false])))
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->defaultSort('name');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Tabs::make('Resort Details')
                    ->tabs([
                        Infolists\Components\Tabs\Tab::make('Overview')
                            ->schema([
                                Infolists\Components\Section::make('Resort Information')
                                    ->schema([
                                        Infolists\Components\ImageEntry::make('featured_image')
                                            ->hiddenLabel()
                                            ->size(300),
                                        Infolists\Components\TextEntry::make('name')
                                            ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                                            ->weight(FontWeight::Bold),
                                        Infolists\Components\TextEntry::make('slug')
                                            ->badge()
                                            ->color('gray'),
                                        Infolists\Components\TextEntry::make('star_rating')
                                            ->formatStateUsing(fn (string $state): string => str_repeat('⭐', (int) $state)),
                                        Infolists\Components\TextEntry::make('resort_type')
                                            ->badge()
                                            ->color('success'),
                                        Infolists\Components\TextEntry::make('currency')
                                            ->badge()
                                            ->color('warning'),
                                        Infolists\Components\IconEntry::make('active')
                                            ->label('Status')
                                            ->boolean()
                                            ->trueIcon('heroicon-o-check-circle')
                                            ->falseIcon('heroicon-o-x-circle')
                                            ->trueColor('success')
                                            ->falseColor('danger'),
                                    ])
                                    ->columns(2),
                                    
                                Infolists\Components\Section::make('Description')
                                    ->schema([
                                        Infolists\Components\TextEntry::make('description')
                                            ->label('Description')
                                            ->prose()
                                            ->hiddenLabel(),
                                    ])
                                    ->collapsible(),
                            ]),
                            
                        Infolists\Components\Tabs\Tab::make('Location & Contact')
                            ->schema([
                                Infolists\Components\Section::make('Location Details')
                                    ->schema([
                                        Infolists\Components\TextEntry::make('location')
                                            ->badge()
                                            ->color('info')
                                            ->icon('heroicon-o-globe-alt'),
                                        Infolists\Components\TextEntry::make('atoll')
                                            ->badge()
                                            ->color('primary')
                                            ->icon('heroicon-o-map'),
                                        Infolists\Components\TextEntry::make('island')
                                            ->badge()
                                            ->color('success')
                                            ->icon('heroicon-o-map-pin'),
                                        Infolists\Components\TextEntry::make('coordinates')
                                            ->icon('heroicon-o-map-pin')
                                            ->copyable(),
                                    ])
                                    ->columns(2),
                                    
                                Infolists\Components\Section::make('Contact Information')
                                    ->schema([
                                        Infolists\Components\TextEntry::make('contact_email')
                                            ->copyable()
                                            ->icon('heroicon-m-envelope'),
                                        Infolists\Components\TextEntry::make('contact_phone')
                                            ->copyable()
                                            ->icon('heroicon-m-phone'),
                                    ])
                                    ->columns(2),
                                    
                                Infolists\Components\Section::make('Operating Hours')
                                    ->schema([
                                        Infolists\Components\TextEntry::make('check_in_time')
                                            ->label('Check-in Time')
                                            ->icon('heroicon-m-clock')
                                            ->time('H:i'),
                                        Infolists\Components\TextEntry::make('check_out_time')
                                            ->label('Check-out Time')
                                            ->icon('heroicon-m-clock')
                                            ->time('H:i'),
                                    ])
                                    ->columns(2),
                            ]),
                            
                        Infolists\Components\Tabs\Tab::make('Amenities')
                            ->schema([
                                Infolists\Components\Section::make('Resort Amenities')
                                    ->schema([
                                        Infolists\Components\TextEntry::make('amenities.name')
                                            ->listWithLineBreaks()
                                            ->badge()
                                            ->color('primary'),
                                    ]),
                            ]),
                            
                        Infolists\Components\Tabs\Tab::make('Tax & Pricing')
                            ->schema([
                                Infolists\Components\Section::make('Tax Configuration')
                                    ->schema([
                                        Infolists\Components\KeyValueEntry::make('tax_rules')
                                            ->label('Tax Rules')
                                            ->hiddenLabel(),
                                    ]),
                            ]),
                            
                        Infolists\Components\Tabs\Tab::make('Media')
                            ->schema([
                                Infolists\Components\Section::make('Resort Gallery')
                                    ->schema([
                                        Infolists\Components\ImageEntry::make('gallery')
                                            ->label('Gallery Images')
                                            ->hiddenLabel()
                                            ->size(150)
                                            ->circular(false),
                                    ]),
                            ]),
                            
                        Infolists\Components\Tabs\Tab::make('Statistics')
                            ->schema([
                                Infolists\Components\Section::make('Resort Statistics')
                                    ->schema([
                                        Infolists\Components\TextEntry::make('room_types_count')
                                            ->label('Room Types')
                                            ->badge()
                                            ->color('primary'),
                                        Infolists\Components\TextEntry::make('bookings_count')
                                            ->label('Total Bookings')
                                            ->badge()
                                            ->color('success'),
                                        Infolists\Components\TextEntry::make('created_at')
                                            ->label('Created')
                                            ->dateTime()
                                            ->since(),
                                        Infolists\Components\TextEntry::make('updated_at')
                                            ->label('Last Updated')
                                            ->dateTime()
                                            ->since(),
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
            RelationManagers\ManagersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListResorts::route('/'),
            'create' => Pages\CreateResort::route('/create'),
            'view' => Pages\ViewResort::route('/{record}'),
            'edit' => Pages\EditResort::route('/{record}/edit'),
        ];
    }
}
