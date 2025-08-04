<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AuditLogResource\Pages;
use App\Models\AuditLog;
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

class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Administration';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'action';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Audit Log Details')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('user_id')
                                    ->label('User')
                                    ->relationship('user', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->nullable(),

                                Forms\Components\TextInput::make('user_type')
                                    ->label('User Type')
                                    ->maxLength(50)
                                    ->placeholder('admin, guest, system'),
                            ]),

                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('action')
                                    ->label('Action')
                                    ->required()
                                    ->maxLength(100)
                                    ->placeholder('create, update, delete, login'),

                                Forms\Components\TextInput::make('model_type')
                                    ->label('Model Type')
                                    ->maxLength(100)
                                    ->placeholder('App\Models\Booking'),

                                Forms\Components\TextInput::make('model_id')
                                    ->label('Model ID')
                                    ->numeric()
                                    ->nullable(),
                            ]),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('event_type')
                                    ->label('Event Type')
                                    ->options([
                                        'authentication' => 'Authentication',
                                        'authorization' => 'Authorization',
                                        'data_access' => 'Data Access',
                                        'data_modification' => 'Data Modification',
                                        'system_configuration' => 'System Configuration',
                                        'user_management' => 'User Management',
                                        'booking_management' => 'Booking Management',
                                        'payment_processing' => 'Payment Processing',
                                        'report_generation' => 'Report Generation',
                                        'api_access' => 'API Access',
                                        'security_event' => 'Security Event',
                                        'error' => 'Error',
                                    ])
                                    ->nullable(),

                                Forms\Components\Select::make('severity')
                                    ->label('Severity')
                                    ->options([
                                        'low' => 'Low',
                                        'medium' => 'Medium',
                                        'high' => 'High',
                                        'critical' => 'Critical',
                                    ])
                                    ->default('medium'),
                            ]),

                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->rows(3)
                            ->columnSpanFull()
                            ->placeholder('Detailed description of the action performed'),
                    ]),

                Forms\Components\Section::make('Request Information')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('ip_address')
                                    ->label('IP Address')
                                    ->maxLength(45)
                                    ->placeholder('192.168.1.1'),

                                Forms\Components\TextInput::make('user_agent')
                                    ->label('User Agent')
                                    ->maxLength(500)
                                    ->placeholder('Browser/device information'),
                            ]),

                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('url')
                                    ->label('URL')
                                    ->maxLength(500)
                                    ->placeholder('Request URL'),

                                Forms\Components\TextInput::make('method')
                                    ->label('HTTP Method')
                                    ->maxLength(10)
                                    ->placeholder('GET, POST, PUT, DELETE'),

                                Forms\Components\TextInput::make('session_id')
                                    ->label('Session ID')
                                    ->maxLength(100),
                            ]),
                    ]),

                Forms\Components\Section::make('Data Changes')
                    ->schema([
                        Forms\Components\Textarea::make('old_values')
                            ->label('Old Values (JSON)')
                            ->rows(5)
                            ->columnSpanFull()
                            ->placeholder('Previous data values in JSON format'),

                        Forms\Components\Textarea::make('new_values')
                            ->label('New Values (JSON)')
                            ->rows(5)
                            ->columnSpanFull()
                            ->placeholder('New data values in JSON format'),

                        Forms\Components\KeyValue::make('metadata')
                            ->label('Additional Metadata')
                            ->keyLabel('Key')
                            ->valueLabel('Value')
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Timestamps')
                    ->schema([
                        Forms\Components\DateTimePicker::make('created_at')
                            ->label('Created At')
                            ->default(now())
                            ->disabled(),
                    ])
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Timestamp')
                    ->dateTime('M j, Y g:i A')
                    ->sortable()
                    ->weight(FontWeight::Bold),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('User')
                    ->searchable()
                    ->sortable()
                    ->placeholder('System'),

                Tables\Columns\TextColumn::make('action')
                    ->label('Action')
                    ->searchable()
                    ->sortable()
                    ->badge(),

                Tables\Columns\BadgeColumn::make('event_type')
                    ->label('Event Type')
                    ->colors([
                        'success' => 'authentication',
                        'info' => 'authorization',
                        'primary' => 'data_access',
                        'warning' => 'data_modification',
                        'purple' => 'system_configuration',
                        'gray' => 'user_management',
                        'blue' => 'booking_management',
                        'green' => 'payment_processing',
                        'orange' => 'report_generation',
                        'indigo' => 'api_access',
                        'danger' => 'security_event',
                        'red' => 'error',
                    ]),

                Tables\Columns\BadgeColumn::make('severity')
                    ->label('Severity')
                    ->colors([
                        'success' => 'low',
                        'warning' => 'medium',
                        'danger' => 'high',
                        'red' => 'critical',
                    ]),

                Tables\Columns\TextColumn::make('model_type')
                    ->label('Model')
                    ->formatStateUsing(fn (string $state): string => class_basename($state))
                    ->toggleable(),

                Tables\Columns\TextColumn::make('model_id')
                    ->label('ID')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP Address')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('method')
                    ->label('Method')
                    ->badge()
                    ->colors([
                        'success' => 'GET',
                        'info' => 'POST',
                        'warning' => 'PUT',
                        'danger' => 'DELETE',
                    ])
                    ->toggleable(),

                Tables\Columns\TextColumn::make('description')
                    ->label('Description')
                    ->limit(50)
                    ->searchable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('user_id')
                    ->label('User')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('action')
                    ->multiple()
                    ->options([
                        'create' => 'Create',
                        'read' => 'Read',
                        'update' => 'Update',
                        'delete' => 'Delete',
                        'login' => 'Login',
                        'logout' => 'Logout',
                        'failed_login' => 'Failed Login',
                        'password_reset' => 'Password Reset',
                        'export' => 'Export',
                        'import' => 'Import',
                    ]),

                Tables\Filters\SelectFilter::make('event_type')
                    ->multiple()
                    ->options([
                        'authentication' => 'Authentication',
                        'authorization' => 'Authorization',
                        'data_access' => 'Data Access',
                        'data_modification' => 'Data Modification',
                        'system_configuration' => 'System Configuration',
                        'user_management' => 'User Management',
                        'booking_management' => 'Booking Management',
                        'payment_processing' => 'Payment Processing',
                        'report_generation' => 'Report Generation',
                        'api_access' => 'API Access',
                        'security_event' => 'Security Event',
                        'error' => 'Error',
                    ]),

                Tables\Filters\SelectFilter::make('severity')
                    ->multiple()
                    ->options([
                        'low' => 'Low',
                        'medium' => 'Medium',
                        'high' => 'High',
                        'critical' => 'Critical',
                    ]),

                Tables\Filters\Filter::make('date_range')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')
                            ->label('From Date'),
                        Forms\Components\DatePicker::make('created_until')
                            ->label('Until Date'),
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

                Tables\Filters\Filter::make('today')
                    ->label('Today')
                    ->query(fn (Builder $query): Builder => $query->whereDate('created_at', today())),

                Tables\Filters\Filter::make('security_events')
                    ->label('Security Events')
                    ->query(fn (Builder $query): Builder => 
                        $query->whereIn('event_type', ['security_event', 'authentication'])
                              ->orWhere('severity', 'high')
                              ->orWhere('severity', 'critical')
                    ),

                Tables\Filters\Filter::make('failed_logins')
                    ->label('Failed Logins')
                    ->query(fn (Builder $query): Builder => $query->where('action', 'failed_login')),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                // Note: Audit logs should typically be read-only, so no edit/delete actions
            ])
            ->bulkActions([
                // Typically audit logs should not have bulk delete, but can have export
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('export')
                        ->label('Export Selected')
                        ->icon('heroicon-m-arrow-down-tray')
                        ->color('info')
                        ->action(function (Collection $records): void {
                            // Export logic would go here
                        }),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->poll('30s'); // Auto-refresh every 30 seconds
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Event Information')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('created_at')
                                    ->label('Timestamp')
                                    ->dateTime()
                                    ->weight(FontWeight::Bold),
                                    
                                Infolists\Components\TextEntry::make('user.name')
                                    ->label('User')
                                    ->placeholder('System User'),

                                Infolists\Components\TextEntry::make('action')
                                    ->badge(),

                                Infolists\Components\TextEntry::make('event_type')
                                    ->badge(),

                                Infolists\Components\TextEntry::make('severity')
                                    ->badge(),

                                Infolists\Components\TextEntry::make('model_type')
                                    ->label('Affected Model')
                                    ->formatStateUsing(fn (string $state): string => class_basename($state)),
                            ]),

                        Infolists\Components\TextEntry::make('description')
                            ->columnSpanFull(),
                    ]),

                Infolists\Components\Section::make('Request Details')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('ip_address')
                                    ->copyable(),

                                Infolists\Components\TextEntry::make('method')
                                    ->badge(),

                                Infolists\Components\TextEntry::make('url')
                                    ->columnSpanFull()
                                    ->copyable(),

                                Infolists\Components\TextEntry::make('user_agent')
                                    ->columnSpanFull()
                                    ->limit(100),
                            ]),
                    ]),

                Infolists\Components\Section::make('Data Changes')
                    ->schema([
                        Infolists\Components\TextEntry::make('old_values')
                            ->label('Previous Values')
                            ->formatStateUsing(fn (string $state): string => json_encode(json_decode($state), JSON_PRETTY_PRINT))
                            ->columnSpanFull(),

                        Infolists\Components\TextEntry::make('new_values')
                            ->label('New Values')
                            ->formatStateUsing(fn (string $state): string => json_encode(json_decode($state), JSON_PRETTY_PRINT))
                            ->columnSpanFull(),
                    ])
                    ->visible(fn (AuditLog $record): bool => !empty($record->old_values) || !empty($record->new_values))
                    ->collapsed(),

                Infolists\Components\Section::make('Additional Information')
                    ->schema([
                        Infolists\Components\KeyValueEntry::make('metadata')
                            ->columnSpanFull(),
                    ])
                    ->visible(fn (AuditLog $record): bool => !empty($record->metadata))
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
            'index' => Pages\ListAuditLogs::route('/'),
            'view' => Pages\ViewAuditLog::route('/{record}'),
            // Note: No create/edit pages as audit logs should be system-generated
        ];
    }

    public static function canCreate(): bool
    {
        return false; // Audit logs should only be created by the system
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('severity', 'critical')
            ->orWhere('severity', 'high')
            ->whereDate('created_at', today())
            ->count();
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'danger';
    }
}
