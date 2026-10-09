<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Services\ReportService;

class AdminReportController
{
    private ReportService $service;

    public function __construct(?ReportService $service = null)
    {
        $this->service = $service ?? new ReportService();
    }

    /**
     * GET /api/admin/reports/summary?from=&to=&format=
     */
    public function summary(Request $request): Response
    {
        $params = $request->getQueryParams();
        $from = isset($params['from']) ? (string) $params['from'] : null;
        $to = isset($params['to']) ? (string) $params['to'] : null;
        $format = isset($params['format']) ? strtolower((string) $params['format']) : 'json';

        $data = $this->service->getSummary($from, $to);

        if ($format === 'csv') {
            $headers = ['Metric Category', 'Metric Key', 'Count / Value'];
            $rows = [];
            foreach ($data['users_by_role'] as $k => $v) {
                $rows[] = ['Users By Role', $k, $v];
            }
            foreach ($data['ngos_by_status'] as $k => $v) {
                $rows[] = ['NGOs By Status', $k, $v];
            }
            foreach ($data['donations_by_status'] as $k => $v) {
                $rows[] = ['Donations By Status', $k, $v];
            }
            foreach ($data['requests_by_status'] as $k => $v) {
                $rows[] = ['Requests By Status', $k, $v];
            }
            $rows[] = ['Handovers', 'Completed Handovers', $data['completed_handovers']];
            $rows[] = ['Handovers', 'Completion Rate (%)', $data['completion_rate']];

            $csv = ReportService::formatCsv($headers, $rows);
            return new Response(200, [
                'Content-Type'        => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="admin_summary_report.csv"',
            ], $csv);
        }

        return Response::success($data);
    }

    /**
     * GET /api/admin/reports/trends?metric=donations|completions&interval=week|day|month&from=&to=
     */
    public function trends(Request $request): Response
    {
        $params = $request->getQueryParams();
        $metric = isset($params['metric']) ? (string) $params['metric'] : 'donations';
        $interval = isset($params['interval']) ? (string) $params['interval'] : 'week';
        $from = isset($params['from']) ? (string) $params['from'] : null;
        $to = isset($params['to']) ? (string) $params['to'] : null;

        $trends = $this->service->getTrends($metric, $interval, $from, $to);

        return Response::success($trends, [
            'metric'   => $metric,
            'interval' => $interval,
        ]);
    }

    /**
     * GET /api/admin/reports/categories
     */
    public function categories(Request $request): Response
    {
        $dist = $this->service->getCategoryDistribution();
        return Response::success($dist);
    }

    /**
     * GET /api/admin/audit-logs?actor_id=&action=&target_type=&from=&to=&page=&limit=&format=
     */
    public function auditLogs(Request $request): Response
    {
        $params = $request->getQueryParams();
        $filters = [];

        if (!empty($params['actor_id'])) {
            $filters['actor_id'] = (int) $params['actor_id'];
        }
        if (!empty($params['action'])) {
            $filters['action'] = (string) $params['action'];
        }
        if (!empty($params['target_type'])) {
            $filters['target_type'] = (string) $params['target_type'];
        }
        if (!empty($params['from'])) {
            $filters['from'] = (string) $params['from'];
        }
        if (!empty($params['to'])) {
            $filters['to'] = (string) $params['to'];
        }

        $page = isset($params['page']) ? (int) $params['page'] : 1;
        $limit = isset($params['limit']) ? (int) $params['limit'] : 50;
        $format = isset($params['format']) ? strtolower((string) $params['format']) : 'json';

        $result = $this->service->getAuditLogs($filters, $page, $limit);

        if ($format === 'csv') {
            $headers = ['ID', 'Action', 'Actor ID', 'Actor Name', 'Actor Role', 'Target Type', 'Target ID', 'Result', 'Created At'];
            $rows = [];
            foreach ($result['items'] as $item) {
                $rows[] = [
                    $item['id'],
                    $item['action'],
                    $item['actor']['id'] ?? '',
                    $item['actor']['name'] ?? '',
                    $item['actor']['role'] ?? '',
                    $item['target']['type'] ?? '',
                    $item['target']['id'] ?? '',
                    $item['result'],
                    $item['created_at'],
                ];
            }

            $csv = ReportService::formatCsv($headers, $rows);
            return new Response(200, [
                'Content-Type'        => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="audit_logs.csv"',
            ], $csv);
        }

        return Response::success($result['items'], [
            'total' => $result['total'],
            'page'  => $result['page'],
            'limit' => $result['limit'],
            'pages' => $result['pages'],
        ]);
    }
}
