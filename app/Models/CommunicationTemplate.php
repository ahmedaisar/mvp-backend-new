<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class CommunicationTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'type',
        'category',
        'event',
        'description',
        'subject',
        'content',
        'from_email',
        'push_title',
        'push_icon',
        'available_variables',
        'custom_variables',
        'send_delay_minutes',
        'send_time_preference',
        'preferred_send_time',
        'is_active',
        'requires_approval',
        'language',
        'priority',
        'fallback_content',
        'metadata',
        'placeholders', // Keep for backward compatibility
        'active', // Keep for backward compatibility
    ];

    protected $casts = [
        'available_variables' => 'array',
        'custom_variables' => 'array',
        'metadata' => 'array',
        'placeholders' => 'array',
        'is_active' => 'boolean',
        'active' => 'boolean', // Keep for backward compatibility
        'requires_approval' => 'boolean',
        'send_delay_minutes' => 'integer',
        'priority' => 'integer',
        'preferred_send_time' => 'datetime:H:i',
    ];

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orWhere('active', true);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByEvent($query, $event)
    {
        return $query->where('trigger_event', $event)->orWhere('event', $event);
    }

    public function scopeEmail($query)
    {
        return $query->where('type', 'email');
    }

    public function scopeSms($query)
    {
        return $query->where('type', 'sms');
    }

    /**
     * Accessors & Mutators
     */
    public function getSubjectForLocaleAttribute($locale = null)
    {
        return $this->subject;
    }

    public function getContentForLocaleAttribute($locale = null)
    {
        return $this->content;
    }

    // Backward compatibility for 'event' field
    public function getEventAttribute()
    {
        return $this->attributes['event'] ?? $this->trigger_event ?? null;
    }

    public function setEventAttribute($value)
    {
        $this->attributes['event'] = $value;
    }

    // Backward compatibility for 'active' field
    public function getActiveAttribute()
    {
        return $this->is_active ?? $this->attributes['active'] ?? false;
    }

    public function setActiveAttribute($value)
    {
        $this->attributes['is_active'] = $value;
    }

    /**
     * Methods
     */
    public function renderContent($data = [], $locale = null)
    {
        return $this->replacePlaceholders($this->content, $data);
    }

    public function renderSubject($data = [], $locale = null)
    {
        if ($this->type === 'sms') {
            return null; // SMS doesn't have subjects
        }

        return $this->replacePlaceholders($this->subject, $data);
    }

    protected function replacePlaceholders($text, $data)
    {
        // Use both new available_variables and legacy placeholders for compatibility
        $variables = $this->available_variables ?? $this->placeholders ?? [];
        
        if (empty($variables) || empty($data)) {
            return $text;
        }

        foreach ($variables as $variable) {
            $key = str_replace(['{', '}'], '', $variable);
            if (isset($data[$key])) {
                $text = str_replace($variable, $data[$key], $text);
            }
        }

        // Also handle custom variables
        if (!empty($this->custom_variables)) {
            foreach ($this->custom_variables as $customKey => $defaultValue) {
                $placeholder = '{' . $customKey . '}';
                $value = $data[$customKey] ?? $defaultValue;
                $text = str_replace($placeholder, $value, $text);
            }
        }

        return $text;
    }

    /**
     * Static methods
     */
    public static function getTemplate($event, $type = 'email')
    {
        return static::active()
            ->where(function($query) use ($event) {
                $query->where('trigger_event', $event)->orWhere('event', $event);
            })
            ->where('type', $type)
            ->first();
    }

    public static function send($event, $data, $recipient, $type = 'email', $locale = null)
    {
        $template = static::getTemplate($event, $type);
        
        if (!$template) {
            throw new \Exception("No active template found for event: {$event}, type: {$type}");
        }

        $content = $template->renderContent($data, $locale);
        $subject = $template->renderSubject($data, $locale);

        if ($type === 'email') {
            // Here you would integrate with your email service (Mailgun, etc.)
            // For now, we'll just log it or use a generic notification
            Log::info('Email would be sent', [
                'recipient' => $recipient,
                'subject' => $subject,
                'content' => $content
            ]);
            
            // If you have a mail system set up, you could use Laravel's Mail facade
            // \Mail::to($recipient)->send(new \App\Mail\TemplateEmail($subject, $content));
            
        } elseif ($type === 'sms') {
            // Here you would integrate with your SMS service (Twilio, etc.)
            // For now, we'll just log it
            Log::info('SMS would be sent', [
                'recipient' => $recipient,
                'content' => $content
            ]);
        }

        return true;
    }

    /**
     * Get available placeholders for the given event
     */
    public static function getAvailablePlaceholders($event)
    {
        $placeholders = [
            'booking_created' => [
                '{guest_name}',
                '{booking_reference}',
                '{resort_name}',
                '{room_type}',
                '{check_in_date}',
                '{check_out_date}',
                '{total_amount}',
                '{nights}',
                '{adults}',
                '{children}',
            ],
            'booking_confirmed' => [
                '{guest_name}',
                '{booking_reference}',
                '{resort_name}',
                '{room_type}',
                '{check_in_date}',
                '{check_out_date}',
                '{total_amount}',
                '{nights}',
                '{adults}',
                '{children}',
            ],
            'booking_cancelled' => [
                '{guest_name}',
                '{booking_reference}',
                '{resort_name}',
                '{cancellation_reason}',
                '{refund_amount}',
            ],
            'booking_modified' => [
                '{guest_name}',
                '{booking_reference}',
                '{resort_name}',
                '{changes_made}',
            ],
            'payment_received' => [
                '{guest_name}',
                '{booking_reference}',
                '{amount}',
                '{currency}',
                '{transaction_id}',
                '{payment_method}',
            ],
            'payment_failed' => [
                '{guest_name}',
                '{booking_reference}',
                '{amount}',
                '{currency}',
                '{failure_reason}',
            ],
            'check_in_reminder' => [
                '{guest_name}',
                '{booking_reference}',
                '{resort_name}',
                '{check_in_date}',
                '{resort_address}',
                '{resort_phone}',
                '{room_type}',
            ],
            'check_out_reminder' => [
                '{guest_name}',
                '{booking_reference}',
                '{resort_name}',
                '{check_out_date}',
                '{final_bill}',
            ],
            'feedback_request' => [
                '{guest_name}',
                '{booking_reference}',
                '{resort_name}',
                '{stay_dates}',
                '{feedback_url}',
            ],
            // Legacy event mappings for backward compatibility
            'booking_confirmed' => [
                '{guest_name}',
                '{booking_reference}',
                '{resort_name}',
                '{room_type}',
                '{check_in}',
                '{check_out}',
                '{total_amount}',
                '{nights}',
                '{adults}',
                '{children}',
            ],
        ];

        return $placeholders[$event] ?? [];
    }
}
