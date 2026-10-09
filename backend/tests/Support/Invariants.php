<?php

declare(strict_types=1);

namespace App\Tests\Support;

use PDO;
use RuntimeException;

class Invariants
{
    /**
     * Asserts that all database invariants hold:
     * 1. For every donation: available_quantity + sum(active allocations: reserved, confirmed, collected, completed) == total_quantity
     * 2. For every requirement: sum(active allocations: reserved, confirmed, collected, completed) == quantity_allocated
     * 3. For every requirement: quantity_allocated <= quantity_needed
     * 4. No negative available_quantity or allocated_quantity
     */
    public static function checkAll(PDO $pdo): void
    {
        // 1. Donation stock invariant
        $stmt = $pdo->query("
            SELECT d.id, d.title, d.total_quantity, d.available_quantity,
                   COALESCE(SUM(a.allocated_quantity), 0) AS allocated_sum
            FROM donations d
            LEFT JOIN allocations a ON a.donation_id = d.id AND a.status IN ('reserved', 'confirmed', 'collected', 'completed')
            GROUP BY d.id, d.title, d.total_quantity, d.available_quantity
            HAVING (d.available_quantity + COALESCE(SUM(a.allocated_quantity), 0)) != d.total_quantity
                OR d.available_quantity < 0
        ");
        $mismatches = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($mismatches)) {
            $msg = "Donation invariant violation detected: " . json_encode($mismatches);
            throw new RuntimeException($msg);
        }

        // 2. Requirement allocation invariant
        $stmtReq = $pdo->query("
            SELECT r.id, r.title, r.quantity_needed, r.quantity_allocated,
                   COALESCE(SUM(a.allocated_quantity), 0) AS allocated_sum
            FROM ngo_requirements r
            LEFT JOIN allocations a ON a.requirement_id = r.id AND a.status IN ('reserved', 'confirmed', 'collected', 'completed')
            GROUP BY r.id, r.title, r.quantity_needed, r.quantity_allocated
            HAVING COALESCE(SUM(a.allocated_quantity), 0) != r.quantity_allocated
                OR r.quantity_allocated > r.quantity_needed
                OR r.quantity_allocated < 0
        ");
        $reqMismatches = $stmtReq->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($reqMismatches)) {
            $msg = "Requirement invariant violation detected: " . json_encode($reqMismatches);
            throw new RuntimeException($msg);
        }
    }
}
