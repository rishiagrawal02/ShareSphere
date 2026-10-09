import React from 'react';
import { PageHeader } from '../../components/PageHeader';
import { useAuth } from '../../auth/AuthContext';
import { Link } from 'react-router-dom';
import { Package, PlusCircle, MapPin, ClipboardList } from 'lucide-react';

export function DonorDashboardStub() {
  const { user } = useAuth();

  return (
    <div className="space-y-8">
      <PageHeader
        title={`Welcome back, ${user?.name || 'Donor'}`}
        subtitle="Manage your surplus donation listings and review non-profit requests."
        action={
          <Link
            to="/donor/donations/new"
            className="inline-flex items-center gap-2 px-5 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-semibold rounded-xl text-xs shadow-lg transition-all"
          >
            <PlusCircle size={16} />
            <span>Donate Items</span>
          </Link>
        }
      />

      <div className="grid grid-cols-1 sm:grid-cols-3 gap-6">
        <div className="bg-slate-900 border border-slate-800 rounded-2xl p-6">
          <span className="text-xs text-slate-400 block mb-1">Donor Status</span>
          <span className="text-2xl font-bold text-white">Active Donor</span>
        </div>
        <div className="bg-slate-900 border border-slate-800 rounded-2xl p-6">
          <span className="text-xs text-slate-400 block mb-1">Role Permission</span>
          <span className="text-2xl font-bold text-emerald-400">Verified</span>
        </div>
        <div className="bg-slate-900 border border-slate-800 rounded-2xl p-6">
          <span className="text-xs text-slate-400 block mb-1">Location Radius</span>
          <span className="text-2xl font-bold text-teal-400">Privacy Snapped (550m)</span>
        </div>
      </div>
    </div>
  );
}
