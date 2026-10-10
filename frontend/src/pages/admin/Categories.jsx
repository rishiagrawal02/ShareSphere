import React, { useState, useEffect, useCallback } from 'react';
import { api, ApiError } from '../../api/client';
import { PageHeader } from '../../components/PageHeader';
import { LoadingState } from '../../components/LoadingState';
import { ErrorState } from '../../components/ErrorState';
import { EmptyState } from '../../components/EmptyState';
import { useToast } from '../../components/Toast';
import {
  Layers,
  Plus,
  Edit2,
  Check,
  X,
  AlertCircle,
  Link as LinkIcon,
  Save,
  Trash2,
  RefreshCw,
  Sliders,
} from 'lucide-react';

export function Categories() {
  const { showSuccess, showError } = useToast();
  const [categories, setCategories] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  // Create / Edit modal state
  const [editCategory, setEditCategory] = useState(null); // null (closed), {} (create), or category obj (edit)
  const [formName, setFormName] = useState('');
  const [formDesc, setFormDesc] = useState('');
  const [formActive, setFormActive] = useState(true);
  const [formError, setFormError] = useState('');
  const [savingCategory, setSavingCategory] = useState(false);

  // Compatibility editor state
  const [compatCategory, setCompatCategory] = useState(null); // category being edited
  const [compatList, setCompatList] = useState([]); // [{ compatible_category_id, score_factor }]
  const [savingCompat, setSavingCompat] = useState(false);
  const [compatError, setCompatError] = useState('');
  const [tableError, setTableError] = useState('');

  const loadCategories = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await api.get('/api/admin/categories');
      setCategories(res?.data ?? []);
    } catch (err) {
      setError(
        err instanceof ApiError
          ? err
          : new ApiError(500, 'LOAD_FAILED', 'Could not load categories list.')
      );
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    loadCategories();
  }, [loadCategories]);

  // Open Create Modal
  const handleOpenCreate = () => {
    setEditCategory({});
    setFormName('');
    setFormDesc('');
    setFormActive(true);
    setFormError('');
  };

  // Open Edit Modal
  const handleOpenEdit = (cat) => {
    setEditCategory(cat);
    setFormName(cat.name);
    setFormDesc(cat.description || '');
    setFormActive(Boolean(cat.is_active));
    setFormError('');
  };

  // Submit Category Create / Update
  const handleSubmitCategory = async (e) => {
    e.preventDefault();
    if (!formName.trim()) {
      setFormError('Category name is required.');
      return;
    }

    setSavingCategory(true);
    setFormError('');

    try {
      const payload = {
        name: formName.trim(),
        description: formDesc.trim() || undefined,
        is_active: formActive,
      };

      if (editCategory.id) {
        // Edit
        const res = await api.patch(`/api/admin/categories/${editCategory.id}`, payload);
        showSuccess(`Category "${res?.category?.name || formName}" updated.`);
      } else {
        // Create
        const res = await api.post('/api/admin/categories', payload);
        showSuccess(`Category "${res?.category?.name || formName}" created.`);
      }

      setEditCategory(null);
      await loadCategories();
    } catch (err) {
      // T-16.1-07: Surfacing 409 conflict on category deactivation with usage counts
      const msg = err.message || 'Could not save category.';
      setFormError(msg);
      showError(msg);
    } finally {
      setSavingCategory(false);
    }
  };

  // Direct Active Toggle (T-16.1-07: handles 409 conflict with usage counts)
  const handleToggleActiveStatus = async (cat) => {
    setTableError('');
    try {
      const nextActive = !Boolean(cat.is_active);
      await api.patch(`/api/admin/categories/${cat.id}`, { is_active: nextActive });
      showSuccess(`Category "${cat.name}" marked ${nextActive ? 'active' : 'inactive'}.`);
      await loadCategories();
    } catch (err) {
      const msg = err.message || 'Could not update category status.';
      setTableError(msg);
      showError(msg);
    }
  };

  // Open Compatibility Editor
  const handleOpenCompatibility = (cat) => {
    setCompatCategory(cat);
    // Initialize with current compatibilities if present
    const existing = cat.compatibilities || cat.compatibility || [];
    setCompatList(
      existing.map((c) => ({
        compatible_category_id: c.compatible_category_id || c.id,
        score_factor: c.score_factor ?? 1.0,
      }))
    );
    setCompatError('');
  };

  // Add pair in Compatibility Editor
  const handleAddCompatRow = () => {
    const available = categories.filter(
      (c) =>
        c.id !== compatCategory.id &&
        !compatList.some((item) => Number(item.compatible_category_id) === Number(c.id))
    );
    if (available.length === 0) {
      setCompatError('All available categories are already configured.');
      return;
    }
    setCompatList((prev) => [
      ...prev,
      { compatible_category_id: available[0].id, score_factor: 0.8 },
    ]);
  };

  // Save Compatibility Matrix (PUT /api/admin/categories/:id/compatibility)
  const handleSaveCompatibility = async (e) => {
    e.preventDefault();
    setSavingCompat(true);
    setCompatError('');

    try {
      const payload = {
        compatibility: compatList.map((c) => ({
          compatible_category_id: Number(c.compatible_category_id),
          score_factor: parseFloat(c.score_factor),
        })),
      };

      await api.put(`/api/admin/categories/${compatCategory.id}/compatibility`, payload);
      showSuccess('Category compatibility rules updated successfully.');
      setCompatCategory(null);
      await loadCategories();
    } catch (err) {
      setCompatError(err.message || 'Failed to update category compatibility.');
      showError(err.message || 'Failed to save compatibility.');
    } finally {
      setSavingCompat(false);
    }
  };

  return (
    <div className="space-y-6">
      <PageHeader
        title="Donation Categories & Compatibility"
        subtitle="Manage donation taxonomy, category active states, and cross-category compatibility matching algorithms."
        action={
          <div className="flex items-center gap-2">
            <button
              type="button"
              onClick={loadCategories}
              aria-label="Refresh categories"
              className="flex items-center gap-1.5 px-3 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs border border-slate-700 transition-all"
            >
              <RefreshCw size={14} />
            </button>
            <button
              type="button"
              id="create-category-btn"
              onClick={handleOpenCreate}
              className="flex items-center gap-1.5 px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-semibold shadow-lg shadow-emerald-950/40 transition-all"
            >
              <Plus size={14} />
              <span>New Category</span>
            </button>
          </div>
        }
      />

      {tableError && (
        <div
          role="alert"
          id="category-table-error"
          className="p-4 rounded-xl bg-red-500/10 border border-red-500/30 text-red-300 text-xs flex items-start gap-2"
        >
          <AlertCircle size={16} className="shrink-0 mt-0.5" />
          <span className="flex-1 font-medium">{tableError}</span>
          <button
            type="button"
            onClick={() => setTableError('')}
            className="text-red-400 hover:text-red-200"
          >
            ✕
          </button>
        </div>
      )}

      {loading ? (
        <LoadingState message="Loading categories…" />
      ) : error ? (
        <ErrorState error={error} onRetry={loadCategories} />
      ) : categories.length === 0 ? (
        <EmptyState
          icon={Layers}
          title="No categories configured"
          description="Create your first item category to enable donor listings and NGO matching."
          actionText="Create Category"
          onAction={handleOpenCreate}
        />
      ) : (
        <div className="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-xl">
          <div className="overflow-x-auto">
            <table className="w-full text-left text-xs text-slate-300">
              <thead className="bg-slate-950/80 text-slate-400 uppercase font-semibold text-[11px] border-b border-slate-800">
                <tr>
                  <th scope="col" className="px-5 py-3.5">Category</th>
                  <th scope="col" className="px-5 py-3.5">Description</th>
                  <th scope="col" className="px-5 py-3.5">Compatibility Rules</th>
                  <th scope="col" className="px-5 py-3.5">Status</th>
                  <th scope="col" className="px-5 py-3.5 text-right">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-800/60">
                {categories.map((cat) => {
                  const compatCount = (cat.compatibilities || cat.compatibility || []).length;
                  const isActive = Boolean(cat.is_active);

                  return (
                    <tr key={cat.id} className="hover:bg-slate-800/40 transition-colors">
                      <td className="px-5 py-4 font-bold text-white text-sm">
                        {/* Strictly text to prevent XSS (T-16.1-04) */}
                        {cat.name}
                      </td>
                      <td className="px-5 py-4 text-slate-400 max-w-xs truncate">
                        {cat.description || '—'}
                      </td>
                      <td className="px-5 py-4">
                        <span className="px-2.5 py-1 rounded-lg bg-slate-800 text-slate-300 text-[11px] font-medium border border-slate-700/80">
                          {compatCount} compatible rule{compatCount === 1 ? '' : 's'}
                        </span>
                      </td>
                      <td className="px-5 py-4">
                        <button
                          type="button"
                          id={`toggle-category-${cat.id}`}
                          onClick={() => handleToggleActiveStatus(cat)}
                          title={`Click to mark ${isActive ? 'inactive' : 'active'}`}
                          className={`inline-flex items-center gap-1.5 font-medium rounded-full border text-xs px-2.5 py-0.5 transition-all ${
                            isActive
                              ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20 hover:bg-emerald-500/20'
                              : 'bg-slate-800 text-slate-400 border-slate-700 hover:bg-slate-700'
                          }`}
                        >
                          <span
                            className={`w-1.5 h-1.5 rounded-full ${
                              isActive ? 'bg-emerald-400' : 'bg-slate-500'
                            }`}
                          />
                          <span>{isActive ? 'Active' : 'Inactive'}</span>
                        </button>
                      </td>
                      <td className="px-5 py-4 text-right space-x-2">
                        <button
                          type="button"
                          id={`edit-compat-${cat.id}`}
                          onClick={() => handleOpenCompatibility(cat)}
                          title="Configure compatibility"
                          className="inline-flex items-center gap-1 px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl font-medium border border-slate-700 transition-all"
                        >
                          <Sliders size={12} className="text-emerald-400" />
                          <span>Compatibility</span>
                        </button>
                        <button
                          type="button"
                          id={`edit-category-${cat.id}`}
                          onClick={() => handleOpenEdit(cat)}
                          title="Edit category"
                          className="inline-flex items-center gap-1 px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl font-medium border border-slate-700 transition-all"
                        >
                          <Edit2 size={12} />
                          <span>Edit</span>
                        </button>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* Category Create / Edit Modal */}
      {editCategory && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm animate-in fade-in duration-150">
          <div className="bg-slate-900 border border-slate-800 rounded-2xl max-w-md w-full p-6 space-y-5 shadow-2xl">
            <h3 className="text-base font-bold text-white flex items-center gap-2">
              <Layers size={18} className="text-emerald-400" />
              <span>{editCategory.id ? 'Edit Category' : 'Create New Category'}</span>
            </h3>

            <form onSubmit={handleSubmitCategory} className="space-y-4">
              <div>
                <label htmlFor="cat-name" className="block text-xs font-semibold text-slate-300 mb-1">
                  Category Name
                </label>
                <input
                  id="cat-name"
                  type="text"
                  required
                  value={formName}
                  onChange={(e) => {
                    setFormName(e.target.value);
                    setFormError('');
                  }}
                  placeholder="e.g. Winter Clothing, Non-Perishable Food"
                  className="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-emerald-500"
                />
              </div>

              <div>
                <label htmlFor="cat-desc" className="block text-xs font-semibold text-slate-300 mb-1">
                  Description
                </label>
                <textarea
                  id="cat-desc"
                  rows={3}
                  value={formDesc}
                  onChange={(e) => setFormDesc(e.target.value)}
                  placeholder="Items that fall into this classification..."
                  className="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-emerald-500"
                />
              </div>

              <div className="flex items-center justify-between p-3 rounded-xl bg-slate-950 border border-slate-800">
                <div>
                  <span className="font-semibold text-xs text-white block">Active Status</span>
                  <span className="text-[11px] text-slate-400 block">
                    Inactive categories cannot be selected for new donations or requirements.
                  </span>
                </div>
                <label className="relative inline-flex items-center cursor-pointer">
                  <input
                    type="checkbox"
                    id="cat-active-toggle"
                    checked={formActive}
                    onChange={(e) => {
                      setFormActive(e.target.checked);
                      setFormError('');
                    }}
                    className="sr-only peer"
                  />
                  <div className="w-10 h-6 bg-slate-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-600" />
                </label>
              </div>

              {formError && (
                <div
                  role="alert"
                  className="p-3 rounded-xl bg-red-500/10 border border-red-500/30 text-red-300 text-xs flex items-start gap-2"
                >
                  <AlertCircle size={15} className="shrink-0 mt-0.5" />
                  <span>{formError}</span>
                </div>
              )}

              <div className="flex items-center justify-end gap-2 pt-2">
                <button
                  type="button"
                  onClick={() => setEditCategory(null)}
                  className="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-medium transition-all"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  id="save-category-btn"
                  disabled={savingCategory}
                  className="px-5 py-2 bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 text-white rounded-xl text-xs font-semibold transition-all"
                >
                  {savingCategory ? 'Saving…' : editCategory.id ? 'Save Changes' : 'Create Category'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Category Compatibility Matrix Modal */}
      {compatCategory && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm animate-in fade-in duration-150">
          <div className="bg-slate-900 border border-slate-800 rounded-2xl max-w-lg w-full p-6 space-y-5 shadow-2xl">
            <div className="flex items-center justify-between pb-3 border-b border-slate-800">
              <div>
                <h3 className="text-base font-bold text-white flex items-center gap-2">
                  <Sliders size={18} className="text-emerald-400" />
                  <span>Compatibility: {compatCategory.name}</span>
                </h3>
                <p className="text-xs text-slate-400 mt-0.5">
                  Define alternative categories acceptable when matching requirements.
                </p>
              </div>
            </div>

            <form onSubmit={handleSaveCompatibility} className="space-y-4">
              <div className="space-y-3 max-h-60 overflow-y-auto pr-1">
                {compatList.length === 0 ? (
                  <p className="text-xs text-slate-500 text-center py-4">
                    No compatible categories configured. This category will only match with itself.
                  </p>
                ) : (
                  compatList.map((item, idx) => (
                    <div
                      key={idx}
                      className="p-3 bg-slate-950 border border-slate-800 rounded-xl flex items-center gap-3"
                    >
                      <div className="flex-1">
                        <label className="block text-[10px] uppercase font-semibold text-slate-500 mb-1">
                          Compatible Category
                        </label>
                        <select
                          value={item.compatible_category_id}
                          onChange={(e) => {
                            const val = Number(e.target.value);
                            setCompatList((prev) =>
                              prev.map((c, i) =>
                                i === idx ? { ...c, compatible_category_id: val } : c
                              )
                            );
                          }}
                          className="w-full bg-slate-900 border border-slate-800 rounded-lg px-2.5 py-1.5 text-xs text-white focus:outline-none focus:border-emerald-500"
                        >
                          {categories
                            .filter((c) => c.id !== compatCategory.id)
                            .map((c) => (
                              <option key={c.id} value={c.id}>
                                {c.name}
                              </option>
                            ))}
                        </select>
                      </div>

                      <div className="w-24">
                        <label className="block text-[10px] uppercase font-semibold text-slate-500 mb-1">
                          Score Factor
                        </label>
                        <input
                          type="number"
                          step="0.05"
                          min="0.1"
                          max="1.0"
                          value={item.score_factor}
                          onChange={(e) => {
                            const val = parseFloat(e.target.value) || 0;
                            setCompatList((prev) =>
                              prev.map((c, i) => (i === idx ? { ...c, score_factor: val } : c))
                            );
                          }}
                          className="w-full bg-slate-900 border border-slate-800 rounded-lg px-2.5 py-1.5 text-xs text-white text-center focus:outline-none focus:border-emerald-500"
                        />
                      </div>

                      <button
                        type="button"
                        onClick={() => setCompatList((prev) => prev.filter((_, i) => i !== idx))}
                        className="mt-4 p-1.5 text-slate-500 hover:text-red-400 rounded-lg transition-colors"
                        title="Remove rule"
                      >
                        <Trash2 size={14} />
                      </button>
                    </div>
                  ))
                )}
              </div>

              <button
                type="button"
                onClick={handleAddCompatRow}
                className="w-full py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-medium border border-slate-700 transition-all flex items-center justify-center gap-1.5"
              >
                <Plus size={13} />
                <span>Add Compatible Pairing</span>
              </button>

              {compatError && (
                <p className="text-xs text-red-400 font-medium" role="alert">
                  {compatError}
                </p>
              )}

              <div className="flex items-center justify-end gap-2 pt-3 border-t border-slate-800">
                <button
                  type="button"
                  onClick={() => setCompatCategory(null)}
                  className="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-medium transition-all"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  id="save-compat-btn"
                  disabled={savingCompat}
                  className="px-5 py-2 bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 text-white rounded-xl text-xs font-semibold transition-all flex items-center gap-1.5"
                >
                  <Save size={13} />
                  <span>{savingCompat ? 'Saving…' : 'Save Compatibility'}</span>
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
