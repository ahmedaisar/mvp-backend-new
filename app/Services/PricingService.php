<?php

namespace App\Services;

use App\Models\SeasonalRate;
use App\Models\Promotion;
use App\Models\SiteSetting;
use App\Models\RatePlan;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class PricingService
{
    /**
     * Calculate total price for a booking period
     */
    public function calculateTotalPrice($ratePlanId, $checkIn, $checkOut, $promotionCode = null)
    {
        $basePrice = $this->calculateBasePrice($ratePlanId, $checkIn, $checkOut);
        $discount = 0;
        $promotion = null;

        if ($promotionCode) {
            $promotion = $this->getValidPromotion($promotionCode, $ratePlanId, $basePrice);
            if ($promotion) {
                $discount = $this->calculatePromotionDiscount($promotion, $basePrice);
            }
        }

        $subtotal = $basePrice - $discount;
        $taxes = $this->calculateTaxes($subtotal);
        $fees = $this->calculateFees($subtotal);
        $total = $subtotal + $taxes + $fees;

        return [
            'base_price' => $basePrice,
            'discount' => $discount,
            'subtotal' => $subtotal,
            'taxes' => $taxes,
            'fees' => $fees,
            'total' => $total,
            'promotion' => $promotion,
            'breakdown' => $this->getNightlyBreakdown($ratePlanId, $checkIn, $checkOut, $promotion),
        ];
    }

    /**
     * Calculate base price without any discounts
     */
    public function calculateBasePrice($ratePlanId, $checkIn, $checkOut)
    {
        return SeasonalRate::calculateTotalForPeriod($ratePlanId, $checkIn, $checkOut);
    }

    /**
     * Get nightly price breakdown
     */
    public function getNightlyBreakdown($ratePlanId, $checkIn, $checkOut, $promotion = null)
    {
        $breakdown = [];
        $period = Carbon::parse($checkIn)->toPeriod($checkOut, 1, 'day');

        foreach ($period as $date) {
            if ($date->eq(Carbon::parse($checkOut))) {
                break; // Exclude checkout date
            }

            $rate = SeasonalRate::where('rate_plan_id', $ratePlanId)
                ->forDate($date)
                ->first();

            $basePrice = $rate ? $rate->nightly_price : 0;
            $discount = 0;

            if ($promotion && $promotion->applies_to === 'per_night') {
                $discount = $this->calculatePromotionDiscount($promotion, $basePrice);
            }

            $breakdown[] = [
                'date' => $date->toDateString(),
                'day_of_week' => $date->format('l'),
                'base_price' => $basePrice,
                'discount' => $discount,
                'final_price' => $basePrice - $discount,
                'rate_name' => $rate ? $rate->name : 'Standard Rate',
            ];
        }

        return $breakdown;
    }

    /**
     * Get valid promotion for the given criteria
     */
    protected function getValidPromotion($promotionCode, $ratePlanId, $subtotal)
    {
        $promotion = Promotion::active()
            ->byCode($promotionCode)
            ->first();

        if (!$promotion || !$promotion->isValid($subtotal, $ratePlanId)) {
            return null;
        }

        return $promotion;
    }

    /**
     * Calculate promotion discount amount
     */
    protected function calculatePromotionDiscount($promotion, $amount)
    {
        if ($promotion->discount_type === 'percentage') {
            $discount = ($amount * $promotion->discount_value) / 100;
        } else {
            $discount = $promotion->discount_value;
        }

        // Apply maximum discount limit if set
        if ($promotion->max_discount_amount && $discount > $promotion->max_discount_amount) {
            $discount = $promotion->max_discount_amount;
        }

        return min($discount, $amount); // Discount cannot exceed the base amount
    }

    /**
     * Calculate taxes based on site settings
     */
    protected function calculateTaxes($subtotal)
    {
        $taxRate = SiteSetting::getValue('booking.tax_rate', 0.12); // Default 12% TGST
        return $subtotal * $taxRate;
    }

    /**
     * Calculate additional fees
     */
    protected function calculateFees($subtotal)
    {
        $serviceFeeRate = SiteSetting::getValue('booking.service_fee_rate', 0.05); // 5% service fee
        $bookingFee = SiteSetting::getValue('booking.booking_fee', 25.00); // Fixed booking fee
        
        return ($subtotal * $serviceFeeRate) + $bookingFee;
    }

    /**
     * Convert price between currencies
     */
    public function convertCurrency($amount, $fromCurrency, $toCurrency)
    {
        if ($fromCurrency === $toCurrency) {
            return $amount;
        }

        // Get exchange rates from site settings
        $rates = SiteSetting::getValue('currency_rates', []);
        
        // Convert to MVR first (base currency)
        if ($fromCurrency !== 'MVR') {
            $toMvrRate = $rates["{$fromCurrency}_TO_MVR"] ?? 1;
            $amountInMvr = $amount * $toMvrRate;
        } else {
            $amountInMvr = $amount;
        }

        // Convert from MVR to target currency
        if ($toCurrency !== 'MVR') {
            $fromMvrRate = $rates["MVR_TO_{$toCurrency}"] ?? 1;
            return $amountInMvr * $fromMvrRate;
        }

        return $amountInMvr;
    }

    /**
     * Get pricing summary for multiple rate plans
     */
    public function getBulkPricingSummary(array $ratePlanIds, $checkIn, $checkOut)
    {
        $results = [];

        foreach ($ratePlanIds as $ratePlanId) {
            $ratePlan = RatePlan::with('roomType.resort')->find($ratePlanId);
            
            if (!$ratePlan) {
                continue;
            }

            $pricing = $this->calculateTotalPrice($ratePlanId, $checkIn, $checkOut);
            
            $results[] = [
                'rate_plan_id' => $ratePlanId,
                'rate_plan' => $ratePlan,
                'pricing' => $pricing,
                'average_nightly_rate' => $pricing['base_price'] / max(1, Carbon::parse($checkIn)->diffInDays($checkOut)),
            ];
        }

        return collect($results)->sortBy('pricing.total');
    }

    /**
     * Calculate dynamic pricing based on demand and occupancy
     */
    public function calculateDynamicPrice($ratePlanId, $date, $basePrice, $occupancyRate = null)
    {
        // Get occupancy rate if not provided
        if ($occupancyRate === null) {
            $occupancyRate = $this->getOccupancyRate($ratePlanId, $date);
        }

        $multiplier = 1.0;

        // Adjust based on occupancy
        if ($occupancyRate > 0.8) {
            $multiplier = 1.3; // High demand - increase by 30%
        } elseif ($occupancyRate > 0.6) {
            $multiplier = 1.15; // Medium demand - increase by 15%
        } elseif ($occupancyRate < 0.3) {
            $multiplier = 0.85; // Low demand - decrease by 15%
        }

        // Adjust based on day of week
        $dayOfWeek = Carbon::parse($date)->dayOfWeek;
        if (in_array($dayOfWeek, [5, 6])) { // Friday, Saturday
            $multiplier *= 1.1; // Weekend premium
        }

        // Adjust based on advance booking
        $daysUntilStay = Carbon::parse($date)->diffInDays(now());
        if ($daysUntilStay < 7) {
            $multiplier *= 1.05; // Last minute premium
        } elseif ($daysUntilStay > 60) {
            $multiplier *= 0.95; // Early bird discount
        }

        $dynamicPrice = $basePrice * $multiplier;

        // Apply minimum and maximum price limits
        $minPrice = $basePrice * 0.7; // Never go below 70% of base price
        $maxPrice = $basePrice * 2.0; // Never go above 200% of base price

        return max($minPrice, min($maxPrice, $dynamicPrice));
    }

    /**
     * Get current occupancy rate for a rate plan on a specific date
     */
    protected function getOccupancyRate($ratePlanId, $date)
    {
        // This would typically query the inventory service
        // For now, return a mock occupancy rate
        return 0.5; // 50% occupancy
    }

    /**
     * Generate pricing recommendations for yield management
     */
    public function getPricingRecommendations($ratePlanId, $startDate, $endDate)
    {
        $recommendations = [];
        $period = Carbon::parse($startDate)->toPeriod($endDate, 1, 'day');

        foreach ($period as $date) {
            $currentRate = SeasonalRate::where('rate_plan_id', $ratePlanId)
                ->forDate($date)
                ->first();

            if (!$currentRate) {
                continue;
            }

            $occupancyRate = $this->getOccupancyRate($ratePlanId, $date->toDateString());
            $recommendedPrice = $this->calculateDynamicPrice(
                $ratePlanId, 
                $date->toDateString(), 
                $currentRate->nightly_price, 
                $occupancyRate
            );

            $priceDifference = $recommendedPrice - $currentRate->nightly_price;
            $percentageChange = ($priceDifference / $currentRate->nightly_price) * 100;

            $recommendations[] = [
                'date' => $date->toDateString(),
                'current_price' => $currentRate->nightly_price,
                'recommended_price' => $recommendedPrice,
                'price_difference' => $priceDifference,
                'percentage_change' => round($percentageChange, 2),
                'occupancy_rate' => $occupancyRate * 100,
                'action' => $this->getPricingAction($percentageChange),
            ];
        }

        return collect($recommendations);
    }

    /**
     * Get pricing action recommendation
     */
    protected function getPricingAction($percentageChange)
    {
        if ($percentageChange > 10) {
            return 'increase_significantly';
        } elseif ($percentageChange > 5) {
            return 'increase_moderately';
        } elseif ($percentageChange < -10) {
            return 'decrease_significantly';
        } elseif ($percentageChange < -5) {
            return 'decrease_moderately';
        } else {
            return 'maintain_current';
        }
    }

    /**
     * Calculate competitor price analysis
     */
    public function getCompetitorAnalysis($ratePlanId, $checkIn, $checkOut)
    {
        // This would integrate with external APIs to get competitor pricing
        // For now, return mock data structure
        
        $ourPrice = $this->calculateBasePrice($ratePlanId, $checkIn, $checkOut);
        $nights = Carbon::parse($checkIn)->diffInDays($checkOut);
        
        return [
            'our_price' => $ourPrice,
            'our_avg_nightly' => $ourPrice / $nights,
            'competitors' => [
                [
                    'name' => 'Competitor A',
                    'total_price' => $ourPrice * 1.1,
                    'avg_nightly' => ($ourPrice * 1.1) / $nights,
                    'difference' => $ourPrice * 0.1,
                ],
                [
                    'name' => 'Competitor B', 
                    'total_price' => $ourPrice * 0.95,
                    'avg_nightly' => ($ourPrice * 0.95) / $nights,
                    'difference' => -($ourPrice * 0.05),
                ],
            ],
            'market_position' => 'competitive',
            'recommendation' => 'maintain_current_pricing',
        ];
    }
}
