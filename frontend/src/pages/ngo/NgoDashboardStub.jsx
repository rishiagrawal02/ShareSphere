import React from 'react';
import { PageHeader } from '../../components/PageHeader';
import { useAuth } from '../../auth/AuthContext';
import { StatusBadge } from '../../components/StatusBadge';
import { Building2, PlusCircle, MapPin, ClipboardList } from 'lucide-react';

export function NgoDashboardStub() {
  const { user } = useAuth();
  const status = user?.ngo?.verification_status || 'pending';

  return (
    <div className="space-y-8">
      <PageHeader
        title={user?.ngo?.organization_name || 'NGO Dashboard'}
        subtitle="Manage community resource requirements and match with available surplus donations."
        action={<StatusBadge status={status} size="md" />}
      />

      <div className="grid grid-cols-1 sm:grid-cols-3 gap-6">
        <div className="bg-slate-900 border border-slate-800 rounded-2xl p-6">
          <span className="text-xs text-slate-400 block mb-1">Organization</span>
          <span className="text-lg font-bold text-white truncate block">
            {user?.ngo?.organization_name || 'Registered Charity'}
          </span>
        </div>
        <div className="bg-slate-900 border border-slate-800 rounded-2xl p-6">
          <span className="text-xs text-slate-400 block mb-1">Verification</span>
          <span className="text-lg font-bold text-emerald-400 uppercase">{status}</span>
        </div>
        <div className="bg-slate-900 border border-slate-800 rounded-2xl p-6">
          <span className="text-xs text-slate-400 block mb-1">Service Radius</span>
          <span className="text-lg font-bold text-teal-400">
            {user?.ngo?.service_radius_km || 25} km
          </span>
        </div>
      </div>
    </div>
  );
}
