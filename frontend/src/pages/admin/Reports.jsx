import React, { useState, useEffect, useCallback } from 'react';
import { api, ApiError } from '../../api/client';
import { PageHeader } from '../../components/PageHeader';
import { LoadingState } from '../../components/LoadingState';
import { ErrorState } from '../../components/ErrorState';
import { EmptyState } from '../../components/EmptyState';
import {
  BarChart3,
  Calendar,
  Download,
  Info,
  TrendingUp,
  Table as TableIcon,
  CheckCircle2,
  Package,
  Layers,
  Inbox,
  RefreshCw,
  AlertCircle,
} from 'lucide-react';

export function Reports() {
  const [fromDate, setFromDate] = useState('');
  const [toDate, setToDate] = useState('');
  const [rangeError, setRangeError] = useState('');

  const [summary, setSummary] = useState(null);
  const [trends, setTrends] = useState([]);
  const [trendMetric, setTrendMetric] = useState('donations'); // 'donations' | 'completions'
  const [trendInterval, setTrendInterval] = useState('week'); // 'day' | 'week' | 'month'
  const [categoryDist, setCategoryDist] = useState([]);

  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [showTrendTable, setShowTrendTable] = useState(false);

  // Validate date range <= 366 days (T-16.2-03)
  const validateDateRange = (from, to) => {
    if (!from || !to) {
      setRangeError('');
      return true;
    }
    const start = new Date(from).getTime();
    const end = new Date(to).getTime();

    if (start > end) {
      setRangeError('"From" date cannot be after "To" date.');
      return false;
    }

    const diffDays = (end - start) / (1000 * 3600 * 24);
    if (diffDays > 366) {
      setRangeError('Date range cannot exceed 366 days.');
      return false;
    }

    setRangeError('');
    return true;
  };

  const loadReportData = useCallback(async () => {
    if (!validateDateRange(fromDate, toDate)) return;

    setLoading(true);
    setError(null);
    try {
      let queryParams = '';
      if (fromDate) queryParams += `&from=${encodeURIComponent(fromDate)}`;
      if (toDate) queryParams += `&to=${encodeURIComponent(toDate)}`;

      const [sumRes, trendsRes, catRes] = await Promise.all([
        api.get(`/api/admin/reports/summary?format=json${queryParams}`),
        api.get(
          `/api/admin/reports/trends?metric=${trendMetric}&interval=${trendInterval}${queryParams}`
        ),
        api.get('/api/admin/reports/categories'),
      ]);

      setSummary(sumRes?.data ?? sumRes);
      setTrends(trendsRes?.data ?? []);
      setCategoryDist(catRes?.data ?? []);
    } catch (err) {
      setError(
        err instanceof ApiError ? err : new ApiError(500, 'LOAD_FAILED', 'Could not load report analytics.')
      );
    } finally {
      setLoading(false);
    }
  }, [fromDate, toDate, trendMetric, trendInterval]);

  useEffect(() => {
    loadReportData();
  }, [loadReportData]);

  const handleApplyDates = (e) => {
    e.preventDefault();
    if (validateDateRange(fromDate, toDate)) {
      loadReportData();
    }
  };

  const handleDownloadCsv = () => {
    let url = '/api/admin/reports/summary?format=csv';
    if (fromDate) url += `&from=${encodeURIComponent(fromDate)}`;
    if (toDate) url += `&to=${encodeURIComponent(toDate)}`;
    window.open(url, '_blank');
  };

  const isDataEmpty =
    !summary ||
    ((summary.completed_handovers ?? 0) === 0 &&
      Object.values(summary.donations_by_status ?? {}).every((v) => v === 0) &&
      Object.values(summary.requests_by_status ?? {}).every((v) => v === 0));

  // Trend line coordinates
  const maxTrend = Math.max(...trends.map((t) => Number(t.count) || 0), 5);
  const trendPoints = trends.map((t, i) => {
    const x = trends.length > 1 ? (i / (trends.length - 1)) * 500 : 250;
    const y = 140 - ((Number(t.count) || 0) / maxTrend) * 110;
    return `${x},${y}`;
  }).join(' ');

  return (
    <div className="space-y-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
      <PageHeader
        title="Reports & Analytics"
        subtitle="Platform impact metrics, request fulfillment ratios, and temporal trend analyses."
        action={
          <div className="flex items-center gap-2">
            <button
              type="button"
              onClick={loadReportData}
              aria-label="Refresh reports"
              className="flex items-center gap-1.5 px-3 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs border border-slate-700 transition-all"
            >
              <RefreshCw size={14} />
            </button>
            <button
              type="button"
              onClick={handleDownloadCsv}
              className="flex items-center gap-1.5 px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-xl text-xs font-medium border border-slate-700 transition-all"
            >
              <Download size={14} />
              <span>Export CSV</span>
            </button>
          </div>
        }
      />

      {/* Date Range Selector Form */}
      <form
        onSubmit={handleApplyDates}
        className="bg-slate-900 border border-slate-800 rounded-2xl p-4 sm:p-5 shadow-xl space-y-3"
      >
        <div className="flex flex-col sm:flex-row sm:items-end gap-4">
          <div className="flex-1 grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label htmlFor="from-date" className="block text-xs font-semibold text-slate-400 mb-1">
                From Date
              </label>
              <input
                id="from-date"
                type="date"
                value={fromDate}
                onChange={(e) => {
                  setFromDate(e.target.value);
                  validateDateRange(e.target.value, toDate);
                }}
                className="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-emerald-500"
              />
            </div>
            <div>
              <label htmlFor="to-date" className="block text-xs font-semibold text-slate-400 mb-1">
                To Date
              </label>
              <input
                id="to-date"
                type="date"
                value={toDate}
                onChange={(e) => {
                  setToDate(e.target.value);
                  validateDateRange(fromDate, e.target.value);
                }}
                className="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-emerald-500"
              />
            </div>
          </div>

          <div className="flex items-center gap-2">
            <button
              type="submit"
              className="px-5 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-semibold shadow-md transition-all"
            >
              Apply Filter
            </button>
            {(fromDate || toDate) && (
              <button
                type="button"
                onClick={() => {
                  setFromDate('');
                  setToDate('');
                  setRangeError('');
                }}
                className="px-3 py-2 bg-slate-800 text-slate-400 hover:text-white rounded-xl text-xs border border-slate-700"
              >
                Reset
              </button>
            )}
          </div>
        </div>

        {/* T-16.2-03: Inline error for date range > 366 days */}
        {rangeError && (
          <p className="text-xs text-red-400 font-medium flex items-center gap-1.5" role="alert">
            <AlertCircle size={14} />
            <span>{rangeError}</span>
          </p>
        )}
      </form>

      {loading ? (
        <LoadingState message="Calculating analytics and aggregates…" />
      ) : error ? (
        <ErrorState error={error} onRetry={loadReportData} />
      ) : isDataEmpty ? (
        /* T-16.2-02: Empty range shows empty state */
        <EmptyState
          icon={BarChart3}
          title="No data for this period"
          description="There is no recorded activity or handover metrics within the selected date range. Try widening the timeframe."
        />
      ) : (
        <div className="space-y-8">
          {/* Top Summary Metric Cards */}
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div className="bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-lg">
              <span className="text-xs font-semibold uppercase text-slate-400 block mb-1">
                Completed Handovers
              </span>
              <span className="text-3xl font-extrabold text-white">
                {summary?.completed_handovers ?? 0}
              </span>
              <p className="text-xs text-slate-400 mt-1">Successfully transferred to community</p>
            </div>

            <div className="bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-lg">
              <div className="flex items-center gap-1.5 mb-1">
                <span className="text-xs font-semibold uppercase text-slate-400 block">
                  Completion Rate
                </span>
                {/* T-16.2-01: Definition tooltip */}
                <div
                  className="group relative cursor-help"
                  title="Ratio of successfully completed handovers to accepted donation requests"
                >
                  <Info size={13} className="text-slate-500 hover:text-emerald-400" />
                </div>
              </div>
              <span className="text-3xl font-extrabold text-emerald-400">
                {summary?.completion_rate ?? 0}%
              </span>
              <p className="text-xs text-slate-400 mt-1">
                Ratio of completed to accepted requests
              </p>
            </div>

            <div className="bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-lg">
              <span className="text-xs font-semibold uppercase text-slate-400 block mb-1">
                Accepted Requests
              </span>
              <span className="text-3xl font-extrabold text-teal-400">
                {summary?.requests_by_status?.accepted ?? 0}
              </span>
              <p className="text-xs text-slate-400 mt-1">Matched allocations confirmed</p>
            </div>

            <div className="bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-lg">
              <span className="text-xs font-semibold uppercase text-slate-400 block mb-1">
                Active Listings
              </span>
              <span className="text-3xl font-extrabold text-blue-400">
                {summary?.donations_by_status?.active ?? 0}
              </span>
              <p className="text-xs text-slate-400 mt-1">Live surplus items on platform</p>
            </div>
          </div>

          {/* Temporal Trends Section */}
          <div className="bg-slate-900 border border-slate-800 rounded-2xl p-6 space-y-5 shadow-xl">
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-800">
              <div>
                <h3 className="text-sm font-bold text-white flex items-center gap-2">
                  <TrendingUp size={16} className="text-emerald-400" />
                  <span>Temporal Activity Trends</span>
                </h3>
                <p className="text-xs text-slate-400 mt-0.5">
                  Visual aggregate of platform activities over selected period
                </p>
              </div>

              <div className="flex items-center gap-2">
                <select
                  aria-label="Trend metric"
                  value={trendMetric}
                  onChange={(e) => setTrendMetric(e.target.value)}
                  className="bg-slate-950 border border-slate-800 rounded-xl px-2.5 py-1.5 text-xs text-white"
                >
                  <option value="donations">Donations</option>
                  <option value="completions">Completions</option>
                </select>

                <select
                  aria-label="Trend interval"
                  value={trendInterval}
                  onChange={(e) => setTrendInterval(e.target.value)}
                  className="bg-slate-950 border border-slate-800 rounded-xl px-2.5 py-1.5 text-xs text-white"
                >
                  <option value="day">Daily</option>
                  <option value="week">Weekly</option>
                  <option value="month">Monthly</option>
                </select>

                {/* T-16.2-04 Accessible data table toggle */}
                <button
                  type="button"
                  onClick={() => setShowTrendTable(!showTrendTable)}
                  className="inline-flex items-center gap-1 px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-medium border border-slate-700 transition-all"
                >
                  <TableIcon size={12} />
                  <span>{showTrendTable ? 'Chart View' : 'Table View'}</span>
                </button>
              </div>
            </div>

            {showTrendTable ? (
              <div className="overflow-x-auto">
                <table className="w-full text-left text-xs text-slate-300" role="table" aria-label="Trend data alternative">
                  <thead className="bg-slate-950 text-slate-400 uppercase text-[10px] font-semibold border-b border-slate-800">
                    <tr>
                      <th scope="col" className="px-4 py-2.5">Time Interval</th>
                      <th scope="col" className="px-4 py-2.5 text-right">Event Count</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-800/60">
                    {trends.map((t, i) => {
                      const periodText = t.period || t.label;
                      const dateObj = periodText ? new Date(periodText) : null;
                      const displayPeriod =
                        t.label ||
                        (dateObj && !isNaN(dateObj.getTime())
                          ? dateObj.toLocaleDateString('en-IN')
                          : periodText) ||
                        `Interval ${i + 1}`;

                      return (
                        <tr key={i}>
                          <td className="px-4 py-2.5">
                            {displayPeriod}
                          </td>
                          <td className="px-4 py-2.5 text-right font-bold text-emerald-400">
                            {t.count}
                          </td>
                        </tr>
                      );
                    })}
                  </tbody>
                </table>
              </div>
            ) : trends.length === 0 ? (
              <p className="text-xs text-slate-500 text-center py-10">No trend data for this selection.</p>
            ) : (
              <div className="h-56 w-full bg-slate-950 rounded-xl p-4 flex flex-col justify-between">
                <svg viewBox="0 0 500 150" className="w-full h-full overflow-visible" role="img" aria-label="Activity trend chart">
                  <polyline
                    fill="none"
                    stroke="#10b981"
                    strokeWidth="3"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    points={trendPoints}
                  />
                  {trends.map((t, i) => {
                    const x = trends.length > 1 ? (i / (trends.length - 1)) * 500 : 250;
                    const y = 140 - ((Number(t.count) || 0) / maxTrend) * 110;
                    return (
                      <circle
                        key={i}
                        cx={x}
                        cy={y}
                        r="4"
                        className="fill-emerald-400 stroke-slate-950 stroke-2"
                      />
                    );
                  })}
                </svg>
                <div className="flex justify-between text-[10px] text-slate-500 pt-1">
                  <span>{trends[0]?.period ? new Date(trends[0].period).toLocaleDateString('en-IN', { month: 'short', day: 'numeric' }) : 'Start'}</span>
                  <span>{trends[trends.length - 1]?.period ? new Date(trends[trends.length - 1].period).toLocaleDateString('en-IN', { month: 'short', day: 'numeric' }) : 'Recent'}</span>
                </div>
              </div>
            )}
          </div>

          {/* Breakdown Grids */}
          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            {/* Donations By Status */}
            <div className="bg-slate-900 border border-slate-800 rounded-2xl p-6 space-y-4 shadow-xl">
              <h4 className="text-sm font-bold text-white flex items-center gap-2">
                <Package size={16} className="text-emerald-400" />
                <span>Donation Lifecycle Breakdown</span>
              </h4>
              <div className="divide-y divide-slate-800 text-xs">
                {Object.entries(summary?.donations_by_status ?? {}).map(([status, count]) => (
                  <div key={status} className="py-2.5 flex justify-between">
                    <span className="capitalize text-slate-400">{status.replace('_', ' ')}</span>
                    <span className="font-semibold text-white">{count}</span>
                  </div>
                ))}
              </div>
            </div>

            {/* Requests By Status */}
            <div className="bg-slate-900 border border-slate-800 rounded-2xl p-6 space-y-4 shadow-xl">
              <h4 className="text-sm font-bold text-white flex items-center gap-2">
                <Inbox size={16} className="text-teal-400" />
                <span>Request Outcomes Breakdown</span>
              </h4>
              <div className="divide-y divide-slate-800 text-xs">
                {Object.entries(summary?.requests_by_status ?? {}).map(([status, count]) => (
                  <div key={status} className="py-2.5 flex justify-between">
                    <span className="capitalize text-slate-400">{status}</span>
                    <span className="font-semibold text-white">{count}</span>
                  </div>
                ))}
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
