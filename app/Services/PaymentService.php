<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\Booking;
use App\Models\SiteSetting;
use App\Models\AuditLog;
use Stripe\Stripe;
use Stripe\PaymentIntent;
use Stripe\Webhook;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class PaymentService
{
    protected $stripeSecretKey;
    protected $stripeWebhookSecret;

    public function __construct()
    {
        $this->stripeSecretKey = SiteSetting::getValue('payment.stripe_secret_key');
        $this->stripeWebhookSecret = SiteSetting::getValue('payment.stripe_webhook_secret');
        
        if ($this->stripeSecretKey) {
            Stripe::setApiKey($this->stripeSecretKey);
        }
    }

    /**
     * Create a payment intent for a booking
     */
    public function createPaymentIntent($bookingId, $currency = 'usd')
    {
        try {
            $booking = Booking::findOrFail($bookingId);
            
            if ($booking->status !== 'pending') {
                throw new Exception('Can only create payment for pending bookings.');
            }

            // Convert amount to cents and target currency
            $amount = $this->convertToPaymentCurrency($booking->total_price_usd, $currency);
            $amountCents = intval($amount * 100);

            $paymentIntent = PaymentIntent::create([
                'amount' => $amountCents,
                'currency' => strtolower($currency),
                'metadata' => [
                    'booking_id' => $bookingId,
                    'guest_email' => $booking->guestProfile->email,
                    'resort_name' => $booking->resort->name,
                ],
                'description' => "Booking #{$booking->id} - {$booking->resort->name}",
                'receipt_email' => $booking->guestProfile->email,
            ]);

            // Create transaction record
            $transaction = Transaction::create([
                'booking_id' => $bookingId,
                'type' => 'payment',
                'status' => 'pending',
                'amount_usd' => $booking->total_price_usd,
                'amount_usd' => $amount,
                'currency' => strtoupper($currency),
                'payment_method' => 'stripe',
                'gateway_transaction_id' => $paymentIntent->id,
                'gateway_response' => $paymentIntent->toArray(),
                'processed_at' => null,
            ]);

            // Log the payment intent creation
            AuditLog::log('payment_intent_created', $transaction, null, [
                'payment_intent_id' => $paymentIntent->id,
                'amount' => $amountCents,
                'currency' => $currency,
            ]);

            return [
                'client_secret' => $paymentIntent->client_secret,
                'payment_intent_id' => $paymentIntent->id,
                'amount' => $amount,
                'currency' => $currency,
                'transaction_id' => $transaction->id,
            ];

        } catch (Exception $e) {
            Log::error('Payment intent creation failed', [
                'booking_id' => $bookingId,
                'error' => $e->getMessage(),
            ]);
            
            throw new Exception('Failed to create payment intent: ' . $e->getMessage());
        }
    }

    /**
     * Handle successful payment confirmation
     */
    public function confirmPayment($paymentIntentId)
    {
        return DB::transaction(function () use ($paymentIntentId) {
            $transaction = Transaction::where('gateway_transaction_id', $paymentIntentId)
                ->where('status', 'pending')
                ->firstOrFail();

            try {
                // Retrieve payment intent from Stripe
                $paymentIntent = PaymentIntent::retrieve($paymentIntentId);

                if ($paymentIntent->status === 'succeeded') {
                    // Update transaction
                    $transaction->update([
                        'status' => 'completed',
                        'processed_at' => now(),
                        'gateway_response' => $paymentIntent->toArray(),
                    ]);

                    // Confirm the booking
                    $booking = $transaction->booking;
                    $booking->confirm();

                    // Move inventory from reserved to booked
                    app(InventoryService::class)->confirmBookingInventory($booking->id);

                    // Log the successful payment
                    AuditLog::log('payment_completed', $transaction, null, [
                        'payment_intent_id' => $paymentIntentId,
                        'amount' => $transaction->amount_usd,
                        'currency' => $transaction->currency,
                    ]);

                    return $transaction->load('booking');
                }

                throw new Exception("Payment intent status is {$paymentIntent->status}");

            } catch (Exception $e) {
                $transaction->update([
                    'status' => 'failed',
                    'processed_at' => now(),
                    'failure_reason' => $e->getMessage(),
                ]);

                throw $e;
            }
        });
    }

    /**
     * Handle failed payment
     */
    public function handleFailedPayment($paymentIntentId, $failureReason = null)
    {
        return DB::transaction(function () use ($paymentIntentId, $failureReason) {
            $transaction = Transaction::where('gateway_transaction_id', $paymentIntentId)
                ->firstOrFail();

            $transaction->update([
                'status' => 'failed',
                'processed_at' => now(),
                'failure_reason' => $failureReason,
            ]);

            // Release reserved inventory
            app(InventoryService::class)->releaseReservation($transaction->booking_id);

            // Log the failed payment
            AuditLog::log('payment_failed', $transaction, null, [
                'payment_intent_id' => $paymentIntentId,
                'failure_reason' => $failureReason,
            ]);

            return $transaction;
        });
    }

    /**
     * Process refund for a booking
     */
    public function processRefund($bookingId, $amount = null, $reason = null)
    {
        return DB::transaction(function () use ($bookingId, $amount, $reason) {
            $booking = Booking::findOrFail($bookingId);
            
            if ($booking->status !== 'confirmed') {
                throw new Exception('Can only refund confirmed bookings.');
            }

            // Find the original payment transaction
            $originalTransaction = Transaction::where('booking_id', $bookingId)
                ->where('type', 'payment')
                ->where('status', 'completed')
                ->firstOrFail();

            // Default to full refund if amount not specified
            $refundAmount = $amount ?? $originalTransaction->amount_usd;

            try {
                // Create refund in Stripe
                $refund = \Stripe\Refund::create([
                    'payment_intent' => $originalTransaction->gateway_transaction_id,
                    'amount' => intval($refundAmount * 100), // Convert to cents
                    'reason' => $reason ? 'requested_by_customer' : 'duplicate',
                    'metadata' => [
                        'booking_id' => $bookingId,
                        'refund_reason' => $reason,
                    ],
                ]);

                // Create refund transaction record
                $refundTransaction = Transaction::create([
                    'booking_id' => $bookingId,
                    'type' => 'refund',
                    'status' => 'completed',
                    'amount_mvr' => $this->convertFromPaymentCurrency($refundAmount, $originalTransaction->currency),
                    'amount_usd' => $refundAmount,
                    'currency' => $originalTransaction->currency,
                    'payment_method' => 'stripe',
                    'gateway_transaction_id' => $refund->id,
                    'gateway_response' => $refund->toArray(),
                    'processed_at' => now(),
                    'notes' => $reason,
                ]);

                // Update booking status
                $booking->update(['status' => 'refunded']);

                // Release booked inventory
                app(InventoryService::class)->releaseBookedInventory($bookingId);

                // Log the refund
                AuditLog::log('refund_processed', $refundTransaction, null, [
                    'refund_id' => $refund->id,
                    'amount' => $refundAmount,
                    'reason' => $reason,
                ]);

                return $refundTransaction;

            } catch (Exception $e) {
                Log::error('Refund processing failed', [
                    'booking_id' => $bookingId,
                    'error' => $e->getMessage(),
                ]);

                throw new Exception('Failed to process refund: ' . $e->getMessage());
            }
        });
    }

    /**
     * Handle Stripe webhook events
     */
    public function handleWebhook($payload, $signature)
    {
        try {
            $event = Webhook::constructEvent(
                $payload,
                $signature,
                $this->stripeWebhookSecret
            );

            switch ($event->type) {
                case 'payment_intent.succeeded':
                    $this->handlePaymentIntentSucceeded($event->data->object);
                    break;

                case 'payment_intent.payment_failed':
                    $this->handlePaymentIntentFailed($event->data->object);
                    break;

                case 'charge.dispute.created':
                    $this->handleChargeDispute($event->data->object);
                    break;

                default:
                    Log::info('Unhandled webhook event type: ' . $event->type);
            }

            return ['status' => 'success'];

        } catch (Exception $e) {
            Log::error('Webhook handling failed', [
                'error' => $e->getMessage(),
                'payload' => $payload,
            ]);

            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * Handle payment intent succeeded webhook
     */
    protected function handlePaymentIntentSucceeded($paymentIntent)
    {
        try {
            $this->confirmPayment($paymentIntent->id);
        } catch (Exception $e) {
            Log::error('Failed to confirm payment from webhook', [
                'payment_intent_id' => $paymentIntent->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle payment intent failed webhook
     */
    protected function handlePaymentIntentFailed($paymentIntent)
    {
        try {
            $failureReason = $paymentIntent->last_payment_error->message ?? 'Payment failed';
            $this->handleFailedPayment($paymentIntent->id, $failureReason);
        } catch (Exception $e) {
            Log::error('Failed to handle payment failure from webhook', [
                'payment_intent_id' => $paymentIntent->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle charge dispute webhook
     */
    protected function handleChargeDispute($dispute)
    {
        try {
            // Find the transaction by charge ID
            $transaction = Transaction::where('gateway_response->charges->data->0->id', $dispute->charge)
                ->first();

            if ($transaction) {
                // Create dispute transaction record
                Transaction::create([
                    'booking_id' => $transaction->booking_id,
                    'type' => 'dispute',
                    'status' => 'pending',
                    'amount_mvr' => $transaction->amount_mvr,
                    'amount_usd' => $transaction->amount_usd,
                    'currency' => $transaction->currency,
                    'payment_method' => 'stripe',
                    'gateway_transaction_id' => $dispute->id,
                    'gateway_response' => $dispute->toArray(),
                    'processed_at' => now(),
                    'notes' => "Dispute reason: {$dispute->reason}",
                ]);

                // Log the dispute
                AuditLog::log('payment_disputed', $transaction, null, [
                    'dispute_id' => $dispute->id,
                    'reason' => $dispute->reason,
                    'amount' => $dispute->amount / 100,
                ]);
            }
        } catch (Exception $e) {
            Log::error('Failed to handle dispute from webhook', [
                'dispute_id' => $dispute->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Convert MVR amount to payment currency
     */
    protected function convertToPaymentCurrency($amountMvr, $currency)
    {
        if (strtoupper($currency) === 'MVR') {
            return $amountMvr;
        }

        $exchangeRate = SiteSetting::getValue("currency_rates.MVR_TO_" . strtoupper($currency), 0.065);
        return round($amountMvr * $exchangeRate, 2);
    }

    /**
     * Convert payment currency amount to MVR
     */
    protected function convertFromPaymentCurrency($amount, $currency)
    {
        if (strtoupper($currency) === 'MVR') {
            return $amount;
        }

        $exchangeRate = SiteSetting::getValue(strtoupper($currency) . "_TO_MVR", 15.42);
        return round($amount * $exchangeRate, 2);
    }

    /**
     * Get payment statistics
     */
    public function getPaymentStats($startDate = null, $endDate = null, $resortId = null)
    {
        $query = Transaction::where('type', 'payment');

        if ($startDate && $endDate) {
            $query->whereBetween('processed_at', [$startDate, $endDate]);
        }

        if ($resortId) {
            $query->whereHas('booking', function ($q) use ($resortId) {
                $q->where('resort_id', $resortId);
            });
        }

        $transactions = $query->get();

        return [
            'total_transactions' => $transactions->count(),
            'successful_payments' => $transactions->where('status', 'completed')->count(),
            'failed_payments' => $transactions->where('status', 'failed')->count(),
            'pending_payments' => $transactions->where('status', 'pending')->count(),
            'total_revenue_mvr' => $transactions->where('status', 'completed')->sum('amount_mvr'),
            'total_revenue_usd' => $transactions->where('status', 'completed')->sum('amount_usd'),
            'average_transaction_value' => $transactions->where('status', 'completed')->avg('amount_usd'),
            'success_rate' => $transactions->count() > 0 
                ? round(($transactions->where('status', 'completed')->count() / $transactions->count()) * 100, 2)
                : 0,
        ];
    }

    /**
     * Get transaction history for a booking
     */
    public function getTransactionHistory($bookingId)
    {
        return Transaction::where('booking_id', $bookingId)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($transaction) {
                return [
                    'id' => $transaction->id,
                    'type' => $transaction->type,
                    'status' => $transaction->status,
                    'amount_usd' => $transaction->amount_usd,
                    'currency' => $transaction->currency,
                    'processed_at' => $transaction->processed_at,
                    'failure_reason' => $transaction->failure_reason,
                    'notes' => $transaction->notes,
                    'gateway_transaction_id' => $transaction->gateway_transaction_id,
                ];
            });
    }
}
