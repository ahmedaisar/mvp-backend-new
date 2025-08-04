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
                'subject' => [
                    'en' => 'Booking Confirmation - {booking_reference}',
                    'ru' => 'Подтверждение бронирования - {booking_reference}',
                    'fr' => 'Confirmation de réservation - {booking_reference}',
                ],
                'content' => [
                    'en' => "Dear {guest_name},\n\nYour booking has been confirmed!\n\nBooking Details:\n- Reference: {booking_reference}\n- Resort: {resort_name}\n- Room Type: {room_type}\n- Check-in: {check_in}\n- Check-out: {check_out}\n- Nights: {nights}\n- Guests: {adults} adults, {children} children\n- Total Amount: {total_amount}\n\nThank you for choosing us!\n\nBest regards,\nMulti-Resort OTA Platform",
                    'ru' => "Уважаемый {guest_name},\n\nВаше бронирование подтверждено!\n\nДетали бронирования:\n- Номер: {booking_reference}\n- Курорт: {resort_name}\n- Тип номера: {room_type}\n- Заезд: {check_in}\n- Выезд: {check_out}\n- Ночей: {nights}\n- Гости: {adults} взрослых, {children} детей\n- Общая сумма: {total_amount}\n\nСпасибо за выбор нас!\n\nС уважением,\nMulti-Resort OTA Platform",
                    'fr' => "Cher {guest_name},\n\nVotre réservation a été confirmée!\n\nDétails de la réservation:\n- Référence: {booking_reference}\n- Resort: {resort_name}\n- Type de chambre: {room_type}\n- Arrivée: {check_in}\n- Départ: {check_out}\n- Nuits: {nights}\n- Invités: {adults} adultes, {children} enfants\n- Montant total: {total_amount}\n\nMerci de nous avoir choisis!\n\nCordialement,\nMulti-Resort OTA Platform",
                ],
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
                'subject' => [
                    'en' => 'Payment Confirmation - {transaction_id}',
                    'ru' => 'Подтверждение платежа - {transaction_id}',
                    'fr' => 'Confirmation de paiement - {transaction_id}',
                ],
                'content' => [
                    'en' => "Dear {guest_name},\n\nWe have successfully received your payment.\n\nPayment Details:\n- Booking Reference: {booking_reference}\n- Amount: {amount} {currency}\n- Transaction ID: {transaction_id}\n\nYour booking is now fully confirmed.\n\nBest regards,\nMulti-Resort OTA Platform",
                    'ru' => "Уважаемый {guest_name},\n\nМы успешно получили ваш платеж.\n\nДетали платежа:\n- Номер бронирования: {booking_reference}\n- Сумма: {amount} {currency}\n- ID транзакции: {transaction_id}\n\nВаше бронирование теперь полностью подтверждено.\n\nС уважением,\nMulti-Resort OTA Platform",
                    'fr' => "Cher {guest_name},\n\nNous avons reçu votre paiement avec succès.\n\nDétails du paiement:\n- Référence de réservation: {booking_reference}\n- Montant: {amount} {currency}\n- ID de transaction: {transaction_id}\n\nVotre réservation est maintenant entièrement confirmée.\n\nCordialement,\nMulti-Resort OTA Platform",
                ],
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
                'subject' => [
                    'en' => 'Check-in Reminder - {resort_name}',
                    'ru' => 'Напоминание о заезде - {resort_name}',
                    'fr' => 'Rappel d\'arrivée - {resort_name}',
                ],
                'content' => [
                    'en' => "Dear {guest_name},\n\nThis is a friendly reminder that your check-in is tomorrow!\n\nBooking Details:\n- Reference: {booking_reference}\n- Resort: {resort_name}\n- Check-in Date: {check_in}\n- Address: {resort_address}\n- Phone: {resort_phone}\n\nWe look forward to welcoming you!\n\nBest regards,\nMulti-Resort OTA Platform",
                    'ru' => "Уважаемый {guest_name},\n\nЭто дружеское напоминание о том, что ваш заезд завтра!\n\nДетали бронирования:\n- Номер: {booking_reference}\n- Курорт: {resort_name}\n- Дата заезда: {check_in}\n- Адрес: {resort_address}\n- Телефон: {resort_phone}\n\nМы с нетерпением ждем встречи с вами!\n\nС уважением,\nMulti-Resort OTA Platform",
                    'fr' => "Cher {guest_name},\n\nCeci est un rappel amical que votre arrivée est demain!\n\nDétails de la réservation:\n- Référence: {booking_reference}\n- Resort: {resort_name}\n- Date d'arrivée: {check_in}\n- Adresse: {resort_address}\n- Téléphone: {resort_phone}\n\nNous avons hâte de vous accueillir!\n\nCordialement,\nMulti-Resort OTA Platform",
                ],
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
                'subject' => [
                    'en' => 'Booking Cancelled - {booking_reference}',
                    'ru' => 'Бронирование отменено - {booking_reference}',
                    'fr' => 'Réservation annulée - {booking_reference}',
                ],
                'content' => [
                    'en' => "Dear {guest_name},\n\nYour booking has been cancelled as requested.\n\nBooking Reference: {booking_reference}\nResort: {resort_name}\nReason: {cancellation_reason}\n\nIf you have any questions, please contact our support team.\n\nBest regards,\nMulti-Resort OTA Platform",
                    'ru' => "Уважаемый {guest_name},\n\nВаше бронирование было отменено по вашему запросу.\n\nНомер бронирования: {booking_reference}\nКурорт: {resort_name}\nПричина: {cancellation_reason}\n\nЕсли у вас есть вопросы, пожалуйста, свяжитесь с нашей службой поддержки.\n\nС уважением,\nMulti-Resort OTA Platform",
                    'fr' => "Cher {guest_name},\n\nVotre réservation a été annulée comme demandé.\n\nRéférence de réservation: {booking_reference}\nResort: {resort_name}\nRaison: {cancellation_reason}\n\nSi vous avez des questions, veuillez contacter notre équipe de support.\n\nCordialement,\nMulti-Resort OTA Platform",
                ],
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
                'content' => [
                    'en' => "Hi {guest_name}! Your booking {booking_reference} at {resort_name} is confirmed. Check-in: {check_in}. Have a great stay!",
                    'ru' => "Привет {guest_name}! Ваше бронирование {booking_reference} в {resort_name} подтверждено. Заезд: {check_in}. Приятного отдыха!",
                    'fr' => "Salut {guest_name}! Votre réservation {booking_reference} à {resort_name} est confirmée. Arrivée: {check_in}. Bon séjour!",
                ],
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
                'content' => [
                    'en' => "Reminder: Check-in tomorrow at {resort_name}. Booking: {booking_reference}. Contact: {resort_phone}",
                    'ru' => "Напоминание: Заезд завтра в {resort_name}. Бронирование: {booking_reference}. Контакт: {resort_phone}",
                    'fr' => "Rappel: Arrivée demain à {resort_name}. Réservation: {booking_reference}. Contact: {resort_phone}",
                ],
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
