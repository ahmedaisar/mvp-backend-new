<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\SiteSetting;

class SiteSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            [
                'key' => 'tourist_service_fee',
                'value' => ['value' => 12],
                'type' => 'integer',
                'description' => 'Tourist service fee percentage',
                'public' => true,
            ],
            [
                'key' => 'default_currency',
                'value' => ['value' => 'USD'],
                'type' => 'string',
                'description' => 'Default currency for frontend display',
                'public' => true,
            ],
            [
                'key' => 'base_currency',
                'value' => ['value' => 'MVR'],
                'type' => 'string',
                'description' => 'Base currency for calculations',
                'public' => false,
            ],
            [
                'key' => 'gst_rate',
                'value' => ['value' => 8],
                'type' => 'integer',
                'description' => 'GST rate percentage',
                'public' => true,
            ],
            [
                'key' => 'booking_confirmation_hours',
                'value' => ['value' => 24],
                'type' => 'integer',
                'description' => 'Hours to confirm booking before auto-cancellation',
                'public' => false,
            ],
            [
                'key' => 'default_cancellation_hours',
                'value' => ['value' => 24],
                'type' => 'integer',
                'description' => 'Default hours before check-in for free cancellation',
                'public' => true,
            ],
            [
                'key' => 'company_name',
                'value' => ['value' => 'Multi-Resort OTA Platform'],
                'type' => 'string',
                'description' => 'Company name',
                'public' => true,
            ],
            [
                'key' => 'support_email',
                'value' => ['value' => 'support@mvp-grock-ota.com'],
                'type' => 'string',
                'description' => 'Support email address',
                'public' => true,
            ],
            [
                'key' => 'support_phone',
                'value' => ['value' => '+960 123 4567'],
                'type' => 'string',
                'description' => 'Support phone number',
                'public' => true,
            ],
            [
                'key' => 'max_booking_days_ahead',
                'value' => ['value' => 365],
                'type' => 'integer',
                'description' => 'Maximum days ahead for bookings',
                'public' => true,
            ],
            [
                'key' => 'inventory_update_frequency',
                'value' => ['value' => 60],
                'type' => 'integer',
                'description' => 'Inventory update frequency in minutes',
                'public' => false,
            ],
            [
                'key' => 'currency_rates',
                'value' => [
                    'USD_TO_MVR' => 15.42,
                    'EUR_TO_MVR' => 17.23,
                    'GBP_TO_MVR' => 19.87,
                ],
                'type' => 'object',
                'description' => 'Currency exchange rates',
                'public' => true,
            ],
        ];

        foreach ($settings as $setting) {
            SiteSetting::updateOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }
    }
}
