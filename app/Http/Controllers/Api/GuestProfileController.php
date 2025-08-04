<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\GuestProfileRequest;
use App\Http\Resources\Api\GuestProfileResource;
use App\Models\GuestProfile;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

/**
 * @group Guest Profile Management
 * 
 * Manage guest profiles, preferences, and booking history.
 */
class GuestProfileController extends Controller
{
    /**
     * Create or update guest profile
     */
    public function createOrUpdate(GuestProfileRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            
            // Find existing guest by email or create new one
            $guest = GuestProfile::where('email', $validated['email'])->first();
            
            if ($guest) {
                // Update existing guest
                $guest->update($validated);
                $message = 'Guest profile updated successfully';
            } else {
                // Create new guest
                if (isset($validated['password'])) {
                    $validated['password'] = Hash::make($validated['password']);
                }
                $guest = GuestProfile::create($validated);
                $message = 'Guest profile created successfully';
            }
            
            return response()->json([
                'success' => true,
                'data' => new GuestProfileResource($guest),
                'message' => $message
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to create/update guest profile',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get guest profile by ID or email
     */
    public function show(Request $request, int $guestId = null): JsonResponse
    {
        try {
            if ($guestId) {
                $guest = GuestProfile::findOrFail($guestId);
            } else {
                $email = $request->input('email');
                if (!$email) {
                    return response()->json([
                        'error' => 'Guest ID or email is required'
                    ], 400);
                }
                $guest = GuestProfile::where('email', $email)->firstOrFail();
            }
            
            return response()->json([
                'success' => true,
                'data' => new GuestProfileResource($guest)
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Guest not found',
                'message' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Get guest booking history
     */
    public function bookingHistory(int $guestId): JsonResponse
    {
        try {
            $guest = GuestProfile::findOrFail($guestId);
            
            $bookings = Booking::where('guest_id', $guestId)
                ->with(['resort', 'ratePlan.roomType'])
                ->orderBy('created_at', 'desc')
                ->paginate(10);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'guest' => new GuestProfileResource($guest),
                    'bookings' => $bookings
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to get booking history',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update guest preferences
     */
    public function updatePreferences(int $guestId, Request $request): JsonResponse
    {
        try {
            $guest = GuestProfile::findOrFail($guestId);
            
            $preferences = $request->input('preferences', []);
            $loyaltyProgram = $request->input('loyalty_program');
            
            $updateData = [];
            if (!empty($preferences)) {
                $updateData['preferences'] = $preferences;
            }
            if ($loyaltyProgram !== null) {
                $updateData['loyalty_program'] = $loyaltyProgram;
            }
            
            if (empty($updateData)) {
                return response()->json([
                    'error' => 'No preferences to update'
                ], 400);
            }
            
            $guest->update($updateData);
            
            return response()->json([
                'success' => true,
                'data' => new GuestProfileResource($guest),
                'message' => 'Preferences updated successfully'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to update preferences',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add guest to loyalty program
     */
    public function joinLoyaltyProgram(int $guestId, Request $request): JsonResponse
    {
        try {
            $guest = GuestProfile::findOrFail($guestId);
            
            $programTier = $request->input('program_tier', 'bronze');
            
            $guest->update([
                'loyalty_program' => $programTier,
                'loyalty_points' => $guest->loyalty_points ?? 0,
                'loyalty_joined_at' => now()
            ]);
            
            return response()->json([
                'success' => true,
                'data' => new GuestProfileResource($guest),
                'message' => 'Successfully joined loyalty program'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to join loyalty program',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update guest contact information
     */
    public function updateContact(int $guestId, Request $request): JsonResponse
    {
        try {
            $guest = GuestProfile::findOrFail($guestId);
            
            $contactFields = ['phone', 'address', 'city', 'country', 'postal_code'];
            $updateData = $request->only($contactFields);
            
            if (empty($updateData)) {
                return response()->json([
                    'error' => 'No contact information to update'
                ], 400);
            }
            
            $guest->update($updateData);
            
            return response()->json([
                'success' => true,
                'data' => new GuestProfileResource($guest),
                'message' => 'Contact information updated successfully'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to update contact information',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
