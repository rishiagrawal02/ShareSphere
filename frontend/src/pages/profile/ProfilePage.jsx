import React, { useState, useEffect } from 'react';
import { useAuth } from '../../auth/AuthContext';
import { api } from '../../api/client';
import { PageHeader } from '../../components/PageHeader';
import { FormField } from '../../components/FormField';
import { StatusBadge } from '../../components/StatusBadge';
import { useToast } from '../../components/Toast';
import { useSubmitOnce } from '../../components/useSubmitOnce';
import {
  User,
  Building2,
  Lock,
  Phone,
  Mail,
  ShieldCheck,
  FileText,
  Upload,
  AlertTriangle,
  CheckCircle2,
  Trash2,
} from 'lucide-react';

export function ProfilePage() {
  const { user, refresh } = useAuth();
  const toast = useToast();
  const { isSubmitting: isUpdatingProfile, handleSubmit: handleProfileSubmit } = useSubmitOnce();
  const { isSubmitting: isChangingPassword, handleSubmit: handlePasswordSubmit } = useSubmitOnce();
  const { isSubmitting: isUploadingDoc, handleSubmit: handleDocUpload } = useSubmitOnce();

  // Profile Form state
  const [name, setName] = useState('');
  const [phone, setPhone] = useState('');

  // Password Form state
  const [currentPassword, setCurrentPassword] = useState('');
  const [newPassword, setNewPassword] = useState('');
  const [confirmNewPassword, setConfirmNewPassword] = useState('');
  const [passwordErrors, setPasswordErrors] = useState({});

  // NGO Documents & Resubmit
  const [documents, setDocuments] = useState([]);
  const [selectedDocFile, setSelectedDocFile] = useState(null);
  const [resubmitNote, setResubmitNote] = useState('');

  useEffect(() => {
    if (user) {
      setName(user.name || '');
      setPhone(user.phone || '');
    }

    if (user?.role === 'ngo') {
      loadDocuments();
    }
  }, [user]);

  const loadDocuments = async () => {
    try {
      const res = await api.get('/api/profile/ngo-documents');
      setDocuments(res.data || []);
    } catch {
      // Ignored if none
    }
  };

  const onUpdateProfile = handleProfileSubmit(async () => {
    try {
      await api.patch('/api/profile', {
        name: name.trim(),
        phone: phone.trim() || null,
      });
      await refresh();
      toast.success('Profile information updated successfully.');
    } catch (err) {
      toast.error(err.message || 'Failed to update profile.');
    }
  });

  const onChangePassword = handlePasswordSubmit(async () => {
    setPasswordErrors({});

    if (!currentPassword) {
      setPasswordErrors({ current_password: 'Current password is required' });
      return;
    }
    if (newPassword.length < 8) {
      setPasswordErrors({ new_password: 'New password must be at least 8 characters' });
      return;
    }
    if (newPassword !== confirmNewPassword) {
      setPasswordErrors({ confirm_new_password: 'Passwords do not match' });
      return;
    }

    try {
      await api.patch('/api/profile', {
        current_password: currentPassword,
        new_password: newPassword,
      });
      setCurrentPassword('');
      setNewPassword('');
      setConfirmNewPassword('');
      toast.success('Password changed successfully.');
    } catch (err) {
      if (err.fields) {
        setPasswordErrors(err.fields);
      } else {
        toast.error(err.message || 'Failed to update password.');
      }
    }
  });

  const onUploadDocument = handleDocUpload(async () => {
    if (!selectedDocFile) {
      toast.error('Please select a document file first.');
      return;
    }

    const formData = new FormData();
    formData.append('document', selectedDocFile);
    if (resubmitNote.trim()) {
      formData.append('note', resubmitNote.trim());
    }

    try {
      const res = await fetch('/api/profile/ngo-documents', {
        method: 'POST',
        credentials: 'include',
        body: formData,
      });

      const json = await res.json();
      if (!res.ok) {
        throw new Error(json?.error?.message || 'Document upload failed');
      }

      setSelectedDocFile(null);
      setResubmitNote('');
      await loadDocuments();
      await refresh();
      toast.success('Document uploaded successfully.');
    } catch (err) {
      toast.error(err.message || 'Upload failed.');
    }
  });

  const onDeleteDocument = async (docId) => {
    try {
      await api.delete(`/api/profile/ngo-documents/${docId}`);
      await loadDocuments();
      toast.success('Document removed.');
    } catch (err) {
      toast.error(err.message || 'Could not delete document.');
    }
  };

  const onResubmitApplication = async () => {
    try {
      await api.post('/api/profile/ngo-resubmit', { note: resubmitNote.trim() || undefined });
      await refresh();
      toast.success('Application resubmitted for admin review.');
    } catch (err) {
      toast.error(err.message || 'Could not resubmit application.');
    }
  };

  if (!user) return null;

  const isNgo = user.role === 'ngo';
  const ngoStatus = user.ngo?.verification_status || 'pending';

  return (
    <div className="max-w-4xl mx-auto space-y-10">
      <PageHeader
        title="Account & Profile Settings"
        subtitle="Manage your personal details, credentials, and organization status."
        breadcrumbs={[{ label: 'Home', to: '/' }, { label: 'Profile' }]}
      />

      {/* NGO Status Card (if applicable) */}
      {isNgo && (
        <div className="bg-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-8 space-y-6">
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-6 border-b border-slate-800">
            <div>
              <div className="flex items-center gap-3 mb-1">
                <Building2 className="text-emerald-400" size={20} />
                <h2 className="text-lg font-bold text-white">{user.ngo?.organization_name || 'NGO Details'}</h2>
              </div>
              <p className="text-xs text-slate-400 font-mono">
                Reg ID: {user.ngo?.registration_number || 'N/A'}
              </p>
            </div>
            <StatusBadge status={ngoStatus} size="md" />
          </div>

          {user.ngo?.review_note && (
            <div className="p-4 bg-slate-950 border border-slate-800 rounded-2xl">
              <span className="text-[11px] font-bold uppercase tracking-wider text-slate-400 block mb-1">
                Admin Review Remarks
              </span>
              <p className="text-sm text-slate-200">{user.ngo?.review_note}</p>
            </div>
          )}

          {/* Verification Documents Table */}
          <div className="space-y-4">
            <h3 className="text-sm font-bold text-white flex items-center gap-2">
              <FileText size={16} className="text-emerald-400" />
              <span>Verification Documents</span>
            </h3>

            {documents.length > 0 ? (
              <div className="divide-y divide-slate-800 border border-slate-800 rounded-2xl overflow-hidden bg-slate-950/40">
                {documents.map((doc) => (
                  <div key={doc.id} className="p-4 flex items-center justify-between gap-4">
                    <div className="flex items-center gap-3 truncate">
                      <FileText size={18} className="text-slate-400 shrink-0" />
                      <div className="truncate">
                        <span className="text-xs font-medium text-slate-200 block truncate">
                          {doc.file_name || 'Document #' + doc.id}
                        </span>
                        <span className="text-[11px] text-slate-500">
                          Uploaded on {new Date(doc.created_at).toLocaleDateString()}
                        </span>
                      </div>
                    </div>

                    <button
                      type="button"
                      onClick={() => onDeleteDocument(doc.id)}
                      aria-label="Delete document"
                      className="text-slate-500 hover:text-red-400 p-1.5 rounded-lg transition-colors"
                    >
                      <Trash2 size={16} />
                    </button>
                  </div>
                ))}
              </div>
            ) : (
              <p className="text-xs text-slate-500">No verification documents currently on file.</p>
            )}

            {/* Upload new document & resubmit */}
            {(ngoStatus === 'correction_requested' || ngoStatus === 'pending') && (
              <div className="p-5 bg-slate-950 border border-slate-800/80 rounded-2xl space-y-4">
                <h4 className="text-xs font-bold uppercase tracking-wider text-emerald-400">
                  Upload Additional Verification Document
                </h4>

                <div className="flex flex-col sm:flex-row items-center gap-3">
                  <label className="cursor-pointer inline-flex items-center gap-2 px-4 py-2.5 bg-slate-800 hover:bg-slate-700 border border-slate-700 rounded-xl text-xs font-medium text-slate-200 transition-colors shrink-0">
                    <Upload size={14} />
                    <span>Select PDF/Image</span>
                    <input
                      type="file"
                      accept=".pdf,.png,.jpg,.jpeg"
                      onChange={(e) => setSelectedDocFile(e.target.files?.[0] || null)}
                      className="sr-only"
                    />
                  </label>
                  <span className="text-xs text-slate-400 truncate max-w-xs">
                    {selectedDocFile ? selectedDocFile.name : 'No file chosen'}
                  </span>
                  {selectedDocFile && (
                    <button
                      type="button"
                      disabled={isUploadingDoc}
                      onClick={onUploadDocument}
                      className="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-medium transition-all"
                    >
                      {isUploadingDoc ? 'Uploading...' : 'Upload Document'}
                    </button>
                  )}
                </div>

                {ngoStatus === 'correction_requested' && (
                  <div className="pt-2">
                    <button
                      type="button"
                      onClick={onResubmitApplication}
                      className="px-5 py-2.5 bg-teal-600 hover:bg-teal-500 text-white text-xs font-semibold rounded-xl shadow-lg transition-all"
                    >
                      Resubmit Application for Admin Approval
                    </button>
                  </div>
                )}
              </div>
            )}
          </div>
        </div>
      )}

      {/* Basic Profile Details */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
        <div className="bg-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-8 space-y-6">
          <div className="flex items-center gap-3">
            <User className="text-emerald-400" size={20} />
            <h2 className="text-lg font-bold text-white">Personal Information</h2>
          </div>

          <form onSubmit={onUpdateProfile} className="space-y-4">
            <FormField id="profile-name" label="Full Name" required>
              <input
                type="text"
                required
                value={name}
                onChange={(e) => setName(e.target.value)}
                className="w-full bg-slate-950 border border-slate-700/80 rounded-xl px-3.5 py-2.5 text-sm text-white focus:outline-none focus:ring-2 focus:ring-emerald-500/40 focus:border-emerald-500"
              />
            </FormField>

            <FormField id="profile-email" label="Email Address (Read-only)" hint="Email cannot be changed directly">
              <input
                type="email"
                disabled
                value={user.email}
                className="w-full bg-slate-950/50 border border-slate-800 rounded-xl px-3.5 py-2.5 text-sm text-slate-400 cursor-not-allowed"
              />
            </FormField>

            <FormField id="profile-phone" label="Contact Phone">
              <input
                type="tel"
                value={phone}
                onChange={(e) => setPhone(e.target.value)}
                placeholder="+1 555-0199"
                className="w-full bg-slate-950 border border-slate-700/80 rounded-xl px-3.5 py-2.5 text-sm text-white focus:outline-none focus:ring-2 focus:ring-emerald-500/40 focus:border-emerald-500"
              />
            </FormField>

            <button
              type="submit"
              disabled={isUpdatingProfile}
              className="px-6 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-medium rounded-xl text-xs transition-all"
            >
              {isUpdatingProfile ? 'Saving...' : 'Save Profile'}
            </button>
          </form>
        </div>

        {/* Change Password */}
        <div className="bg-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-8 space-y-6">
          <div className="flex items-center gap-3">
            <Lock className="text-emerald-400" size={20} />
            <h2 className="text-lg font-bold text-white">Security & Password</h2>
          </div>

          <form onSubmit={onChangePassword} className="space-y-4">
            <FormField id="current-password" label="Current Password" required error={passwordErrors.current_password}>
              <input
                type="password"
                required
                value={currentPassword}
                onChange={(e) => setCurrentPassword(e.target.value)}
                className="w-full bg-slate-950 border border-slate-700/80 rounded-xl px-3.5 py-2.5 text-sm text-white focus:outline-none focus:ring-2 focus:ring-emerald-500/40 focus:border-emerald-500"
              />
            </FormField>

            <FormField id="new-password" label="New Password" required error={passwordErrors.new_password}>
              <input
                type="password"
                required
                value={newPassword}
                onChange={(e) => setNewPassword(e.target.value)}
                className="w-full bg-slate-950 border border-slate-700/80 rounded-xl px-3.5 py-2.5 text-sm text-white focus:outline-none focus:ring-2 focus:ring-emerald-500/40 focus:border-emerald-500"
              />
            </FormField>

            <FormField
              id="confirm-new-password"
              label="Confirm New Password"
              required
              error={passwordErrors.confirm_new_password}
            >
              <input
                type="password"
                required
                value={confirmNewPassword}
                onChange={(e) => setConfirmNewPassword(e.target.value)}
                className="w-full bg-slate-950 border border-slate-700/80 rounded-xl px-3.5 py-2.5 text-sm text-white focus:outline-none focus:ring-2 focus:ring-emerald-500/40 focus:border-emerald-500"
              />
            </FormField>

            <button
              type="submit"
              disabled={isChangingPassword}
              className="px-6 py-2.5 bg-slate-800 hover:bg-slate-700 text-white font-medium rounded-xl text-xs transition-all"
            >
              {isChangingPassword ? 'Updating...' : 'Change Password'}
            </button>
          </form>
        </div>
      </div>
    </div>
  );
}
