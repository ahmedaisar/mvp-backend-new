<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CreateBookingRequest;
use App\Http\Resources\Api\BookingResource;
use App\Models\Booking;
use App\Services\BookingService;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * @group Booking Management
 * 
 * Create, update, and manage resort bookings with payment processing.
 */
class BookingController extends Controller
{
    protected $bookingService;
    protected $paymentService;

    public function __construct(BookingService $bookingService, PaymentService $paymentService)
    {
        $this->bookingService = $bookingService;
        $this->paymentService = $paymentService;
    }

    /**
     * Create a new booking
     */
    public function create(CreateBookingRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            
            // Create the booking
            $booking = $this->bookingService->createBooking($validated);
            
            // Process payment if payment details provided
            if (isset($validated['payment_details'])) {
                $paymentResult = $this->paymentService->createPaymentIntent(
                    $booking->id,
                    $validated['payment_details']['currency'] ?? 'MVR'
                );
                
                if (!$paymentResult || !isset($paymentResult['success']) || !$paymentResult['success']) {
                    // Cancel booking if payment fails
                    $this->bookingService->cancelBooking($booking->id, 'Payment failed');
                    
                    return response()->json([
                        'error' => 'Payment processing failed',
                        'message' => $paymentResult['message'] ?? 'Unknown payment error'
                    ], 400);
                }
            }
            
            return response()->json([
                'success' => true,
                'data' => new BookingResource($booking->fresh(['guest', 'ratePlan.roomType', 'resort'])),
                'message' => 'Booking created successfully'
            ], 201);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to create booking',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get booking details by booking reference
     */
    public function show(string $bookingReference): JsonResponse
    {
        try {
            $booking = Booking::where('booking_reference', $bookingReference)
                ->with(['guest', 'ratePlan.roomType', 'resort', 'payments'])
                ->firstOrFail();
            
            return response()->json([
                'success' => true,
                'data' => new BookingResource($booking)
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Booking not found',
                'message' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Update booking details
     */
    public function update(string $bookingReference, Request $request): JsonResponse
    {
        try {
            $booking = Booking::where('booking_reference', $bookingReference)->firstOrFail();
            
            // Only allow certain fields to be updated
            $allowedFields = ['special_requests', 'contact_phone', 'contact_email'];
            $updateData = $request->only($allowedFields);
            
            if (empty($updateData)) {
                return response()->json([
                    'error' => 'No valid fields to update'
                ], 400);
            }
            
            $booking->update($updateData);
            
            return response()->json([
                'success' => true,
                'data' => new BookingResource($booking->fresh(['guest', 'ratePlan.roomType', 'resort'])),
                'message' => 'Booking updated successfully'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to update booking',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel a booking
     */
    public function cancel(string $bookingReference, Request $request): JsonResponse
    {
        try {
            $reason = $request->input('cancellation_reason', 'Customer request');
            
            $booking = $this->bookingService->cancelBooking($bookingReference, $reason);
            
            return response()->json([
                'success' => true,
                'data' => new BookingResource($booking),
                'message' => 'Booking cancelled successfully'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to cancel booking',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get booking payment status
     */
    public function paymentStatus(string $bookingReference): JsonResponse
    {
        try {
            $booking = Booking::where('booking_reference', $bookingReference)
                ->with('payments')
                ->firstOrFail();
            
            $paymentSummary = [
                'booking_reference' => $booking->booking_reference,
                'total_amount' => $booking->total_amount,
                'paid_amount' => $booking->payments()->where('status', 'completed')->sum('amount'),
                'pending_amount' => $booking->payments()->where('status', 'pending')->sum('amount'),
                'payment_status' => $booking->payment_status,
                'payments' => $booking->payments->map(function ($payment) {
                    return [
                        'id' => $payment->id,
                        'amount' => $payment->amount,
                        'status' => $payment->status,
                        'payment_method' => $payment->payment_method,
                        'transaction_id' => $payment->transaction_id,
                        'created_at' => $payment->created_at,
                    ];
                })
            ];
            
            return response()->json([
                'success' => true,
                'data' => $paymentSummary
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to get payment status',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
