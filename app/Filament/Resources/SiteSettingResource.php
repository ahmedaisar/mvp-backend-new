<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SiteSettingResource\Pages;
use App\Filament\Resources\SiteSettingResource\RelationManagers;
use App\Models\SiteSetting;
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

class SiteSettingResource extends Resource
{
    protected static ?string $model = SiteSetting::class;

    protected static ?string $navigationGroup = 'Administration';

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    
    protected static ?int $navigationSort = 2;
    
    protected static ?string $recordTitleAttribute = 'key';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('Setting Configuration')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('Basic Information')
                            ->schema([
                                Forms\Components\Section::make('Setting Details')
                                    ->schema([
                                        Forms\Components\TextInput::make('key')
                                            ->label('Setting Key')
                                            ->required()
                                            ->maxLength(100)
                                            ->unique(ignoreRecord: true)
                                            ->placeholder('e.g., site_name, admin_email, maintenance_mode')
                                            ->helperText('Unique identifier for this setting'),
                                            
                                        Forms\Components\Select::make('type')
                                            ->required()
                                            ->options([
                                                'string' => 'Text',
                                                'integer' => 'Number (Integer)',
                                                'float' => 'Number (Decimal)',
                                                'boolean' => 'True/False',
                                                'array' => 'JSON Array',
                                                'object' => 'JSON Object',
                                                'email' => 'Email Address',
                                                'url' => 'URL',
                                                'color' => 'Color',
                                                'date' => 'Date',
                                                'datetime' => 'Date & Time',
                                            ])
                                            ->default('string')
                                            ->reactive()
                                            ->placeholder('Select data type'),
                                            
                                        Forms\Components\Textarea::make('description')
                                            ->label('Description')
                                            ->placeholder('Brief description of what this setting controls')
                                            ->rows(2)
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(2),
                            ]),
                            
                        Forms\Components\Tabs\Tab::make('Value Configuration')
                            ->schema([
                                Forms\Components\Section::make('Setting Value')
                                    ->schema([
                                        // String value
                                        Forms\Components\TextInput::make('value.value')
                                            ->label('Value')
                                            ->visible(fn (Forms\Get $get) => in_array($get('type'), ['string', 'email', 'url']))
                                            ->required(fn (Forms\Get $get) => in_array($get('type'), ['string', 'email', 'url']))
                                            ->email(fn (Forms\Get $get) => $get('type') === 'email')
                                            ->url(fn (Forms\Get $get) => $get('type') === 'url'),
                                            
                                        // Integer value
                                        Forms\Components\TextInput::make('value.value')
                                            ->label('Value')
                                            ->visible(fn (Forms\Get $get) => $get('type') === 'integer')
                                            ->required(fn (Forms\Get $get) => $get('type') === 'integer')
                                            ->numeric()
                                            ->integer(),
                                            
                                        // Float value
                                        Forms\Components\TextInput::make('value.value')
                                            ->label('Value')
                                            ->visible(fn (Forms\Get $get) => $get('type') === 'float')
                                            ->required(fn (Forms\Get $get) => $get('type') === 'float')
                                            ->numeric(),
                                            
                                        // Boolean value
                                        Forms\Components\Toggle::make('value.value')
                                            ->label('Enabled')
                                            ->visible(fn (Forms\Get $get) => $get('type') === 'boolean')
                                            ->required(fn (Forms\Get $get) => $get('type') === 'boolean'),
                                            
                                        // Color value
                                        Forms\Components\ColorPicker::make('value.value')
                                            ->label('Color')
                                            ->visible(fn (Forms\Get $get) => $get('type') === 'color')
                                            ->required(fn (Forms\Get $get) => $get('type') === 'color'),
                                            
                                        // Date value
                                        Forms\Components\DatePicker::make('value.value')
                                            ->label('Date')
                                            ->visible(fn (Forms\Get $get) => $get('type') === 'date')
                                            ->required(fn (Forms\Get $get) => $get('type') === 'date'),
                                            
                                        // DateTime value
                                        Forms\Components\DateTimePicker::make('value.value')
                                            ->label('Date & Time')
                                            ->visible(fn (Forms\Get $get) => $get('type') === 'datetime')
                                            ->required(fn (Forms\Get $get) => $get('type') === 'datetime'),
                                            
                                        // JSON/Array value
                                        Forms\Components\Textarea::make('value')
                                            ->label('JSON Value')
                                            ->visible(fn (Forms\Get $get) => in_array($get('type'), ['array', 'object']))
                                            ->required(fn (Forms\Get $get) => in_array($get('type'), ['array', 'object']))
                                            ->rows(5)
                                            ->placeholder('{"key": "value"}')
                                            ->helperText('Enter valid JSON format')
                                            ->columnSpanFull(),
                                    ]),
                            ]),
                            
                        Forms\Components\Tabs\Tab::make('Visibility')
                            ->schema([
                                Forms\Components\Section::make('Access Control')
                                    ->schema([
                                        Forms\Components\Toggle::make('public')
                                            ->label('Public Setting')
                                            ->default(false)
                                            ->helperText('Public settings are accessible via API without authentication'),
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
                Tables\Columns\TextColumn::make('key')
                    ->label('Setting Key')
                    ->searchable()
                    ->weight(FontWeight::Bold)
                    ->copyable(),
                    
                Tables\Columns\TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'string' => 'gray',
                        'integer', 'float' => 'info',
                        'boolean' => 'success',
                        'array', 'object' => 'warning',
                        'email' => 'purple',
                        'url' => 'cyan',
                        'color' => 'pink',
                        'date', 'datetime' => 'orange',
                        default => 'gray',
                    }),
                    
                Tables\Columns\TextColumn::make('display_value')
                    ->label('Value')
                    ->wrap()
                    ->limit(50)
                    ->tooltip(fn ($record) => $record->display_value),
                    
                Tables\Columns\TextColumn::make('description')
                    ->wrap()
                    ->limit(60)
                    ->toggleable(),
                    
                Tables\Columns\IconColumn::make('public')
                    ->label('Public')
                    ->boolean()
                    ->trueIcon('heroicon-o-globe-alt')
                    ->falseIcon('heroicon-o-lock-closed')
                    ->trueColor('success')
                    ->falseColor('danger'),
                    
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Last Modified')
                    ->dateTime()
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options([
                        'string' => 'Text',
                        'integer' => 'Number (Integer)',
                        'float' => 'Number (Decimal)',
                        'boolean' => 'True/False',
                        'array' => 'JSON Array',
                        'object' => 'JSON Object',
                        'email' => 'Email Address',
                        'url' => 'URL',
                        'color' => 'Color',
                        'date' => 'Date',
                        'datetime' => 'Date & Time',
                    ]),
                    
                TernaryFilter::make('public')
                    ->label('Visibility')
                    ->placeholder('All settings')
                    ->trueLabel('Public only')
                    ->falseLabel('Private only'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('make_public')
                        ->label('Make Public')
                        ->icon('heroicon-o-globe-alt')
                        ->action(fn ($records) => $records->each->update(['public' => true]))
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\BulkAction::make('make_private')
                        ->label('Make Private')
                        ->icon('heroicon-o-lock-closed')
                        ->action(fn ($records) => $records->each->update(['public' => false]))
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->defaultSort('key');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Tabs::make('Setting Details')
                    ->tabs([
                        Infolists\Components\Tabs\Tab::make('Overview')
                            ->schema([
                                Infolists\Components\Section::make('Setting Information')
                                    ->schema([
                                        Infolists\Components\TextEntry::make('key')
                                            ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                                            ->weight(FontWeight::Bold)
                                            ->copyable(),
                                        Infolists\Components\TextEntry::make('type')
                                            ->badge()
                                            ->color('primary'),
                                        Infolists\Components\TextEntry::make('display_value')
                                            ->label('Current Value')
                                            ->prose(),
                                        Infolists\Components\TextEntry::make('description')
                                            ->prose(),
                                        Infolists\Components\IconEntry::make('public')
                                            ->label('Public Access')
                                            ->boolean(),
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
            'index' => Pages\ListSiteSettings::route('/'),
            'create' => Pages\CreateSiteSetting::route('/create'),
            'view' => Pages\ViewSiteSetting::route('/{record}'),
            'edit' => Pages\EditSiteSetting::route('/{record}/edit'),
        ];
    }
    
    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }
}
