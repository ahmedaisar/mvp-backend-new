<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\Booking;
use App\Services\PaymentService;
use App\Services\BookingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * @group Payment Webhooks
 * 
 * Handle payment gateway webhooks for real-time payment status updates.
 */
class PaymentWebhookController extends Controller
{
    protected $paymentService;
    protected $bookingService;

    public function __construct(PaymentService $paymentService, BookingService $bookingService)
    {
        $this->paymentService = $paymentService;
        $this->bookingService = $bookingService;
    }

    /**
     * Handle BML (Bank of Maldives) payment webhook
     */
    public function bmlWebhook(Request $request): JsonResponse
    {
        try {
            $payload = $request->all();
            
            Log::info('BML webhook received', $payload);
            
            // Verify webhook signature
            if (!$this->verifyBmlSignature($request)) {
                Log::warning('BML webhook signature verification failed');
                return response()->json(['error' => 'Invalid signature'], 401);
            }
            
            $transactionId = $payload['transaction_id'] ?? null;
            $status = $payload['status'] ?? null;
            $amount = $payload['amount'] ?? null;
            
            if (!$transactionId || !$status) {
                return response()->json(['error' => 'Missing required fields'], 400);
            }
            
            // Find payment by transaction ID
            $payment = Transaction::where('gateway_transaction_id', $transactionId)->first();
            
            if (!$payment) {
                Log::warning('Payment not found for transaction ID: ' . $transactionId);
                return response()->json(['error' => 'Payment not found'], 404);
            }
            
            // Update payment status based on webhook
            $this->updatePaymentStatus($payment, $status, $payload);
            
            return response()->json(['status' => 'success']);
            
        } catch (\Exception $e) {
            Log::error('BML webhook processing failed: ' . $e->getMessage());
            return response()->json(['error' => 'Webhook processing failed'], 500);
        }
    }

    /**
     * Handle Stripe payment webhook
     */
    public function stripeWebhook(Request $request): JsonResponse
    {
        try {
            $payload = $request->getContent();
            $sigHeader = $request->header('Stripe-Signature');
            
            Log::info('Stripe webhook received');
            
            // Verify webhook signature
            if (!$this->verifyStripeSignature($payload, $sigHeader)) {
                Log::warning('Stripe webhook signature verification failed');
                return response()->json(['error' => 'Invalid signature'], 401);
            }
            
            $event = json_decode($payload, true);
            
            switch ($event['type']) {
                case 'payment_intent.succeeded':
                    $this->handleStripePaymentSuccess($event['data']['object']);
                    break;
                case 'payment_intent.payment_failed':
                    $this->handleStripePaymentFailed($event['data']['object']);
                    break;
                case 'payment_intent.canceled':
                    $this->handleStripePaymentCanceled($event['data']['object']);
                    break;
                default:
                    Log::info('Unhandled Stripe webhook event: ' . $event['type']);
            }
            
            return response()->json(['status' => 'success']);
            
        } catch (\Exception $e) {
            Log::error('Stripe webhook processing failed: ' . $e->getMessage());
            return response()->json(['error' => 'Webhook processing failed'], 500);
        }
    }

    /**
     * Handle generic payment gateway webhook
     */
    public function genericWebhook(Request $request): JsonResponse
    {
        try {
            $payload = $request->all();
            $gateway = $request->input('gateway', 'unknown');
            
            Log::info("Generic payment webhook received from {$gateway}", $payload);
            
            $transactionId = $payload['transaction_id'] ?? $payload['reference'] ?? null;
            $status = $payload['status'] ?? null;
            
            if (!$transactionId || !$status) {
                return response()->json(['error' => 'Missing required fields'], 400);
            }
            
            // Find payment by transaction ID
            $payment = Transaction::where('gateway_transaction_id', $transactionId)->first();
            
            if (!$payment) {
                Log::warning('Payment not found for transaction ID: ' . $transactionId);
                return response()->json(['error' => 'Payment not found'], 404);
            }
            
            // Update payment status
            $this->updatePaymentStatus($payment, $status, $payload);
            
            return response()->json(['status' => 'success']);
            
        } catch (\Exception $e) {
            Log::error('Generic webhook processing failed: ' . $e->getMessage());
            return response()->json(['error' => 'Webhook processing failed'], 500);
        }
    }

    /**
     * Update payment status based on webhook data
     */
    protected function updatePaymentStatus(Transaction $payment, string $status, array $payload): void
    {
        $oldStatus = $payment->status;
        $newStatus = $this->mapWebhookStatus($status);
        
        $payment->update([
            'status' => $newStatus,
            'gateway_response' => $payload,
            'processed_at' => now()
        ]);
        
        Log::info("Payment {$payment->id} status updated from {$oldStatus} to {$newStatus}");
        
        // Update booking status if payment is completed
        if ($newStatus === 'completed' && $oldStatus !== 'completed') {
            $booking = $payment->booking;
            if ($booking && $booking->status === 'pending') {
                $this->bookingService->confirmBooking($booking->id);
                Log::info("Booking {$booking->id} confirmed due to successful payment");
            }
        }
        
        // Handle failed payments
        if ($newStatus === 'failed' && $oldStatus !== 'failed') {
            $booking = $payment->booking;
            if ($booking && $booking->status === 'pending') {
                $this->bookingService->cancelBooking($booking->id, 'Payment failed');
                Log::info("Booking {$booking->id} cancelled due to failed payment");
            }
        }
    }

    /**
     * Map webhook status to internal payment status
     */
    protected function mapWebhookStatus(string $webhookStatus): string
    {
        $statusMap = [
            'success' => 'completed',
            'completed' => 'completed',
            'paid' => 'completed',
            'failed' => 'failed',
            'error' => 'failed',
            'cancelled' => 'cancelled',
            'canceled' => 'cancelled',
            'pending' => 'pending',
            'processing' => 'processing'
        ];
        
        return $statusMap[strtolower($webhookStatus)] ?? 'failed';
    }

    /**
     * Verify BML webhook signature
     */
    protected function verifyBmlSignature(Request $request): bool
    {
        // Implement BML signature verification logic
        $signature = $request->header('X-BML-Signature');
        $payload = $request->getContent();
        $secret = config('payments.bml.webhook_secret');
        
        if (!$signature || !$secret) {
            return false;
        }
        
        $expectedSignature = hash_hmac('sha256', $payload, $secret);
        
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Verify Stripe webhook signature
     */
    protected function verifyStripeSignature(string $payload, string $sigHeader): bool
    {
        $secret = config('payments.stripe.webhook_secret');
        
        if (!$secret) {
            return false;
        }
        
        try {
            \Stripe\Webhook::constructEvent($payload, $sigHeader, $secret);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Handle successful Stripe payment
     */
    protected function handleStripePaymentSuccess(array $paymentIntent): void
    {
        $payment = Transaction::where('gateway_transaction_id', $paymentIntent['id'])->first();
        
        if ($payment) {
            $this->updatePaymentStatus($payment, 'completed', $paymentIntent);
        }
    }

    /**
     * Handle failed Stripe payment
     */
    protected function handleStripePaymentFailed(array $paymentIntent): void
    {
        $payment = Transaction::where('gateway_transaction_id', $paymentIntent['id'])->first();
        
        if ($payment) {
            $this->updatePaymentStatus($payment, 'failed', $paymentIntent);
        }
    }

    /**
     * Handle canceled Stripe payment
     */
    protected function handleStripePaymentCanceled(array $paymentIntent): void
    {
        $payment = Transaction::where('gateway_transaction_id', $paymentIntent['id'])->first();
        
        if ($payment) {
            $this->updatePaymentStatus($payment, 'cancelled', $paymentIntent);
        }
    }
}
