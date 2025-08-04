<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CommunicationTemplateResource\Pages;
use App\Models\CommunicationTemplate;
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

class CommunicationTemplateResource extends Resource
{
    protected static ?string $model = CommunicationTemplate::class;

    protected static ?string $navigationIcon = 'heroicon-o-envelope';

    protected static ?string $navigationGroup = 'Marketing & Promotions';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Template Information')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Template Name')
                                    ->required()
                                    ->maxLength(100)
                                    ->placeholder('Booking Confirmation Email'),

                                Forms\Components\TextInput::make('code')
                                    ->label('Template Code')
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(50)
                                    ->placeholder('booking_confirmation')
                                    ->alphaDash(),
                            ]),

                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\Select::make('type')
                                    ->label('Communication Type')
                                    ->options([
                                        'email' => 'Email',
                                        'sms' => 'SMS',
                                        'push_notification' => 'Push Notification',
                                        'in_app' => 'In-App Notification',
                                    ])
                                    ->required()
                                    ->default('email')
                                    ->reactive(),

                                Forms\Components\Select::make('category')
                                    ->label('Category')
                                    ->options([
                                        'booking' => 'Booking',
                                        'payment' => 'Payment',
                                        'marketing' => 'Marketing',
                                        'reminder' => 'Reminder',
                                        'confirmation' => 'Confirmation',
                                        'cancellation' => 'Cancellation',
                                        'welcome' => 'Welcome',
                                        'feedback' => 'Feedback',
                                        'notification' => 'Notification',
                                    ])
                                    ->required(),

                                Forms\Components\Select::make('trigger_event')
                                    ->label('Trigger Event')
                                    ->options([
                                        'booking_created' => 'Booking Created',
                                        'booking_confirmed' => 'Booking Confirmed',
                                        'booking_cancelled' => 'Booking Cancelled',
                                        'booking_modified' => 'Booking Modified',
                                        'payment_received' => 'Payment Received',
                                        'payment_failed' => 'Payment Failed',
                                        'check_in_reminder' => 'Check-in Reminder',
                                        'check_out_reminder' => 'Check-out Reminder',
                                        'feedback_request' => 'Feedback Request',
                                        'manual' => 'Manual Send',
                                    ])
                                    ->nullable()
                                    ->reactive(),
                            ]),

                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->rows(2)
                            ->columnSpanFull()
                            ->placeholder('Brief description of when this template is used'),
                    ]),

                Forms\Components\Section::make('Email Content')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('subject')
                                    ->label('Subject Line')
                                    ->required()
                                    ->maxLength(200)
                                    ->placeholder('Your booking confirmation - {{booking_reference}}'),

                                Forms\Components\TextInput::make('from_email')
                                    ->label('From Email')
                                    ->email()
                                    ->placeholder('noreply@yourhotel.com'),
                            ]),

                        Forms\Components\RichEditor::make('content')
                            ->label('Content')
                            ->required()
                            ->columnSpanFull()
                            ->placeholder('Email content with variables like {{guest_name}}, {{booking_reference}}, etc.')
                            ->toolbarButtons([
                                'bold',
                                'italic',
                                'underline',
                                'link',
                                'bulletList',
                                'orderedList',
                                'h2',
                                'h3',
                                'blockquote',
                                'codeBlock',
                            ]),
                    ])
                    ->visible(fn (Forms\Get $get): bool => $get('type') === 'email'),

                Forms\Components\Section::make('SMS Content')
                    ->schema([
                        Forms\Components\Textarea::make('content')
                            ->label('SMS Message')
                            ->required()
                            ->rows(4)
                            ->maxLength(160)
                            ->helperText('SMS messages are limited to 160 characters')
                            ->placeholder('Hi {{guest_name}}, your booking {{booking_reference}} is confirmed for {{check_in_date}}'),
                    ])
                    ->visible(fn (Forms\Get $get): bool => $get('type') === 'sms'),

                Forms\Components\Section::make('Push Notification Content')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('push_title')
                                    ->label('Notification Title')
                                    ->required()
                                    ->maxLength(100)
                                    ->placeholder('Booking Confirmed'),

                                Forms\Components\TextInput::make('push_icon')
                                    ->label('Icon')
                                    ->placeholder('booking-check'),
                            ]),

                        Forms\Components\Textarea::make('content')
                            ->label('Notification Body')
                            ->required()
                            ->rows(3)
                            ->maxLength(200)
                            ->placeholder('Your booking {{booking_reference}} has been confirmed'),
                    ])
                    ->visible(fn (Forms\Get $get): bool => $get('type') === 'push_notification'),

                Forms\Components\Section::make('Variables & Personalization')
                    ->schema([
                        Forms\Components\CheckboxList::make('available_variables')
                            ->label('Available Variables')
                            ->options(function (Forms\Get $get): array {
                                $event = $get('trigger_event');
                                if (!$event) {
                                    return [
                                        'guest_name' => 'Guest Name ({{guest_name}})',
                                        'guest_email' => 'Guest Email ({{guest_email}})',
                                        'booking_reference' => 'Booking Reference ({{booking_reference}})',
                                        'check_in_date' => 'Check-in Date ({{check_in_date}})',
                                        'check_out_date' => 'Check-out Date ({{check_out_date}})',
                                        'resort_name' => 'Resort Name ({{resort_name}})',
                                        'room_type' => 'Room Type ({{room_type}})',
                                        'total_amount' => 'Total Amount ({{total_amount}})',
                                        'nights' => 'Number of Nights ({{nights}})',
                                        'adults' => 'Number of Adults ({{adults}})',
                                        'children' => 'Number of Children ({{children}})',
                                    ];
                                }
                                
                                $placeholders = \App\Models\CommunicationTemplate::getAvailablePlaceholders($event);
                                $options = [];
                                foreach ($placeholders as $placeholder) {
                                    $key = str_replace(['{', '}'], '', $placeholder);
                                    $options[$key] = ucwords(str_replace('_', ' ', $key)) . ' (' . $placeholder . ')';
                                }
                                return $options;
                            })
                            ->columns(2)
                            ->helperText('Select variables that can be used in this template')
                            ->reactive(),

                        Forms\Components\KeyValue::make('custom_variables')
                            ->label('Custom Variables')
                            ->keyLabel('Variable Name')
                            ->valueLabel('Default Value')
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Timing & Delivery')
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('send_delay_minutes')
                                    ->label('Send Delay (minutes)')
                                    ->numeric()
                                    ->default(0)
                                    ->helperText('Delay before sending after trigger'),

                                Forms\Components\Select::make('send_time_preference')
                                    ->label('Send Time Preference')
                                    ->options([
                                        'immediate' => 'Immediate',
                                        'business_hours' => 'Business Hours Only',
                                        'specific_time' => 'Specific Time',
                                    ])
                                    ->default('immediate'),

                                Forms\Components\TimePicker::make('preferred_send_time')
                                    ->label('Preferred Send Time')
                                    ->visible(fn (Forms\Get $get): bool => $get('send_time_preference') === 'specific_time'),
                            ]),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Toggle::make('is_active')
                                    ->label('Active')
                                    ->default(true),

                                Forms\Components\Toggle::make('requires_approval')
                                    ->label('Requires Approval')
                                    ->helperText('Manual approval required before sending')
                                    ->default(false),
                            ]),
                    ]),

                Forms\Components\Section::make('Advanced Settings')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('language')
                                    ->label('Language')
                                    ->options([
                                        'en' => 'English',
                                        'es' => 'Spanish',
                                        'fr' => 'French',
                                        'de' => 'German',
                                        'it' => 'Italian',
                                    ])
                                    ->default('en'),

                                Forms\Components\TextInput::make('priority')
                                    ->label('Priority')
                                    ->numeric()
                                    ->default(5)
                                    ->minValue(1)
                                    ->maxValue(10)
                                    ->helperText('1 = Low, 10 = High'),
                            ]),

                        Forms\Components\Textarea::make('fallback_content')
                            ->label('Fallback Content')
                            ->rows(3)
                            ->columnSpanFull()
                            ->helperText('Plain text version for email clients that don\'t support HTML'),

                        Forms\Components\KeyValue::make('metadata')
                            ->label('Additional Metadata')
                            ->keyLabel('Key')
                            ->valueLabel('Value')
                            ->columnSpanFull(),
                    ])
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Template Name')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold),

                Tables\Columns\TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->copyable()
                    ->badge(),

                Tables\Columns\BadgeColumn::make('type')
                    ->label('Type')
                    ->colors([
                        'primary' => 'email',
                        'success' => 'sms',
                        'warning' => 'push_notification',
                        'info' => 'in_app',
                    ]),

                Tables\Columns\BadgeColumn::make('category')
                    ->label('Category')
                    ->colors([
                        'success' => 'booking',
                        'warning' => 'payment',
                        'info' => 'marketing',
                        'gray' => 'reminder',
                        'primary' => 'confirmation',
                        'danger' => 'cancellation',
                        'purple' => 'welcome',
                        'orange' => 'feedback',
                        'blue' => 'notification',
                    ]),

                Tables\Columns\TextColumn::make('trigger_event')
                    ->label('Trigger')
                    ->badge()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('subject')
                    ->label('Subject')
                    ->limit(50)
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Active'),

                Tables\Columns\IconColumn::make('requires_approval')
                    ->label('Approval')
                    ->boolean()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('language')
                    ->label('Lang')
                    ->badge()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->multiple()
                    ->options([
                        'email' => 'Email',
                        'sms' => 'SMS',
                        'push_notification' => 'Push Notification',
                        'in_app' => 'In-App Notification',
                    ]),

                Tables\Filters\SelectFilter::make('category')
                    ->multiple()
                    ->options([
                        'booking' => 'Booking',
                        'payment' => 'Payment',
                        'marketing' => 'Marketing',
                        'reminder' => 'Reminder',
                        'confirmation' => 'Confirmation',
                        'cancellation' => 'Cancellation',
                        'welcome' => 'Welcome',
                        'feedback' => 'Feedback',
                        'notification' => 'Notification',
                    ]),

                Tables\Filters\SelectFilter::make('trigger_event')
                    ->multiple()
                    ->options([
                        'booking_created' => 'Booking Created',
                        'booking_confirmed' => 'Booking Confirmed',
                        'booking_cancelled' => 'Booking Cancelled',
                        'booking_modified' => 'Booking Modified',
                        'payment_received' => 'Payment Received',
                        'payment_failed' => 'Payment Failed',
                        'check_in_reminder' => 'Check-in Reminder',
                        'check_out_reminder' => 'Check-out Reminder',
                        'feedback_request' => 'Feedback Request',
                        'manual' => 'Manual Send',
                    ]),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),

                Tables\Filters\TernaryFilter::make('requires_approval')
                    ->label('Requires Approval'),

                Tables\Filters\SelectFilter::make('language')
                    ->options([
                        'en' => 'English',
                        'es' => 'Spanish',
                        'fr' => 'French',
                        'de' => 'German',
                        'it' => 'Italian',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                
                Tables\Actions\Action::make('preview')
                    ->label('Preview')
                    ->icon('heroicon-m-eye')
                    ->color('info')
                    ->url(fn (CommunicationTemplate $record): string => route('communication-templates.preview', $record))
                    ->openUrlInNewTab(),
                    
                Tables\Actions\Action::make('test_send')
                    ->label('Test Send')
                    ->icon('heroicon-m-paper-airplane')
                    ->color('warning')
                    ->form([
                        Forms\Components\TextInput::make('test_email')
                            ->label('Test Email Address')
                            ->email()
                            ->required()
                            ->visible(fn (CommunicationTemplate $record): bool => $record->type === 'email'),
                            
                        Forms\Components\TextInput::make('test_phone')
                            ->label('Test Phone Number')
                            ->required()
                            ->visible(fn (CommunicationTemplate $record): bool => $record->type === 'sms'),
                    ])
                    ->action(function (CommunicationTemplate $record, array $data): void {
                        // Test send logic would go here
                    }),
                    
                Tables\Actions\Action::make('duplicate')
                    ->label('Duplicate')
                    ->icon('heroicon-m-square-2-stack')
                    ->color('gray')
                    ->action(function (CommunicationTemplate $record): void {
                        $newTemplate = $record->replicate();
                        $newTemplate->name = $record->name . ' (Copy)';
                        $newTemplate->code = $record->code . '_copy';
                        $newTemplate->is_active = false;
                        $newTemplate->save();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    
                    Tables\Actions\BulkAction::make('activate')
                        ->label('Activate')
                        ->icon('heroicon-m-check-circle')
                        ->color('success')
                        ->action(fn (Collection $records) => $records->each(fn (CommunicationTemplate $record) => $record->update(['is_active' => true]))),
                        
                    Tables\Actions\BulkAction::make('deactivate')
                        ->label('Deactivate')
                        ->icon('heroicon-m-x-circle')
                        ->color('danger')
                        ->action(fn (Collection $records) => $records->each(fn (CommunicationTemplate $record) => $record->update(['is_active' => false]))),
                ]),
            ])
            ->defaultSort('updated_at', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Template Information')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('name')
                                    ->weight(FontWeight::Bold),
                                    
                                Infolists\Components\TextEntry::make('code')
                                    ->copyable()
                                    ->badge(),

                                Infolists\Components\TextEntry::make('type')
                                    ->badge(),

                                Infolists\Components\TextEntry::make('category')
                                    ->badge(),
                            ]),

                        Infolists\Components\TextEntry::make('description')
                            ->columnSpanFull(),
                    ]),

                Infolists\Components\Section::make('Content')
                    ->schema([
                        Infolists\Components\TextEntry::make('subject')
                            ->visible(fn (CommunicationTemplate $record): bool => $record->type === 'email'),
                            
                        Infolists\Components\TextEntry::make('content')
                            ->html()
                            ->columnSpanFull(),
                    ]),

                Infolists\Components\Section::make('Settings')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\IconEntry::make('is_active')
                                    ->boolean(),

                                Infolists\Components\IconEntry::make('requires_approval')
                                    ->boolean(),

                                Infolists\Components\TextEntry::make('language')
                                    ->badge(),
                            ]),
                    ]),
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
            'index' => Pages\ListCommunicationTemplates::route('/'),
            'create' => Pages\CreateCommunicationTemplate::route('/create'),
            'view' => Pages\ViewCommunicationTemplate::route('/{record}'),
            'edit' => Pages\EditCommunicationTemplate::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('is_active', true)->count();
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'info';
    }
}
