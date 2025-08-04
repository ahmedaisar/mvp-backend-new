<?php

namespace App\Services;

use App\Models\CommunicationTemplate;
use App\Models\Booking;
use App\Models\User;
use App\Models\GuestProfile;
use App\Models\SiteSetting;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Mail\Mailable;
use Exception;

class NotificationService
{
    /**
     * Send booking confirmation email
     */
    public function sendBookingConfirmation(Booking $booking)
    {
        try {
            $template = CommunicationTemplate::where('type', 'email')
                ->where('name', 'booking_confirmation')
                ->where('active', true)
                ->first();

            if (!$template) {
                throw new Exception('Booking confirmation email template not found');
            }

            $content = $this->replaceTemplateVariables($template->content, $booking);
            $subject = $this->replaceTemplateVariables($template->subject, $booking);

            $this->sendEmail(
                $booking->guestProfile->email,
                $subject,
                $content,
                $booking->guestProfile->full_name
            );

            // Log the notification
            AuditLog::log('notification_sent', $booking, null, [
                'type' => 'booking_confirmation',
                'recipient' => $booking->guestProfile->email,
                'template_id' => $template->id,
            ]);

            return true;

        } catch (Exception $e) {
            Log::error('Failed to send booking confirmation', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Send booking cancellation email
     */
    public function sendBookingCancellation(Booking $booking)
    {
        try {
            $template = CommunicationTemplate::where('type', 'email')
                ->where('name', 'booking_cancellation')
                ->where('active', true)
                ->first();

            if (!$template) {
                throw new Exception('Booking cancellation email template not found');
            }

            $content = $this->replaceTemplateVariables($template->content, $booking);
            $subject = $this->replaceTemplateVariables($template->subject, $booking);

            $this->sendEmail(
                $booking->guestProfile->email,
                $subject,
                $content,
                $booking->guestProfile->full_name
            );

            // Log the notification
            AuditLog::log('notification_sent', $booking, null, [
                'type' => 'booking_cancellation',
                'recipient' => $booking->guestProfile->email,
                'template_id' => $template->id,
            ]);

            return true;

        } catch (Exception $e) {
            Log::error('Failed to send booking cancellation', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Send payment confirmation email
     */
    public function sendPaymentConfirmation(Booking $booking)
    {
        try {
            $template = CommunicationTemplate::where('type', 'email')
                ->where('name', 'payment_confirmation')
                ->where('active', true)
                ->first();

            if (!$template) {
                throw new Exception('Payment confirmation email template not found');
            }

            $content = $this->replaceTemplateVariables($template->content, $booking);
            $subject = $this->replaceTemplateVariables($template->subject, $booking);

            $this->sendEmail(
                $booking->guestProfile->email,
                $subject,
                $content,
                $booking->guestProfile->full_name
            );

            // Log the notification
            AuditLog::log('notification_sent', $booking, null, [
                'type' => 'payment_confirmation',
                'recipient' => $booking->guestProfile->email,
                'template_id' => $template->id,
            ]);

            return true;

        } catch (Exception $e) {
            Log::error('Failed to send payment confirmation', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Send check-in reminder SMS
     */
    public function sendCheckInReminder(Booking $booking)
    {
        try {
            if (!$booking->guestProfile->phone) {
                Log::warning('Cannot send SMS reminder - no phone number', [
                    'booking_id' => $booking->id,
                ]);
                return false;
            }

            $template = CommunicationTemplate::where('type', 'sms')
                ->where('name', 'checkin_reminder')
                ->where('active', true)
                ->first();

            if (!$template) {
                throw new Exception('Check-in reminder SMS template not found');
            }

            $content = $this->replaceTemplateVariables($template->content, $booking);

            $this->sendSMS($booking->guestProfile->phone, $content);

            // Log the notification
            AuditLog::log('notification_sent', $booking, null, [
                'type' => 'checkin_reminder',
                'recipient' => $booking->guestProfile->phone,
                'template_id' => $template->id,
            ]);

            return true;

        } catch (Exception $e) {
            Log::error('Failed to send check-in reminder SMS', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Send resort manager notification for new booking
     */
    public function notifyResortManagerNewBooking(Booking $booking)
    {
        try {
            // Find resort managers for this resort
            $resortManagers = User::where('role', 'resort_manager')
                ->whereJsonContains('resort_access', $booking->resort_id)
                ->get();

            if ($resortManagers->isEmpty()) {
                Log::warning('No resort managers found for booking notification', [
                    'booking_id' => $booking->id,
                    'resort_id' => $booking->resort_id,
                ]);
                return false;
            }

            $template = CommunicationTemplate::where('type', 'email')
                ->where('name', 'new_booking_notification')
                ->where('active', true)
                ->first();

            if (!$template) {
                throw new Exception('New booking notification template not found');
            }

            foreach ($resortManagers as $manager) {
                $content = $this->replaceTemplateVariables($template->content, $booking);
                $subject = $this->replaceTemplateVariables($template->subject, $booking);

                $this->sendEmail($manager->email, $subject, $content, $manager->name);
            }

            // Log the notification
            AuditLog::log('notification_sent', $booking, null, [
                'type' => 'new_booking_notification',
                'recipients' => $resortManagers->pluck('email')->toArray(),
                'template_id' => $template->id,
            ]);

            return true;

        } catch (Exception $e) {
            Log::error('Failed to send resort manager notification', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Send admin notification for high-value booking
     */
    public function notifyAdminHighValueBooking(Booking $booking)
    {
        try {
            $highValueThreshold = SiteSetting::getValue('notifications.high_value_booking_threshold', 5000);
            
            if ($booking->total_price_usd < $highValueThreshold) {
                return false; // Not a high-value booking
            }

            $admins = User::where('role', 'admin')->get();

            if ($admins->isEmpty()) {
                return false;
            }

            $template = CommunicationTemplate::where('type', 'email')
                ->where('name', 'high_value_booking')
                ->where('active', true)
                ->first();

            if (!$template) {
                // Use a default template if specific one not found
                $subject = "High Value Booking Alert - #{$booking->id}";
                $content = "A high-value booking of \${$booking->total_price_usd} has been made for {$booking->resort->name}.";
            } else {
                $content = $this->replaceTemplateVariables($template->content, $booking);
                $subject = $this->replaceTemplateVariables($template->subject, $booking);
            }

            foreach ($admins as $admin) {
                $this->sendEmail($admin->email, $subject, $content, $admin->name);
            }

            return true;

        } catch (Exception $e) {
            Log::error('Failed to send admin high-value booking notification', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Replace template variables with actual values
     */
    protected function replaceTemplateVariables($content, Booking $booking)
    {
        $variables = [
            '{{guest_name}}' => $booking->guestProfile->full_name,
            '{{booking_id}}' => $booking->id,
            '{{resort_name}}' => $booking->resort->name,
            '{{room_type}}' => $booking->roomType->name,
            '{{check_in}}' => $booking->check_in->format('F j, Y'),
            '{{check_out}}' => $booking->check_out->format('F j, Y'),
            '{{nights}}' => $booking->nights,
            '{{adults}}' => $booking->adults,
            '{{children}}' => $booking->children,
            '{{total_price}}' => '$' . number_format($booking->total_price_usd, 2),
            '{{total_price_usd}}' => '$' . number_format($booking->total_price_usd, 2),
            '{{special_requests}}' => $booking->special_requests ?? 'None',
            '{{booking_date}}' => $booking->created_at->format('F j, Y'),
            '{{site_name}}' => SiteSetting::getValue('site.name', 'Resort Booking'),
            '{{site_url}}' => SiteSetting::getValue('site.url', 'https://resortbooking.com'),
            '{{support_email}}' => SiteSetting::getValue('contact.support_email', 'support@resortbooking.com'),
            '{{support_phone}}' => SiteSetting::getValue('contact.support_phone', '+960 123-4567'),
        ];

        return str_replace(array_keys($variables), array_values($variables), $content);
    }

    /**
     * Send email using the configured mail driver
     */
    protected function sendEmail($to, $subject, $content, $recipientName = null)
    {
        try {
            Mail::send([], [], function ($message) use ($to, $subject, $content, $recipientName) {
                $message->to($to, $recipientName)
                    ->subject($subject)
                    ->html($content)
                    ->from(
                        SiteSetting::getValue('mail.from_address', 'noreply@resortbooking.com'),
                        SiteSetting::getValue('mail.from_name', 'Resort Booking')
                    );
            });

            return true;

        } catch (Exception $e) {
            Log::error('Failed to send email', [
                'to' => $to,
                'subject' => $subject,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Send SMS using the configured SMS service
     */
    protected function sendSMS($phone, $message)
    {
        try {
            // This would integrate with an SMS provider like Twilio, AWS SNS, etc.
            // For now, we'll just log the SMS
            Log::info('SMS sent', [
                'to' => $phone,
                'message' => $message,
            ]);

            // In a real implementation, you would use an SMS service:
            /*
            $twilioSid = SiteSetting::getValue('sms.twilio_sid');
            $twilioToken = SiteSetting::getValue('sms.twilio_token');
            $twilioFrom = SiteSetting::getValue('sms.twilio_from');
            
            $twilio = new Client($twilioSid, $twilioToken);
            $twilio->messages->create($phone, [
                'from' => $twilioFrom,
                'body' => $message
            ]);
            */

            return true;

        } catch (Exception $e) {
            Log::error('Failed to send SMS', [
                'to' => $phone,
                'message' => $message,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Send scheduled check-in reminders
     */
    public function sendScheduledCheckInReminders()
    {
        // Find bookings with check-in tomorrow
        $tomorrow = now()->addDay()->toDateString();
        
        $bookings = Booking::where('status', 'confirmed')
            ->whereDate('check_in', $tomorrow)
            ->with(['guestProfile', 'resort'])
            ->get();

        $sent = 0;
        $failed = 0;

        foreach ($bookings as $booking) {
            if ($this->sendCheckInReminder($booking)) {
                $sent++;
            } else {
                $failed++;
            }
        }

        Log::info('Scheduled check-in reminders sent', [
            'total_bookings' => $bookings->count(),
            'sent' => $sent,
            'failed' => $failed,
        ]);

        return ['sent' => $sent, 'failed' => $failed];
    }

    /**
     * Send promotional email campaign
     */
    public function sendPromotionalCampaign($recipients, $campaignData)
    {
        try {
            $template = CommunicationTemplate::where('type', 'email')
                ->where('name', $campaignData['template_name'])
                ->where('active', true)
                ->first();

            if (!$template) {
                throw new Exception('Promotional email template not found');
            }

            $sent = 0;
            $failed = 0;

            foreach ($recipients as $recipient) {
                try {
                    // Replace campaign-specific variables
                    $content = $this->replaceCampaignVariables($template->content, $campaignData, $recipient);
                    $subject = $this->replaceCampaignVariables($template->subject, $campaignData, $recipient);

                    $this->sendEmail(
                        $recipient['email'],
                        $subject,
                        $content,
                        $recipient['name'] ?? null
                    );

                    $sent++;

                    // Log successful send
                    AuditLog::log('promotional_email_sent', null, null, [
                        'campaign' => $campaignData['campaign_name'],
                        'recipient' => $recipient['email'],
                        'template_id' => $template->id,
                    ]);

                } catch (Exception $e) {
                    $failed++;
                    Log::error('Failed to send promotional email', [
                        'campaign' => $campaignData['campaign_name'],
                        'recipient' => $recipient['email'],
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return [
                'sent' => $sent,
                'failed' => $failed,
                'total' => count($recipients),
            ];

        } catch (Exception $e) {
            Log::error('Failed to send promotional campaign', [
                'campaign' => $campaignData['campaign_name'] ?? 'unknown',
                'error' => $e->getMessage(),
            ]);

            return [
                'sent' => 0,
                'failed' => count($recipients),
                'total' => count($recipients),
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Send birthday/anniversary greetings
     */
    public function sendSpecialOccasionGreeting(GuestProfile $guest, $occasionType)
    {
        try {
            $templateName = match($occasionType) {
                'birthday' => 'birthday_greeting',
                'anniversary' => 'anniversary_greeting',
                'membership_anniversary' => 'membership_anniversary',
                default => throw new Exception('Unknown occasion type')
            };

            $template = CommunicationTemplate::where('type', 'email')
                ->where('name', $templateName)
                ->where('active', true)
                ->first();

            if (!$template) {
                throw new Exception("{$occasionType} greeting template not found");
            }

            // Add special occasion variables
            $occasionVariables = [
                '{{guest_name}}' => $guest->full_name,
                '{{occasion_type}}' => ucfirst(str_replace('_', ' ', $occasionType)),
                '{{year}}' => now()->year,
                '{{special_offer}}' => $this->getSpecialOffer($guest, $occasionType),
            ];

            $content = str_replace(array_keys($occasionVariables), array_values($occasionVariables), $template->content);
            $subject = str_replace(array_keys($occasionVariables), array_values($occasionVariables), $template->subject);

            $this->sendEmail($guest->email, $subject, $content, $guest->full_name);

            // Log the greeting
            AuditLog::log('special_occasion_greeting_sent', $guest, null, [
                'occasion_type' => $occasionType,
                'template_id' => $template->id,
            ]);

            return true;

        } catch (Exception $e) {
            Log::error('Failed to send special occasion greeting', [
                'guest_id' => $guest->id,
                'occasion_type' => $occasionType,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Send welcome series for new guests
     */
    public function sendWelcomeSeries(GuestProfile $guest, $seriesStep = 1)
    {
        try {
            $templateName = "welcome_series_step_{$seriesStep}";
            
            $template = CommunicationTemplate::where('type', 'email')
                ->where('name', $templateName)
                ->where('active', true)
                ->first();

            if (!$template) {
                // If no more steps in series, return success
                if ($seriesStep > 1) {
                    return true;
                }
                throw new Exception("Welcome series step {$seriesStep} template not found");
            }

            $content = $this->replaceGuestVariables($template->content, $guest);
            $subject = $this->replaceGuestVariables($template->subject, $guest);

            $this->sendEmail($guest->email, $subject, $content, $guest->full_name);

            // Schedule next step if exists
            $nextStep = $seriesStep + 1;
            $nextTemplate = CommunicationTemplate::where('name', "welcome_series_step_{$nextStep}")
                ->where('active', true)
                ->first();

            if ($nextTemplate) {
                // In a real application, you would use a job queue system like Laravel Horizon
                // For now, we'll just log that the next step should be scheduled
                Log::info('Welcome series next step should be scheduled', [
                    'guest_id' => $guest->id,
                    'next_step' => $nextStep,
                    'schedule_for' => now()->addDays(3)->toISOString(),
                ]);
            }

            // Log the welcome email
            AuditLog::log('welcome_series_sent', $guest, null, [
                'series_step' => $seriesStep,
                'template_id' => $template->id,
            ]);

            return true;

        } catch (Exception $e) {
            Log::error('Failed to send welcome series email', [
                'guest_id' => $guest->id,
                'series_step' => $seriesStep,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Send post-stay feedback request
     */
    public function sendFeedbackRequest(Booking $booking)
    {
        try {
            $template = CommunicationTemplate::where('type', 'email')
                ->where('name', 'post_stay_feedback')
                ->where('active', true)
                ->first();

            if (!$template) {
                throw new Exception('Post-stay feedback template not found');
            }

            // Add feedback-specific variables
            $feedbackVariables = [
                '{{feedback_url}}' => $this->generateFeedbackUrl($booking),
                '{{stay_dates}}' => $booking->check_in->format('M j') . ' - ' . $booking->check_out->format('M j, Y'),
                '{{days_since_checkout}}' => $booking->check_out->diffInDays(now()),
            ];

            $allVariables = array_merge(
                $this->getBookingVariables($booking),
                $feedbackVariables
            );

            $content = str_replace(array_keys($allVariables), array_values($allVariables), $template->content);
            $subject = str_replace(array_keys($allVariables), array_values($allVariables), $template->subject);

            $this->sendEmail(
                $booking->guestProfile->email,
                $subject,
                $content,
                $booking->guestProfile->full_name
            );

            // Log the feedback request
            AuditLog::log('feedback_request_sent', $booking, null, [
                'template_id' => $template->id,
                'days_after_checkout' => $booking->check_out->diffInDays(now()),
            ]);

            return true;

        } catch (Exception $e) {
            Log::error('Failed to send feedback request', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Send push notification
     */
    public function sendPushNotification($deviceTokens, $title, $message, $data = [])
    {
        try {
            // This would integrate with Firebase Cloud Messaging, OneSignal, etc.
            // For now, we'll just log the notification
            
            $payload = [
                'title' => $title,
                'message' => $message,
                'data' => $data,
                'device_tokens' => $deviceTokens,
                'sent_at' => now()->toISOString(),
            ];

            Log::info('Push notification sent', $payload);

            // In a real implementation:
            /*
            $fcmApiKey = SiteSetting::getValue('push.fcm_api_key');
            $fcmUrl = 'https://fcm.googleapis.com/fcm/send';
            
            foreach ($deviceTokens as $token) {
                $notification = [
                    'to' => $token,
                    'notification' => [
                        'title' => $title,
                        'body' => $message,
                    ],
                    'data' => $data,
                ];

                $response = Http::withHeaders([
                    'Authorization' => 'key=' . $fcmApiKey,
                    'Content-Type' => 'application/json',
                ])->post($fcmUrl, $notification);
            }
            */

            return true;

        } catch (Exception $e) {
            Log::error('Failed to send push notification', [
                'title' => $title,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Send bulk SMS campaign
     */
    public function sendBulkSMS($recipients, $message, $campaignName = null)
    {
        try {
            $sent = 0;
            $failed = 0;

            foreach ($recipients as $recipient) {
                try {
                    $personalizedMessage = $this->personalizeMessage($message, $recipient);
                    $this->sendSMS($recipient['phone'], $personalizedMessage);
                    $sent++;
                } catch (Exception $e) {
                    $failed++;
                    Log::error('Failed to send bulk SMS', [
                        'recipient' => $recipient['phone'],
                        'campaign' => $campaignName,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Log campaign results
            AuditLog::log('bulk_sms_campaign_sent', null, null, [
                'campaign_name' => $campaignName,
                'sent' => $sent,
                'failed' => $failed,
                'total' => count($recipients),
            ]);

            return [
                'sent' => $sent,
                'failed' => $failed,
                'total' => count($recipients),
            ];

        } catch (Exception $e) {
            Log::error('Failed to send bulk SMS campaign', [
                'campaign' => $campaignName,
                'error' => $e->getMessage(),
            ]);

            return [
                'sent' => 0,
                'failed' => count($recipients),
                'total' => count($recipients),
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get notification statistics
     */
    public function getNotificationStats($startDate = null, $endDate = null)
    {
        $query = AuditLog::where('action', 'notification_sent');

        if ($startDate && $endDate) {
            $query->whereBetween('created_at', [$startDate, $endDate]);
        }

        $notifications = $query->get();

        $stats = [
            'total_notifications' => $notifications->count(),
            'by_type' => [],
            'by_day' => [],
            'success_rate' => 100, // Assuming all logged notifications were successful
        ];

        // Group by notification type
        foreach ($notifications as $notification) {
            $type = $notification->new_values['type'] ?? 'unknown';
            if (!isset($stats['by_type'][$type])) {
                $stats['by_type'][$type] = 0;
            }
            $stats['by_type'][$type]++;
        }

        // Group by day
        foreach ($notifications as $notification) {
            $day = $notification->created_at->toDateString();
            if (!isset($stats['by_day'][$day])) {
                $stats['by_day'][$day] = 0;
            }
            $stats['by_day'][$day]++;
        }

        return $stats;
    }

    /**
     * Helper methods for enhanced notification features
     */
    
    /**
     * Replace campaign-specific variables
     */
    protected function replaceCampaignVariables($content, $campaignData, $recipient)
    {
        $variables = [
            '{{recipient_name}}' => $recipient['name'] ?? 'Valued Guest',
            '{{campaign_name}}' => $campaignData['campaign_name'] ?? '',
            '{{offer_code}}' => $campaignData['offer_code'] ?? '',
            '{{discount_amount}}' => $campaignData['discount_amount'] ?? '',
            '{{valid_until}}' => $campaignData['valid_until'] ?? '',
            '{{unsubscribe_url}}' => $this->generateUnsubscribeUrl($recipient['email']),
        ];

        return str_replace(array_keys($variables), array_values($variables), $content);
    }

    /**
     * Replace guest-specific variables
     */
    protected function replaceGuestVariables($content, GuestProfile $guest)
    {
        $variables = [
            '{{guest_name}}' => $guest->full_name,
            '{{guest_email}}' => $guest->email,
            '{{guest_phone}}' => $guest->phone,
            '{{guest_country}}' => $guest->country,
            '{{member_since}}' => $guest->created_at->format('F Y'),
            '{{loyalty_status}}' => $guest->loyalty_status ?? 'Standard',
            '{{total_stays}}' => $guest->bookings()->count(),
        ];

        return str_replace(array_keys($variables), array_values($variables), $content);
    }

    /**
     * Get booking-related variables
     */
    protected function getBookingVariables(Booking $booking)
    {
        return [
            '{{guest_name}}' => $booking->guestProfile->full_name,
            '{{booking_id}}' => $booking->id,
            '{{resort_name}}' => $booking->resort->name,
            '{{room_type}}' => $booking->roomType->name ?? 'Standard Room',
            '{{check_in}}' => $booking->check_in->format('F j, Y'),
            '{{check_out}}' => $booking->check_out->format('F j, Y'),
            '{{nights}}' => $booking->nights,
            '{{adults}}' => $booking->adults,
            '{{children}}' => $booking->children,
            '{{total_price}}' => '$' . number_format($booking->total_price_usd, 2),
            '{{total_price_usd}}' => '$' . number_format($booking->total_price_usd, 2),
            '{{special_requests}}' => $booking->special_requests ?? 'None',
            '{{booking_date}}' => $booking->created_at->format('F j, Y'),
        ];
    }

    /**
     * Get special offer for occasion
     */
    protected function getSpecialOffer(GuestProfile $guest, $occasionType)
    {
        // This would typically fetch from a promotions/offers system
        return match($occasionType) {
            'birthday' => '20% off your next stay - Happy Birthday!',
            'anniversary' => '15% off + complimentary dinner for two',
            'membership_anniversary' => 'Free room upgrade on your next booking',
            default => '10% off your next booking with us'
        };
    }

    /**
     * Generate feedback URL with token
     */
    protected function generateFeedbackUrl(Booking $booking)
    {
        $token = base64_encode($booking->id . '|' . $booking->guestProfile->email);
        $baseUrl = SiteSetting::getValue('site.url', 'https://resortbooking.com');
        
        return "{$baseUrl}/feedback?token={$token}";
    }

    /**
     * Generate unsubscribe URL
     */
    protected function generateUnsubscribeUrl($email)
    {
        $token = base64_encode($email . '|' . now()->timestamp);
        $baseUrl = SiteSetting::getValue('site.url', 'https://resortbooking.com');
        
        return "{$baseUrl}/unsubscribe?token={$token}";
    }

    /**
     * Personalize message with recipient data
     */
    protected function personalizeMessage($message, $recipient)
    {
        $variables = [
            '{{name}}' => $recipient['name'] ?? 'Guest',
            '{{first_name}}' => explode(' ', $recipient['name'] ?? 'Guest')[0],
        ];

        return str_replace(array_keys($variables), array_values($variables), $message);
    }
}
