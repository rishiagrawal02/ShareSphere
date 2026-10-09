import React from 'react';
import { PageHeader } from '../../components/PageHeader';
import { PackagePlus, Send, Clock, KeyRound, CheckCircle2, Search, Building2 } from 'lucide-react';

export function HowItWorksPage() {
  return (
    <div className="max-w-4xl mx-auto space-y-12">
      <PageHeader
        title="How ShareSphere Works"
        subtitle="A step-by-step guide to donating surplus items and requesting supplies as a verified NGO."
        breadcrumbs={[{ label: 'Home', to: '/' }, { label: 'How It Works' }]}
      />

      {/* For Donors */}
      <section className="space-y-6">
        <div className="flex items-center gap-3">
          <div className="w-8 h-8 rounded-xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center font-bold text-sm">
            1
          </div>
          <h2 className="text-2xl font-bold text-white">For Donors (Giving Surplus Goods)</h2>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
          <div className="bg-slate-900/50 border border-slate-800 rounded-2xl p-6">
            <PackagePlus className="w-8 h-8 text-emerald-400 mb-4" />
            <h3 className="text-base font-bold text-white mb-2">1. Post Listing</h3>
            <p className="text-xs text-slate-400 leading-relaxed">
              Upload photos, select a category, specify total quantity, condition, and choose your general neighborhood location.
            </p>
          </div>

          <div className="bg-slate-900/50 border border-slate-800 rounded-2xl p-6">
            <Send className="w-8 h-8 text-teal-400 mb-4" />
            <h3 className="text-base font-bold text-white mb-2">2. Review Requests</h3>
            <p className="text-xs text-slate-400 leading-relaxed">
              Verified local charities review matching donations and submit quantity requests. You review their organization credentials and accept.
            </p>
          </div>

          <div className="bg-slate-900/50 border border-slate-800 rounded-2xl p-6">
            <KeyRound className="w-8 h-8 text-cyan-400 mb-4" />
            <h3 className="text-base font-bold text-white mb-2">3. OTP Handover</h3>
            <p className="text-xs text-slate-400 leading-relaxed">
              Meet at the scheduled pickup time. The NGO presents their 6-digit code, you enter it into your dashboard, and the handover is sealed.
            </p>
          </div>
        </div>
      </section>

      {/* For NGOs */}
      <section className="space-y-6 pt-6 border-t border-slate-800">
        <div className="flex items-center gap-3">
          <div className="w-8 h-8 rounded-xl bg-teal-500/10 text-teal-400 flex items-center justify-center font-bold text-sm">
            2
          </div>
          <h2 className="text-2xl font-bold text-white">For Non-Governmental Organizations</h2>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
          <div className="bg-slate-900/50 border border-slate-800 rounded-2xl p-6">
            <Building2 className="w-8 h-8 text-teal-400 mb-4" />
            <h3 className="text-base font-bold text-white mb-2">1. Verify Organization</h3>
            <p className="text-xs text-slate-400 leading-relaxed">
              Register with your legal NGO identification, service radius, and official registration documents for administrator review.
            </p>
          </div>

          <div className="bg-slate-900/50 border border-slate-800 rounded-2xl p-6">
            <Search className="w-8 h-8 text-emerald-400 mb-4" />
            <h3 className="text-base font-bold text-white mb-2">2. Post Needs & Match</h3>
            <p className="text-xs text-slate-400 leading-relaxed">
              Create specific requirements with required quantity and urgency. Our engine matches available donations within your service radius.
            </p>
          </div>

          <div className="bg-slate-900/50 border border-slate-800 rounded-2xl p-6">
            <CheckCircle2 className="w-8 h-8 text-cyan-400 mb-4" />
            <h3 className="text-base font-bold text-white mb-2">3. Pickup & Receipt</h3>
            <p className="text-xs text-slate-400 leading-relaxed">
              Agree on a convenient pickup schedule. Receive your secure verification code via email, collect the items, and confirm final receipt.
            </p>
          </div>
        </div>
      </section>
    </div>
  );
}
