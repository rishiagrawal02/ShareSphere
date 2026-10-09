<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\Exceptions\ForbiddenException;
use App\Http\Exceptions\ValidationFailedException;
use App\Repositories\NgoRepository;
use App\Repositories\ReportRepository;
use App\Support\Database;
use PDO;

class ReportService
{
    private PDO $pdo;
    private ReportRepository $reportRepo;
    private NgoRepository $ngoRepo;

    public function __construct(
        ?PDO $pdo = null,
        ?ReportRepository $reportRepo = null,
        ?NgoRepository $ngoRepo = null
    ) {
        $this->pdo = $pdo ?? Database::getConnection();
        $this->reportRepo = $reportRepo ?? new ReportRepository($this->pdo);
        $this->ngoRepo = $ngoRepo ?? new NgoRepository($this->pdo);
    }

    /**
     * Admin summary metrics with date range bounding (max 366 days).
     */
    public function getSummary(?string $from = null, ?string $to = null): array
    {
        $dates = $this->validateAndBoundDateRange($from, $to);
        return $this->reportRepo->getSummary($dates['from'], $dates['to']);
    }

    /**
     * Admin trends metrics with interval and date range bounding.
     */
    public function getTrends(string $metric, string $interval = 'week', ?string $from = null, ?string $to = null): array
    {
        $metric = strtolower(trim($metric));
        if (!in_array($metric, ['donations', 'completions'], true)) {
            throw new ValidationFailedException(['metric' => "Metric must be either 'donations' or 'completions'"]);
        }

        $interval = strtolower(trim($interval));
        if (!in_array($interval, ['day', 'week', 'month'], true)) {
            throw new ValidationFailedException(['interval' => "Interval must be 'day', 'week', or 'month'"]);
        }

        $dates = $this->validateAndBoundDateRange($from, $to);
        return $this->reportRepo->getTrends($metric, $interval, $dates['from'], $dates['to']);
    }

    /**
     * Categories distribution overview.
     */
    public function getCategoryDistribution(): array
    {
        return $this->reportRepo->getCategoryDistribution();
    }

    /**
     * Filterable audit logs with metadata secret redaction.
     */
    public function getAuditLogs(array $filters = [], int $page = 1, int $limit = 50): array
    {
        $page = max(1, $page);
        $limit = max(1, min(100, $limit));
        $offset = ($page - 1) * $limit;

        if (!empty($filters['from']) || !empty($filters['to'])) {
            $dates = $this->validateAndBoundDateRange($filters['from'] ?? null, $filters['to'] ?? null);
            $filters['from'] = $dates['from'];
            $filters['to'] = $dates['to'];
        }

        $items = $this->reportRepo->getAuditLogs($filters, $limit, $offset);
        $total = $this->reportRepo->countAuditLogs($filters);

        $sanitized = array_map(function (array $row) {
            $meta = $row['metadata'] ? json_decode($row['metadata'], true) : [];
            if (is_array($meta)) {
                // Redact any potential sensitive keys
                foreach (['otp', 'password', 'password_hash', 'token', 'secret', 'key'] as $k) {
                    if (isset($meta[$k])) {
                        unset($meta[$k]);
                    }
                }
            } else {
                $meta = [];
            }

            return [
                'id'         => (int) $row['id'],
                'action'     => $row['action'],
                'actor'      => [
                    'id'    => $row['actor_user_id'] ? (int) $row['actor_user_id'] : null,
                    'name'  => $row['actor_name'] ?? 'System',
                    'email' => $row['actor_email'] ?? null,
                    'role'  => $row['actor_role'] ?? 'system',
                ],
                'target'     => [
                    'type' => $row['target_type'],
                    'id'   => (int) $row['target_id'],
                ],
                'result'     => $row['result'],
                'metadata'   => $meta,
                'created_at' => $row['created_at'],
            ];
        }, $items);

        return [
            'items' => $sanitized,
            'total' => $total,
            'page'  => $page,
            'limit' => $limit,
            'pages' => (int) ceil($total / $limit),
        ];
    }

    /**
     * Role-scoped dashboard summary.
     */
    public function getDashboard(int $userId, string $role): array
    {
        if ($role === 'donor') {
            return $this->reportRepo->getDonorDashboard($userId);
        }

        if ($role === 'ngo') {
            $ngo = $this->ngoRepo->findNgoByUserId($userId);
            if (!$ngo) {
                return [
                    'role'                => 'ngo',
                    'active_requirements' => 0,
                    'pending_requests'    => 0,
                    'scheduled_pickups'   => 0,
                    'completed_handovers' => 0,
                    'matched_donations'   => 0,
                ];
            }
            return $this->reportRepo->getNgoDashboard((int) $ngo['id'], $userId);
        }

        if ($role === 'admin') {
            return $this->reportRepo->getAdminDashboard();
        }

        throw new ForbiddenException('Invalid role for dashboard', 'FORBIDDEN');
    }

    /**
     * CSV export generator with CSV injection formula escaping.
     */
    public static function formatCsv(array $headers, array $rows): string
    {
        $fp = fopen('php://temp', 'r+');
        fputcsv($fp, $headers);

        foreach ($rows as $row) {
            $sanitizedRow = [];
            foreach ($row as $cell) {
                $str = (string) $cell;
                // CSV Formula Injection mitigation (prefix =, +, -, @ with ')
                if (preg_match('/^[=+\-@\t\r]/', $str)) {
                    $str = "'" . $str;
                }
                $sanitizedRow[] = $str;
            }
            fputcsv($fp, $sanitizedRow);
        }

        rewind($fp);
        $csv = stream_get_contents($fp);
        fclose($fp);

        return $csv ?: '';
    }

    /**
     * Validates date formats and asserts date range <= 366 days.
     */
    private function validateAndBoundDateRange(?string $from, ?string $to): array
    {
        $fromTs = null;
        $toTs = null;

        if ($from !== null && $from !== '') {
            $fromTs = strtotime($from);
            if ($fromTs === false) {
                throw new ValidationFailedException(['from' => 'Invalid from date format']);
            }
        }

        if ($to !== null && $to !== '') {
            $toTs = strtotime($to);
            if ($toTs === false) {
                throw new ValidationFailedException(['to' => 'Invalid to date format']);
            }
        }

        if ($fromTs !== null && $toTs !== null) {
            if ($fromTs > $toTs) {
                throw new ValidationFailedException(['from' => 'from date cannot be after to date']);
            }

            // Max 366 days (leap year tolerance)
            $diffSeconds = $toTs - $fromTs;
            if ($diffSeconds > (366 * 86400)) {
                throw new ValidationFailedException(['date_range' => 'Date range cannot exceed 366 days']);
            }
        }

        return [
            'from' => $fromTs !== null ? date('Y-m-d H:i:s', $fromTs) : null,
            'to'   => $toTs !== null ? date('Y-m-d H:i:s', $toTs) : null,
        ];
    }
}
