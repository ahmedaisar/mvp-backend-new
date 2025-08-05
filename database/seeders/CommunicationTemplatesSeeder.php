<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\CommunicationTemplate;

class CommunicationTemplatesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $templates = [
            // Email Templates
            [
                'type' => 'email',
                'name' => 'Booking Confirmation',
                'event' => 'booking_confirmed',
                'code' => 'booking_confirmed_email',
                'category' => 'booking',
                'description' => 'Email sent when a booking is confirmed',
                'subject' => 'Booking Confirmation - {booking_reference}',
                'content' => "Dear {guest_name},\n\nYour booking has been confirmed!\n\nBooking Details:\n- Reference: {booking_reference}\n- Resort: {resort_name}\n- Room Type: {room_type}\n- Check-in: {check_in}\n- Check-out: {check_out}\n- Nights: {nights}\n- Guests: {adults} adults, {children} children\n- Total Amount: {total_amount}\n\nThank you for choosing us!\n\nBest regards,\nMulti-Resort OTA Platform",
                'placeholders' => ['{guest_name}', '{booking_reference}', '{resort_name}', '{room_type}', '{check_in}', '{check_out}', '{nights}', '{adults}', '{children}', '{total_amount}'],
                'available_variables' => ['{guest_name}', '{booking_reference}', '{resort_name}', '{room_type}', '{check_in}', '{check_out}', '{nights}', '{adults}', '{children}', '{total_amount}'],
                'active' => true,
                'is_active' => true,
                'language' => 'en',
                'priority' => 5,
                'send_delay_minutes' => 0,
                'send_time_preference' => 'immediate',
                'requires_approval' => false,
            ],
            [
                'type' => 'email',
                'name' => 'Payment Received',
                'event' => 'payment_received',
                'code' => 'payment_received_email',
                'category' => 'payment',
                'description' => 'Email sent when payment is successfully received',
                'subject' => 'Payment Confirmation - {transaction_id}',
                'content' => "Dear {guest_name},\n\nWe have successfully received your payment.\n\nPayment Details:\n- Booking Reference: {booking_reference}\n- Amount: {amount} {currency}\n- Transaction ID: {transaction_id}\n\nYour booking is now fully confirmed.\n\nBest regards,\nMulti-Resort OTA Platform",
                'placeholders' => ['{guest_name}', '{booking_reference}', '{amount}', '{currency}', '{transaction_id}'],
                'available_variables' => ['{guest_name}', '{booking_reference}', '{amount}', '{currency}', '{transaction_id}'],
                'active' => true,
                'is_active' => true,
                'language' => 'en',
                'priority' => 5,
                'send_delay_minutes' => 0,
                'send_time_preference' => 'immediate',
                'requires_approval' => false,
            ],
            [
                'type' => 'email',
                'name' => 'Check-in Reminder',
                'event' => 'check_in_reminder',
                'code' => 'check_in_reminder_email',
                'category' => 'reminder',
                'description' => 'Email reminder sent before check-in date',
                'subject' => 'Check-in Reminder - {resort_name}',
                'content' => "Dear {guest_name},\n\nThis is a friendly reminder that your check-in is tomorrow!\n\nBooking Details:\n- Reference: {booking_reference}\n- Resort: {resort_name}\n- Check-in Date: {check_in}\n- Address: {resort_address}\n- Phone: {resort_phone}\n\nWe look forward to welcoming you!\n\nBest regards,\nMulti-Resort OTA Platform",
                'placeholders' => ['{guest_name}', '{booking_reference}', '{resort_name}', '{check_in}', '{resort_address}', '{resort_phone}'],
                'available_variables' => ['{guest_name}', '{booking_reference}', '{resort_name}', '{check_in}', '{resort_address}', '{resort_phone}'],
                'active' => true,
                'is_active' => true,
                'language' => 'en',
                'priority' => 4,
                'send_delay_minutes' => 1440, // 24 hours before
                'send_time_preference' => 'business_hours',
                'requires_approval' => false,
            ],
            [
                'type' => 'email',
                'name' => 'Booking Cancellation',
                'event' => 'booking_cancelled',
                'code' => 'booking_cancelled_email',
                'category' => 'booking',
                'description' => 'Email sent when a booking is cancelled',
                'subject' => 'Booking Cancelled - {booking_reference}',
                'content' => "Dear {guest_name},\n\nYour booking has been cancelled as requested.\n\nBooking Reference: {booking_reference}\nResort: {resort_name}\nReason: {cancellation_reason}\n\nIf you have any questions, please contact our support team.\n\nBest regards,\nMulti-Resort OTA Platform",
                'placeholders' => ['{guest_name}', '{booking_reference}', '{resort_name}', '{cancellation_reason}'],
                'available_variables' => ['{guest_name}', '{booking_reference}', '{resort_name}', '{cancellation_reason}'],
                'active' => true,
                'is_active' => true,
                'language' => 'en',
                'priority' => 5,
                'send_delay_minutes' => 0,
                'send_time_preference' => 'immediate',
                'requires_approval' => false,
            ],

            // SMS Templates
            [
                'type' => 'sms',
                'name' => 'Booking Confirmation SMS',
                'event' => 'booking_confirmed',
                'code' => 'booking_confirmed_sms',
                'category' => 'booking',
                'description' => 'SMS sent when a booking is confirmed',
                'subject' => null,
                'content' => "Hi {guest_name}! Your booking {booking_reference} at {resort_name} is confirmed. Check-in: {check_in}. Have a great stay!",
                'placeholders' => ['{guest_name}', '{booking_reference}', '{resort_name}', '{check_in}'],
                'available_variables' => ['{guest_name}', '{booking_reference}', '{resort_name}', '{check_in}'],
                'active' => true,
                'is_active' => true,
                'language' => 'en',
                'priority' => 5,
                'send_delay_minutes' => 0,
                'send_time_preference' => 'immediate',
                'requires_approval' => false,
            ],
            [
                'type' => 'sms',
                'name' => 'Check-in Reminder SMS',
                'event' => 'check_in_reminder',
                'code' => 'check_in_reminder_sms',
                'category' => 'reminder',
                'description' => 'SMS reminder sent before check-in date',
                'subject' => null,
                'content' => "Reminder: Check-in tomorrow at {resort_name}. Booking: {booking_reference}. Contact: {resort_phone}",
                'placeholders' => ['{resort_name}', '{booking_reference}', '{resort_phone}'],
                'available_variables' => ['{resort_name}', '{booking_reference}', '{resort_phone}'],
                'active' => true,
                'is_active' => true,
                'language' => 'en',
                'priority' => 4,
                'send_delay_minutes' => 1440, // 24 hours before
                'send_time_preference' => 'business_hours',
                'requires_approval' => false,
            ],
        ];

        foreach ($templates as $template) {
            CommunicationTemplate::create($template);
        }
    }
}
