<?php

/**
 * OTA Business Services Layer
 * 
 * This file provides an overview of all business logic services in the OTA platform.
 * Each service handles specific domain responsibilities and can be injected as dependencies.
 * 
 * Usage Example:
 * 
 * In a Controller:
 * public function __construct(
 *     private BookingService $bookingService,
 *     private PricingService $pricingService
 * ) {}
 * 
 * Or using app() helper:
 * $bookingService = app(BookingService::class);
 */

// Service Classes Available:

/**
 * BookingService
 * 
 * Handles all booking-related business logic including:
 * - searchAvailability($criteria) - Search for available rooms
 * - createBooking($data) - Create new bookings with validation
 * - cancelBooking($bookingId, $reason) - Cancel bookings
 * - confirmBooking($bookingId) - Confirm bookings after payment
 * - getBookingStats($resortId, $period) - Dashboard statistics
 * 
 * Example Usage:
 * $available = $bookingService->searchAvailability([
 *     'check_in' => '2024-03-01',
 *     'check_out' => '2024-03-05',
 *     'adults' => 2,
 *     'children' => 1
 * ]);
 */

/**
 * InventoryService
 * 
 * Manages room inventory and availability:
 * - checkAvailability($ratePlanId, $checkIn, $checkOut, $rooms) - Check room availability
 * - reserveInventory($bookingId) - Reserve rooms for pending bookings
 * - confirmBookingInventory($bookingId) - Move reserved to booked
 * - releaseReservation($bookingId) - Release reserved rooms
 * - getInventoryCalendar($ratePlanId, $start, $end) - Get availability calendar
 * - getLowInventoryAlerts($threshold, $daysAhead) - Get low stock alerts
 * - cleanupExpiredReservations($minutes) - Clean up expired reservations
 * 
 * Example Usage:
 * $available = $inventoryService->checkAvailability(123, '2024-03-01', '2024-03-05', 1);
 * $calendar = $inventoryService->getInventoryCalendar(123, '2024-03-01', '2024-03-31');
 */

/**
 * PricingService
 * 
 * Handles all pricing calculations and yield management:
 * - calculateTotalPrice($ratePlanId, $checkIn, $checkOut, $promoCode) - Calculate booking total
 * - calculateBasePrice($ratePlanId, $checkIn, $checkOut) - Base price without discounts
 * - getNightlyBreakdown($ratePlanId, $checkIn, $checkOut, $promotion) - Nightly price breakdown
 * - convertCurrency($amount, $from, $to) - Currency conversion
 * - calculateDynamicPrice($ratePlanId, $date, $basePrice, $occupancy) - Dynamic pricing
 * - getPricingRecommendations($ratePlanId, $start, $end) - Yield management recommendations
 * - getCompetitorAnalysis($ratePlanId, $checkIn, $checkOut) - Competitor price analysis
 * 
 * Example Usage:
 * $pricing = $pricingService->calculateTotalPrice(123, '2024-03-01', '2024-03-05', 'SAVE20');
 * $breakdown = $pricingService->getNightlyBreakdown(123, '2024-03-01', '2024-03-05');
 */

/**
 * PaymentService
 * 
 * Integrates with Stripe for payment processing:
 * - createPaymentIntent($bookingId, $currency) - Create Stripe payment intent
 * - confirmPayment($paymentIntentId) - Confirm successful payment
 * - handleFailedPayment($paymentIntentId, $reason) - Handle payment failures
 * - processRefund($bookingId, $amount, $reason) - Process refunds
 * - handleWebhook($payload, $signature) - Handle Stripe webhooks
 * - getPaymentStats($start, $end, $resortId) - Payment statistics
 * - getTransactionHistory($bookingId) - Transaction history for booking
 * 
 * Example Usage:
 * $paymentIntent = $paymentService->createPaymentIntent($booking->id, 'usd');
 * $refund = $paymentService->processRefund($booking->id, 100.00, 'Customer request');
 */

/**
 * NotificationService
 * 
 * Manages email and SMS communications:
 * - sendBookingConfirmation($booking) - Send booking confirmation email
 * - sendBookingCancellation($booking) - Send cancellation email
 * - sendPaymentConfirmation($booking) - Send payment confirmation
 * - sendCheckInReminder($booking) - Send SMS check-in reminder
 * - notifyResortManagerNewBooking($booking) - Notify resort managers
 * - notifyAdminHighValueBooking($booking) - Notify admins of high-value bookings
 * - sendScheduledCheckInReminders() - Send scheduled reminders (for cron)
 * - getNotificationStats($start, $end) - Notification statistics
 * 
 * Example Usage:
 * $notificationService->sendBookingConfirmation($booking);
 * $notificationService->sendCheckInReminder($booking);
 */

/**
 * ResortService
 * 
 * Manages resort data and operations:
 * - getResortWithAvailability($resortId, $checkIn, $checkOut, $adults, $children) - Resort with availability
 * - searchResorts($filters) - Search resorts with filtering
 * - getResortDashboardStats($resortId, $period) - Resort performance stats
 * - createResort($data) - Create new resort
 * - updateResort($resortId, $data) - Update resort information
 * - createRoomType($resortId, $data) - Create room type
 * - createRatePlan($roomTypeId, $data) - Create rate plan
 * - getResortAvailabilityCalendar($resortId, $start, $end) - Availability calendar
 * - getResortPerformanceComparison($resortIds, $period) - Compare multiple resorts
 * - archiveResort($resortId, $reason) - Archive/deactivate resort
 * 
 * Example Usage:
 * $resort = $resortService->getResortWithAvailability(1, '2024-03-01', '2024-03-05', 2, 1);
 * $stats = $resortService->getResortDashboardStats(1, '30_days');
 */

/**
 * Service Integration Examples
 * 
 * These services are designed to work together. Here are common integration patterns:
 */

/**
 * Complete Booking Flow Example:
 * 
 * // 1. Search for availability
 * $available = app(BookingService::class)->searchAvailability([
 *     'check_in' => '2024-03-01',
 *     'check_out' => '2024-03-05',
 *     'adults' => 2
 * ]);
 * 
 * // 2. Calculate pricing with promotion
 * $pricing = app(PricingService::class)->calculateTotalPrice(
 *     $ratePlanId, '2024-03-01', '2024-03-05', 'SAVE20'
 * );
 * 
 * // 3. Create booking
 * $booking = app(BookingService::class)->createBooking([
 *     'rate_plan_id' => $ratePlanId,
 *     'check_in' => '2024-03-01',
 *     'check_out' => '2024-03-05',
 *     'adults' => 2,
 *     'guest' => $guestData,
 *     'promo_code' => 'SAVE20'
 * ]);
 * 
 * // 4. Reserve inventory
 * app(InventoryService::class)->reserveInventory($booking->id);
 * 
 * // 5. Create payment intent
 * $paymentIntent = app(PaymentService::class)->createPaymentIntent($booking->id, 'usd');
 * 
 * // 6. After successful payment (webhook or confirmation):
 * app(PaymentService::class)->confirmPayment($paymentIntentId);
 * app(BookingService::class)->confirmBooking($booking->id);
 * app(InventoryService::class)->confirmBookingInventory($booking->id);
 * app(NotificationService::class)->sendBookingConfirmation($booking);
 * app(NotificationService::class)->notifyResortManagerNewBooking($booking);
 */

/**
 * Resort Dashboard Example:
 * 
 * $resortService = app(ResortService::class);
 * $bookingService = app(BookingService::class);
 * $inventoryService = app(InventoryService::class);
 * 
 * // Get resort performance stats
 * $stats = $resortService->getResortDashboardStats($resortId, '30_days');
 * 
 * // Get booking statistics
 * $bookingStats = $bookingService->getBookingStats($resortId, '30_days');
 * 
 * // Get low inventory alerts
 * $alerts = $inventoryService->getLowInventoryAlerts(3, 30);
 * 
 * // Get availability calendar
 * $calendar = $resortService->getResortAvailabilityCalendar(
 *     $resortId, 
 *     now()->toDateString(), 
 *     now()->addMonth()->toDateString()
 * );
 */

/**
 * Yield Management Example:
 * 
 * $pricingService = app(PricingService::class);
 * $inventoryService = app(InventoryService::class);
 * 
 * // Get pricing recommendations based on occupancy
 * $recommendations = $pricingService->getPricingRecommendations(
 *     $ratePlanId, 
 *     now()->toDateString(),
 *     now()->addMonth()->toDateString()
 * );
 * 
 * // Get occupancy statistics
 * $occupancyStats = $inventoryService->getOccupancyStats(
 *     $ratePlanId,
 *     now()->toDateString(),
 *     now()->addMonth()->toDateString()
 * );
 * 
 * // Calculate dynamic pricing
 * foreach ($recommendations as $recommendation) {
 *     $dynamicPrice = $pricingService->calculateDynamicPrice(
 *         $ratePlanId,
 *         $recommendation['date'],
 *         $recommendation['current_price'],
 *         $recommendation['occupancy_rate'] / 100
 *     );
 * }
 */

/**
 * Error Handling
 * 
 * All services use exceptions for error handling. Wrap service calls in try-catch blocks:
 * 
 * try {
 *     $booking = app(BookingService::class)->createBooking($data);
 * } catch (Exception $e) {
 *     Log::error('Booking creation failed: ' . $e->getMessage());
 *     return response()->json(['error' => 'Booking failed'], 400);
 * }
 */

/**
 * Service Dependencies
 * 
 * Some services depend on others:
 * - BookingService uses InventoryService and PricingService
 * - PaymentService uses InventoryService and BookingService  
 * - NotificationService works with all booking-related services
 * 
 * These dependencies are automatically resolved by Laravel's service container.
 */
