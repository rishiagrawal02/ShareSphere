import React from 'react';
import { PageHeader } from '../../components/PageHeader';
import { useAuth } from '../../auth/AuthContext';
import { ShieldCheck, Users, BarChart3, Layers } from 'lucide-react';

export function AdminDashboardStub() {
  const { user } = useAuth();

  return (
    <div className="space-y-8">
      <PageHeader
        title="Administrative Control Center"
        subtitle="Review NGO verifications, manage platform users, and inspect compliance audits."
      />

      <div className="grid grid-cols-1 sm:grid-cols-4 gap-6">
        <div className="bg-slate-900 border border-slate-800 rounded-2xl p-6">
          <span className="text-xs text-slate-400 block mb-1">Admin User</span>
          <span className="text-lg font-bold text-white truncate block">{user?.name}</span>
        </div>
        <div className="bg-slate-900 border border-slate-800 rounded-2xl p-6">
          <span className="text-xs text-slate-400 block mb-1">Role Level</span>
          <span className="text-lg font-bold text-purple-400">System Admin</span>
        </div>
        <div className="bg-slate-900 border border-slate-800 rounded-2xl p-6">
          <span className="text-xs text-slate-400 block mb-1">API Surface</span>
          <span className="text-lg font-bold text-emerald-400">Frozen (v1.0)</span>
        </div>
        <div className="bg-slate-900 border border-slate-800 rounded-2xl p-6">
          <span className="text-xs text-slate-400 block mb-1">Security Audit</span>
          <span className="text-lg font-bold text-teal-400">Compliant</span>
        </div>
      </div>
    </div>
  );
}
