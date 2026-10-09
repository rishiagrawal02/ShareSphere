<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Database;
use PDO;

class ReportRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getConnection();
    }

    /**
     * Summary counts bounded by optional date range.
     */
    public function getSummary(?string $from = null, ?string $to = null): array
    {
        $dateFilter = "";
        $params = [];

        if ($from !== null) {
            $dateFilter .= " AND created_at >= :from_date";
            $params[':from_date'] = $from;
        }
        if ($to !== null) {
            $dateFilter .= " AND created_at <= :to_date";
            $params[':to_date'] = $to;
        }

        // 1. Users by role
        $stmtUsers = $this->pdo->prepare("
            SELECT role, COUNT(*) AS count
            FROM users
            WHERE 1=1 {$dateFilter}
            GROUP BY role
        ");
        $stmtUsers->execute($params);
        $usersByRole = ['donor' => 0, 'ngo' => 0, 'admin' => 0];
        foreach ($stmtUsers->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $usersByRole[$row['role']] = (int) $row['count'];
        }

        // 2. NGOs by verification status
        $stmtNgos = $this->pdo->prepare("
            SELECT verification_status, COUNT(*) AS count
            FROM ngos
            WHERE 1=1 {$dateFilter}
            GROUP BY verification_status
        ");
        $stmtNgos->execute($params);
        $ngosByStatus = [
            'pending'              => 0,
            'verified'             => 0,
            'rejected'             => 0,
            'correction_requested' => 0,
            'suspended'            => 0,
        ];
        foreach ($stmtNgos->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $ngosByStatus[$row['verification_status']] = (int) $row['count'];
        }

        // 3. Donations by status
        $stmtDonations = $this->pdo->prepare("
            SELECT status, COUNT(*) AS count
            FROM donations
            WHERE 1=1 {$dateFilter}
            GROUP BY status
        ");
        $stmtDonations->execute($params);
        $donationsByStatus = [
            'draft'               => 0,
            'active'              => 0,
            'partially_allocated' => 0,
            'fully_allocated'     => 0,
            'completed'           => 0,
            'closed'              => 0,
            'removed'             => 0,
        ];
        foreach ($stmtDonations->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $donationsByStatus[$row['status']] = (int) $row['count'];
        }

        // 4. Requests by status
        $stmtRequests = $this->pdo->prepare("
            SELECT status, COUNT(*) AS count
            FROM donation_requests
            WHERE 1=1 {$dateFilter}
            GROUP BY status
        ");
        $stmtRequests->execute($params);
        $requestsByStatus = [
            'pending'   => 0,
            'accepted'  => 0,
            'rejected'  => 0,
            'cancelled' => 0,
            'expired'   => 0,
        ];
        foreach ($stmtRequests->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $requestsByStatus[$row['status']] = (int) $row['count'];
        }

        // 5. Completed handovers (pickups completed)
        $pickupFilter = "";
        $pickupParams = [];
        if ($from !== null) {
            $pickupFilter .= " AND completed_at >= :from_date";
            $pickupParams[':from_date'] = $from;
        }
        if ($to !== null) {
            $pickupFilter .= " AND completed_at <= :to_date";
            $pickupParams[':to_date'] = $to;
        }

        $stmtPickups = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM pickups
            WHERE state = 'completed' {$pickupFilter}
        ");
        $stmtPickups->execute($pickupParams);
        $completedHandovers = (int) $stmtPickups->fetchColumn();

        // 6. Completion rate = completed_handovers / accepted_requests
        $acceptedCount = $requestsByStatus['accepted'];
        $completionRate = ($acceptedCount > 0)
            ? round(($completedHandovers / $acceptedCount) * 100.0, 2)
            : 0.0;

        return [
            'users_by_role'       => $usersByRole,
            'ngos_by_status'      => $ngosByStatus,
            'donations_by_status' => $donationsByStatus,
            'requests_by_status'  => $requestsByStatus,
            'completed_handovers' => $completedHandovers,
            'completion_rate'     => $completionRate,
            'from'                => $from,
            'to'                  => $to,
        ];
    }

    /**
     * Trends bucketed by date intervals.
     */
    public function getTrends(string $metric, string $interval, ?string $from = null, ?string $to = null): array
    {
        $intervalSql = match ($interval) {
            'day'   => 'day',
            'month' => 'month',
            default => 'week',
        };

        if ($metric === 'completions') {
            $dateCol = 'completed_at';
            $table = 'pickups';
            $where = "state = 'completed' AND completed_at IS NOT NULL";
        } else {
            $dateCol = 'created_at';
            $table = 'donations';
            $where = "1=1";
        }

        $params = [];
        if ($from !== null) {
            $where .= " AND {$dateCol} >= :from_date";
            $params[':from_date'] = $from;
        }
        if ($to !== null) {
            $where .= " AND {$dateCol} <= :to_date";
            $params[':to_date'] = $to;
        }

        $sql = "
            SELECT date_trunc('{$intervalSql}', {$dateCol} AT TIME ZONE 'UTC') AS period,
                   COUNT(*) AS count
            FROM {$table}
            WHERE {$where}
            GROUP BY period
            ORDER BY period ASC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(function (array $r) {
            return [
                'period' => $r['period'],
                'count'  => (int) $r['count'],
            ];
        }, $rows);
    }

    /**
     * Distribution of donations and requirements across categories.
     */
    public function getCategoryDistribution(): array
    {
        $stmt = $this->pdo->query("
            SELECT c.id, c.name,
                   COUNT(DISTINCT d.id) AS donations_count,
                   COUNT(DISTINCT r.id) AS requirements_count
            FROM categories c
            LEFT JOIN donations d ON d.category_id = c.id
            LEFT JOIN ngo_requirements r ON r.category_id = c.id
            GROUP BY c.id, c.name
            ORDER BY c.name ASC
        ");

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(function (array $r) {
            return [
                'id'                 => (int) $r['id'],
                'name'               => $r['name'],
                'donations_count'    => (int) $r['donations_count'],
                'requirements_count' => (int) $r['requirements_count'],
            ];
        }, $rows);
    }

    /**
     * Filterable audit logs.
     */
    public function getAuditLogs(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $sql = "
            SELECT al.id, al.actor_user_id, al.action, al.target_type, al.target_id,
                   al.result, al.metadata, al.created_at,
                   u.name AS actor_name, u.email AS actor_email, u.role AS actor_role
            FROM audit_logs al
            LEFT JOIN users u ON u.id = al.actor_user_id
            WHERE 1=1
        ";
        $params = [];

        if (!empty($filters['actor_id'])) {
            $sql .= " AND al.actor_user_id = :actor_id";
            $params[':actor_id'] = (int) $filters['actor_id'];
        }

        if (!empty($filters['action'])) {
            $sql .= " AND al.action = :action";
            $params[':action'] = $filters['action'];
        }

        if (!empty($filters['target_type'])) {
            $sql .= " AND al.target_type = :target_type";
            $params[':target_type'] = $filters['target_type'];
        }

        if (!empty($filters['from'])) {
            $sql .= " AND al.created_at >= :from_date";
            $params[':from_date'] = $filters['from'];
        }

        if (!empty($filters['to'])) {
            $sql .= " AND al.created_at <= :to_date";
            $params[':to_date'] = $filters['to'];
        }

        $sql .= " ORDER BY al.created_at DESC LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countAuditLogs(array $filters = []): int
    {
        $sql = "
            SELECT COUNT(*)
            FROM audit_logs al
            WHERE 1=1
        ";
        $params = [];

        if (!empty($filters['actor_id'])) {
            $sql .= " AND al.actor_user_id = :actor_id";
            $params[':actor_id'] = (int) $filters['actor_id'];
        }

        if (!empty($filters['action'])) {
            $sql .= " AND al.action = :action";
            $params[':action'] = $filters['action'];
        }

        if (!empty($filters['target_type'])) {
            $sql .= " AND al.target_type = :target_type";
            $params[':target_type'] = $filters['target_type'];
        }

        if (!empty($filters['from'])) {
            $sql .= " AND al.created_at >= :from_date";
            $params[':from_date'] = $filters['from'];
        }

        if (!empty($filters['to'])) {
            $sql .= " AND al.created_at <= :to_date";
            $params[':to_date'] = $filters['to'];
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Donor Dashboard metrics.
     */
    public function getDonorDashboard(int $donorUserId): array
    {
        // Total donations
        $stmtTot = $this->pdo->prepare("SELECT COUNT(*) FROM donations WHERE donor_id = :uid");
        $stmtTot->execute([':uid' => $donorUserId]);
        $totalDonations = (int) $stmtTot->fetchColumn();

        // Active donations
        $stmtAct = $this->pdo->prepare("SELECT COUNT(*) FROM donations WHERE donor_id = :uid AND status IN ('active', 'partially_allocated')");
        $stmtAct->execute([':uid' => $donorUserId]);
        $activeDonations = (int) $stmtAct->fetchColumn();

        // Requests received
        $stmtReqRec = $this->pdo->prepare("
            SELECT COUNT(*) FROM donation_requests r
            JOIN donations d ON d.id = r.donation_id
            WHERE d.donor_id = :uid
        ");
        $stmtReqRec->execute([':uid' => $donorUserId]);
        $requestsReceived = (int) $stmtReqRec->fetchColumn();

        // Requests accepted
        $stmtReqAcc = $this->pdo->prepare("
            SELECT COUNT(*) FROM donation_requests r
            JOIN donations d ON d.id = r.donation_id
            WHERE d.donor_id = :uid AND r.status = 'accepted'
        ");
        $stmtReqAcc->execute([':uid' => $donorUserId]);
        $requestsAccepted = (int) $stmtReqAcc->fetchColumn();

        // Scheduled pickups
        $stmtPickSched = $this->pdo->prepare("
            SELECT COUNT(*) FROM pickups p
            JOIN allocations a ON a.id = p.allocation_id
            JOIN donations d ON d.id = a.donation_id
            WHERE d.donor_id = :uid AND p.state IN ('scheduled', 'otp_issued')
        ");
        $stmtPickSched->execute([':uid' => $donorUserId]);
        $scheduledPickups = (int) $stmtPickSched->fetchColumn();

        // Completed handovers
        $stmtPickComp = $this->pdo->prepare("
            SELECT COUNT(*) FROM pickups p
            JOIN allocations a ON a.id = p.allocation_id
            JOIN donations d ON d.id = a.donation_id
            WHERE d.donor_id = :uid AND p.state = 'completed'
        ");
        $stmtPickComp->execute([':uid' => $donorUserId]);
        $completedHandovers = (int) $stmtPickComp->fetchColumn();

        return [
            'role'                => 'donor',
            'total_donations'     => $totalDonations,
            'active_donations'    => $activeDonations,
            'requests_received'   => $requestsReceived,
            'requests_accepted'   => $requestsAccepted,
            'scheduled_pickups'   => $scheduledPickups,
            'completed_handovers' => $completedHandovers,
        ];
    }

    /**
     * NGO Dashboard metrics.
     */
    public function getNgoDashboard(int $ngoId, int $ngoUserId): array
    {
        // Active requirements
        $stmtReq = $this->pdo->prepare("
            SELECT COUNT(*) FROM ngo_requirements
            WHERE ngo_id = :ngo_id AND status IN ('active', 'partially_fulfilled')
        ");
        $stmtReq->execute([':ngo_id' => $ngoId]);
        $activeRequirements = (int) $stmtReq->fetchColumn();

        // Pending requests submitted by NGO
        $stmtPend = $this->pdo->prepare("
            SELECT COUNT(*) FROM donation_requests
            WHERE ngo_id = :ngo_id AND status = 'pending'
        ");
        $stmtPend->execute([':ngo_id' => $ngoId]);
        $pendingRequests = (int) $stmtPend->fetchColumn();

        // Scheduled pickups
        $stmtPickSched = $this->pdo->prepare("
            SELECT COUNT(*) FROM pickups p
            JOIN allocations a ON a.id = p.allocation_id
            JOIN donation_requests r ON r.id = a.request_id
            WHERE r.ngo_id = :ngo_id AND p.state IN ('scheduled', 'otp_issued')
        ");
        $stmtPickSched->execute([':ngo_id' => $ngoId]);
        $scheduledPickups = (int) $stmtPickSched->fetchColumn();

        // Completed handovers
        $stmtPickComp = $this->pdo->prepare("
            SELECT COUNT(*) FROM pickups p
            JOIN allocations a ON a.id = p.allocation_id
            JOIN donation_requests r ON r.id = a.request_id
            WHERE r.ngo_id = :ngo_id AND p.state = 'completed'
        ");
        $stmtPickComp->execute([':ngo_id' => $ngoId]);
        $completedHandovers = (int) $stmtPickComp->fetchColumn();

        // Matched donations count
        $stmtMatches = $this->pdo->prepare("
            SELECT COUNT(DISTINCT d.id)
            FROM donations d
            JOIN users du ON du.id = d.donor_id AND du.account_status = 'active'
            JOIN ngo_requirements req ON req.ngo_id = :ngo_id AND req.status IN ('active', 'partially_fulfilled')
            JOIN ngos n ON n.id = req.ngo_id AND n.verification_status = 'verified'
            WHERE d.status IN ('active', 'partially_allocated')
              AND d.available_quantity > 0
              AND ST_DWithin(d.location, req.location, req.radius_km * 1000)
        ");
        $stmtMatches->execute([':ngo_id' => $ngoId]);
        $matchedDonations = (int) $stmtMatches->fetchColumn();

        return [
            'role'                => 'ngo',
            'active_requirements' => $activeRequirements,
            'pending_requests'    => $pendingRequests,
            'scheduled_pickups'   => $scheduledPickups,
            'completed_handovers' => $completedHandovers,
            'matched_donations'   => $matchedDonations,
        ];
    }

    /**
     * Admin Dashboard metrics.
     */
    public function getAdminDashboard(): array
    {
        $totalUsers = (int) $this->pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        $pendingNgos = (int) $this->pdo->query("SELECT COUNT(*) FROM ngos WHERE verification_status = 'pending'")->fetchColumn();
        $activeDonations = (int) $this->pdo->query("SELECT COUNT(*) FROM donations WHERE status IN ('active', 'partially_allocated')")->fetchColumn();
        $totalCompleted = (int) $this->pdo->query("SELECT COUNT(*) FROM pickups WHERE state = 'completed'")->fetchColumn();

        return [
            'role'                => 'admin',
            'total_users'         => $totalUsers,
            'pending_ngos'        => $pendingNgos,
            'active_donations'    => $activeDonations,
            'completed_handovers' => $totalCompleted,
        ];
    }
}
