import React, { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { api, ApiError } from '../../api/client';
import { useToast } from '../../components/Toast';
import { PageHeader } from '../../components/PageHeader';
import { StatusBadge } from '../../components/StatusBadge';
import { Pagination } from '../../components/Pagination';
import { ConfirmDialog } from '../../components/ConfirmDialog';
import { LoadingState } from '../../components/LoadingState';
import { ErrorState } from '../../components/ErrorState';
import { EmptyState } from '../../components/EmptyState';
import {
  Package,
  PlusCircle,
  Sparkles,
  Edit3,
  Trash2,
  Filter,
  Search,
  CheckCircle2,
  Clock,
  Layers,
  Calendar,
  Eye,
  AlertCircle,
} from 'lucide-react';

const STATUS_OPTIONS = [
  { value: '', label: 'All Statuses' },
  { value: 'active', label: 'Active (Available)' },
  { value: 'partially_allocated', label: 'Partially Claimed' },
  { value: 'fully_allocated', label: 'Fully Claimed' },
  { value: 'completed', label: 'Completed' },
  { value: 'draft', label: 'Draft' },
  { value: 'cancelled', label: 'Closed / Cancelled' },
];

export function MyDonations() {
  const { showSuccess, showError } = useToast();

  const [donations, setDonations] = useState([]);
  const [categories, setCategories] = useState([]);
  const [meta, setMeta] = useState({ page: 1, per_page: 9, total: 0, total_pages: 1 });
  
  const [statusFilter, setStatusFilter] = useState('');
  const [categoryFilter, setCategoryFilter] = useState('');
  const [searchQuery, setSearchQuery] = useState('');
  const [page, setPage] = useState(1);

  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  // Close / Delete Dialog State
  const [donationToClose, setDonationToClose] = useState(null);
  const [closing, setClosing] = useState(false);

  // Fetch Categories once on mount
  useEffect(() => {
    api.get('/api/categories')
      .then((res) => {
        if (res?.data) {
          setCategories(res.data);
        }
      })
      .catch(() => {});
  }, []);

  // Load Donations on filter / page change
  const fetchDonations = async () => {
    setLoading(true);
    setError(null);
    try {
      const params = new URLSearchParams({
        scope: 'mine',
        page: String(page),
        per_page: '9',
      });

      if (statusFilter) params.append('status', statusFilter);
      if (categoryFilter) params.append('category_id', categoryFilter);
      if (searchQuery.trim()) params.append('q', searchQuery.trim());

      const res = await api.get(`/api/donations?${params.toString()}`);

      if (res?.data) {
        setDonations(res.data);
      }
      if (res?.meta) {
        setMeta(res.meta);
      }
    } catch (err) {
      if (err instanceof ApiError) {
        setError(err);
      } else {
        setError(new ApiError(500, 'FETCH_FAILED', 'Failed to load your donations.'));
      }
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchDonations();
  }, [page, statusFilter, categoryFilter]);

  const handleSearchSubmit = (e) => {
    e.preventDefault();
    setPage(1);
    fetchDonations();
  };

  const handleCloseConfirm = async () => {
    if (!donationToClose) return;

    setClosing(true);
    try {
      await api.delete(`/api/donations/${donationToClose.id}`);
      showSuccess(`Donation "${donationToClose.title}" was closed successfully.`);
      setDonationToClose(null);
      fetchDonations();
    } catch (err) {
      if (err.status === 409) {
        showError(err.message || 'Cannot close donation because it has active claim allocations in progress.');
      } else {
        showError(err.message || 'Failed to close donation.');
      }
    } finally {
      setClosing(false);
    }
  };

  return (
    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8 animate-fadeIn">
      {/* Header */}
      <PageHeader
        title="My Donation Listings"
        subtitle="Track your listed surplus items, monitor remaining stock, discover matching NGOs, and manage inventory."
        action={
          <Link
            to="/donor/donations/new"
            className="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-semibold text-xs shadow-lg shadow-emerald-950/50 transition-all cursor-pointer"
          >
            <PlusCircle size={16} />
            <span>Post New Donation</span>
          </Link>
        }
      />

      {/* Filter & Search Controls */}
      <div className="bg-slate-900/90 border border-slate-800 rounded-2xl p-4 sm:p-5 shadow-lg space-y-4">
        <form onSubmit={handleSearchSubmit} className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
          {/* Search Query */}
          <div className="relative">
            <input
              type="text"
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              placeholder="Search by title..."
              className="w-full bg-slate-950 border border-slate-800 rounded-xl pl-9 pr-3 py-2 text-xs text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500"
            />
            <Search size={14} className="absolute left-3 top-2.5 text-slate-500" />
          </div>

          {/* Status Filter */}
          <div>
            <select
              value={statusFilter}
              onChange={(e) => {
                setStatusFilter(e.target.value);
                setPage(1);
              }}
              className="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-200 focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500"
            >
              {STATUS_OPTIONS.map((opt) => (
                <option key={opt.value} value={opt.value}>
                  {opt.label}
                </option>
              ))}
            </select>
          </div>

          {/* Category Filter */}
          <div>
            <select
              value={categoryFilter}
              onChange={(e) => {
                setCategoryFilter(e.target.value);
                setPage(1);
              }}
              className="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-200 focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500"
            >
              <option value="">All Categories</option>
              {categories.map((cat) => (
                <option key={cat.id} value={cat.id}>
                  {cat.name}
                </option>
              ))}
            </select>
          </div>

          {/* Search Action */}
          <button
            type="submit"
            className="w-full inline-flex items-center justify-center gap-2 px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-semibold transition-colors cursor-pointer"
          >
            <Filter size={14} />
            <span>Apply Filters</span>
          </button>
        </form>
      </div>

      {/* Main Content Area */}
      {loading ? (
        <LoadingState message="Fetching your donation listings..." />
      ) : error ? (
        <ErrorState error={error} onRetry={fetchDonations} />
      ) : donations.length === 0 ? (
        <EmptyState
          icon={Package}
          title={statusFilter || categoryFilter || searchQuery ? 'No matching donations found' : 'You haven’t posted any donations yet'}
          description={
            statusFilter || categoryFilter || searchQuery
              ? 'Try adjusting your search terms or filters to find what you are looking for.'
              : 'Share your excess clothing, food, books, and essential items with verified local organizations in need.'
          }
          actionLabel={statusFilter || categoryFilter || searchQuery ? 'Clear Filters' : 'Post Your First Donation'}
          onAction={() => {
            if (statusFilter || categoryFilter || searchQuery) {
              setStatusFilter('');
              setCategoryFilter('');
              setSearchQuery('');
              setPage(1);
            } else {
              window.location.assign('/donor/donations/new');
            }
          }}
        />
      ) : (
        <div className="space-y-6">
          {/* Donations Card Grid */}
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            {donations.map((item) => {
              const mainImageId = item.images?.[0]?.id || (Array.isArray(item.image_ids) ? item.image_ids[0] : null);
              const imageUrl = mainImageId ? `/api/media/donation-images/${mainImageId}` : null;
              const availableQty = item.available_quantity ?? item.total_quantity ?? 0;
              const totalQty = item.total_quantity ?? 0;
              const isClosed = item.status === 'completed' || item.status === 'cancelled';

              return (
                <div
                  key={item.id}
                  className="bg-slate-900/90 border border-slate-800 hover:border-slate-700 rounded-2xl overflow-hidden shadow-xl transition-all duration-200 flex flex-col justify-between group"
                >
                  <div>
                    {/* Image Header with Badge Overlay */}
                    <div className="h-48 w-full bg-slate-950 relative overflow-hidden flex items-center justify-center">
                      {imageUrl ? (
                        <img
                          src={imageUrl}
                          alt={item.title}
                          className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                          onError={(e) => {
                            e.currentTarget.style.display = 'none';
                          }}
                        />
                      ) : (
                        <div className="flex flex-col items-center gap-2 text-slate-600">
                          <Package size={40} />
                          <span className="text-xs">No image attached</span>
                        </div>
                      )}

                      <div className="absolute top-3 right-3">
                        <StatusBadge status={item.status} size="sm" />
                      </div>

                      <div className="absolute bottom-3 left-3 px-2.5 py-1 rounded-lg bg-slate-950/80 backdrop-blur-md border border-slate-800 text-[11px] font-semibold text-slate-200 capitalize">
                        {item.category_name || 'Item'}
                      </div>
                    </div>

                    {/* Listing Content */}
                    <div className="p-5 space-y-3">
                      <h3 className="text-base font-bold text-white group-hover:text-emerald-400 transition-colors line-clamp-1">
                        {item.title}
                      </h3>
                      
                      <p className="text-xs text-slate-400 line-clamp-2 leading-relaxed">
                        {item.description || 'No description provided.'}
                      </p>

                      {/* Quantity Progress Bar & Stats */}
                      <div className="bg-slate-950/60 rounded-xl p-3.5 border border-slate-800/80 space-y-2">
                        <div className="flex items-center justify-between text-xs">
                          <span className="text-slate-400">Inventory Status</span>
                          <span className="font-bold text-white">
                            {availableQty} of {totalQty} units left
                          </span>
                        </div>

                        {/* Visual Progress Bar */}
                        <div className="w-full bg-slate-800 h-2 rounded-full overflow-hidden">
                          <div
                            className={`h-full transition-all duration-500 rounded-full ${
                              availableQty === 0
                                ? 'bg-slate-600'
                                : availableQty < totalQty
                                ? 'bg-amber-500'
                                : 'bg-emerald-500'
                            }`}
                            style={{ width: `${totalQty > 0 ? (availableQty / totalQty) * 100 : 0}%` }}
                          />
                        </div>

                        <div className="flex items-center justify-between text-[11px] text-slate-400 pt-1">
                          <span className="capitalize">Condition: <strong className="text-slate-200">{(item.condition || 'good').replace('_', ' ')}</strong></span>
                          <span>Posted: <strong className="text-slate-200">{new Date(item.created_at || Date.now()).toLocaleDateString()}</strong></span>
                        </div>
                      </div>
                    </div>
                  </div>

                  {/* Actions Bar */}
                  <div className="p-5 pt-0 border-t border-slate-800/80 mt-auto space-y-2">
                    <div className="grid grid-cols-2 gap-2 pt-3">
                      <Link
                        to={`/donor/donations/${item.id}`}
                        className="inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-semibold transition-colors"
                      >
                        <Eye size={13} />
                        <span>Details</span>
                      </Link>

                      <Link
                        to={`/donor/donations/${item.id}/matches`}
                        className="inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl bg-emerald-950/70 hover:bg-emerald-900/70 border border-emerald-800/80 text-emerald-400 text-xs font-semibold transition-colors"
                      >
                        <Sparkles size={13} />
                        <span>Find NGOs</span>
                      </Link>
                    </div>

                    {!isClosed && (
                      <div className="flex items-center justify-between gap-2 pt-1">
                        <Link
                          to={`/donor/donations/${item.id}/edit`}
                          className="inline-flex items-center gap-1 text-xs text-slate-400 hover:text-white transition-colors"
                        >
                          <Edit3 size={13} />
                          <span>Edit</span>
                        </Link>

                        <button
                          type="button"
                          onClick={() => setDonationToClose(item)}
                          className="inline-flex items-center gap-1 text-xs text-red-400/80 hover:text-red-300 transition-colors cursor-pointer"
                        >
                          <Trash2 size={13} />
                          <span>Close Listing</span>
                        </button>
                      </div>
                    )}
                  </div>
                </div>
              );
            })}
          </div>

          {/* Pagination */}
          <Pagination
            currentPage={meta.page}
            totalPages={meta.total_pages}
            onPageChange={(newPage) => setPage(newPage)}
          />
        </div>
      )}

      {/* Close Donation Confirmation Dialog */}
      <ConfirmDialog
        isOpen={Boolean(donationToClose)}
        title="Close Donation Listing?"
        message={`Are you sure you want to close "${donationToClose?.title}"? Closed listings will no longer be matched with NGO requirements. If active pickup claims exist, they must be resolved first.`}
        confirmLabel={closing ? 'Closing...' : 'Yes, Close Listing'}
        cancelLabel="Keep Listing Active"
        variant="danger"
        onConfirm={handleCloseConfirm}
        onCancel={() => setDonationToClose(null)}
      />
    </div>
  );
}
