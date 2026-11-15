<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\AuditLog;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CouponService
{
    /**
     * Validate coupon eligibility and calculate discount.
     */
    public function validateCoupon(string $code, Plan $plan, string $country, float $subtotal, ?User $user = null): array
    {
        $code = strtoupper(trim($code));
        $coupon = Coupon::where('code', $code)->first();

        if (!$coupon) {
            throw ValidationException::withMessages([
                'coupon' => ['The promotional code entered is invalid.'],
            ]);
        }

        if (!$coupon->isValid($plan->slug, $country, $subtotal)) {
            throw ValidationException::withMessages([
                'coupon' => ['This promotional code is not valid for your selected plan, region, or order criteria.'],
            ]);
        }

        if ($user) {
            // Check if user already redeemed this coupon
            $hasRedeemed = CouponRedemption::where('coupon_id', $coupon->id)
                ->where('user_id', $user->id)
                ->exists();

            if ($hasRedeemed) {
                throw ValidationException::withMessages([
                    'coupon' => ['You have already redeemed this promotional discount.'],
                ]);
            }
        }

        $discount = $coupon->calculateDiscount($subtotal);

        return [
            'coupon_id' => $coupon->id,
            'code' => $coupon->code,
            'name' => $coupon->name,
            'discount_type' => $coupon->discount_type,
            'discount_value' => (float) $coupon->discount_value,
            'discount_amount' => $discount,
            'final_amount' => max(0.0, round($subtotal - $discount, 2)),
        ];
    }

    /**
     * Redeem coupon upon successful checkout/invoice generation.
     */
    public function redeemCoupon(Coupon $coupon, User $user, Invoice $invoice, float $discountApplied): CouponRedemption
    {
        $redemption = CouponRedemption::create([
            'id' => (string) Str::uuid(),
            'coupon_id' => $coupon->id,
            'user_id' => $user->id,
            'invoice_id' => $invoice->id,
            'discount_applied' => $discountApplied,
            'currency' => $invoice->currency,
            'redeemed_at' => now(),
        ]);

        $coupon->increment('times_redeemed');

        return $redemption;
    }

    /**
     * Admin: Create or update coupon.
     */
    public function saveCoupon(User $admin, array $data, ?string $couponId = null): Coupon
    {
        $code = strtoupper(trim($data['code']));

        $coupon = $couponId
            ? Coupon::findOrFail($couponId)
            : new Coupon(['id' => (string) Str::uuid()]);

        $coupon->fill([
            'code' => $code,
            'name' => trim($data['name']),
            'discount_type' => $data['discount_type'],
            'discount_value' => (float) $data['discount_value'],
            'currency' => $data['currency'] ?? null,
            'max_redemptions' => isset($data['max_redemptions']) ? (int) $data['max_redemptions'] : null,
            'valid_from' => $data['valid_from'] ?? now(),
            'valid_until' => $data['valid_until'] ?? null,
            'eligible_plans' => $data['eligible_plans'] ?? null,
            'eligible_countries' => $data['eligible_countries'] ?? null,
            'min_order_amount' => isset($data['min_order_amount']) ? (float) $data['min_order_amount'] : null,
            'is_active' => $data['is_active'] ?? true,
        ]);

        $coupon->save();

        AuditLog::create([
            'id' => (string) Str::uuid(),
            'user_id' => $admin->id,
            'action' => $couponId ? 'admin.coupon.update' : 'admin.coupon.create',
            'resource_type' => 'Coupon',
            'resource_id' => $coupon->id,
            'metadata' => ['code' => $coupon->code, 'discount_value' => $coupon->discount_value],
            'created_at' => now(),
        ]);

        return $coupon;
    }
}
