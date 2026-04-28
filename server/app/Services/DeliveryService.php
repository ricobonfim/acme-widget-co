<?php

namespace App\Services;

class DeliveryService
{
    /**
     * Tiered delivery rules. Each rule applies when the subtotal (in cents) is
     * strictly less than `under_cents`; the first matching rule wins. The final
     * rule should use PHP_INT_MAX as a catch-all (free delivery).
     *
     * Rules are ordered by ascending threshold and easy to tweak in one place.
     */
    private const RULES = [
        ['under_cents' => 5000,        'cost_cents' => 495], // < $50  → $4.95
        ['under_cents' => 9000,        'cost_cents' => 295], // < $90  → $2.95
        ['under_cents' => PHP_INT_MAX, 'cost_cents' => 0],   // ≥ $90  → free
    ];

    /**
     * Calculate delivery cost (in cents) for a given subtotal (in cents).
     * Empty baskets incur no delivery charge.
     */
    public function costFor(int $subtotalCents): int
    {
        if ($subtotalCents <= 0) {
            return 0;
        }

        foreach (self::RULES as $rule) {
            if ($subtotalCents < $rule['under_cents']) {
                return $rule['cost_cents'];
            }
        }

        return 0;
    }
}
