import React from 'react';
import { Link } from 'react-router-dom';
import {
  HeartHandshake,
  ShieldCheck,
  MapPin,
  Sparkles,
  ArrowRight,
  Package,
  Layers,
  CheckCircle2,
  Lock,
} from 'lucide-react';

export function LandingPage() {
  return (
    <div className="space-y-24 py-6">
      {/* Hero Section */}
      <section className="relative overflow-hidden text-center max-w-4xl mx-auto pt-6 pb-12">
        <div className="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs font-semibold uppercase tracking-wider mb-6 animate-pulse">
          <Sparkles size={14} />
          <span>Smart Spatial Matching Platform</span>
        </div>

        <h1 className="text-4xl sm:text-6xl font-extrabold text-white tracking-tight leading-[1.15] mb-6">
          Connecting Community Surplus with{' '}
          <span className="bg-gradient-to-r from-emerald-400 via-teal-300 to-cyan-400 bg-clip-text text-transparent">
            Verified NGO Needs
          </span>
        </h1>

        <p className="text-base sm:text-lg text-slate-300 max-w-2xl mx-auto leading-relaxed mb-10">
          ShareSphere ensures surplus goods, school supplies, and clothing reach legitimate community causes
          through intelligent spatial matching and secure two-party handover.
        </p>

        <div className="flex flex-col sm:flex-row items-center justify-center gap-4">
          <Link
            to="/signup?role=donor"
            className="w-full sm:w-auto px-8 py-4 bg-emerald-600 hover:bg-emerald-500 text-white font-semibold rounded-2xl shadow-xl shadow-emerald-950/60 transition-all flex items-center justify-center gap-2 group"
          >
            <span>Donate Surplus Goods</span>
            <ArrowRight size={18} className="group-hover:translate-x-1 transition-transform" />
          </Link>
          <Link
            to="/signup?role=ngo"
            className="w-full sm:w-auto px-8 py-4 bg-slate-900 hover:bg-slate-800 text-slate-200 hover:text-white font-semibold rounded-2xl border border-slate-700/80 transition-all flex items-center justify-center gap-2"
          >
            <ShieldCheck size={18} className="text-emerald-400" />
            <span>Register as NGO</span>
          </Link>
        </div>
      </section>

      {/* 3 Pillars / How it works summary */}
      <section className="grid grid-cols-1 md:grid-cols-3 gap-8">
        <div className="bg-slate-900/60 border border-slate-800/80 rounded-3xl p-8 flex flex-col items-start hover:border-emerald-500/40 transition-colors">
          <div className="w-14 h-14 bg-emerald-500/10 text-emerald-400 rounded-2xl flex items-center justify-center mb-6">
            <Package size={28} />
          </div>
          <h3 className="text-xl font-bold text-white mb-2">1. List or Request</h3>
          <p className="text-sm text-slate-400 leading-relaxed">
            Donors post surplus items with condition and quantity. Verified NGOs post specific item needs with target urgency.
          </p>
        </div>

        <div className="bg-slate-900/60 border border-slate-800/80 rounded-3xl p-8 flex flex-col items-start hover:border-teal-500/40 transition-colors">
          <div className="w-14 h-14 bg-teal-500/10 text-teal-400 rounded-2xl flex items-center justify-center mb-6">
            <MapPin size={28} />
          </div>
          <h3 className="text-xl font-bold text-white mb-2">2. Spatial Match Engine</h3>
          <p className="text-sm text-slate-400 leading-relaxed">
            Our multi-factor scoring evaluates distance, category compatibility, urgency, and quantity to find optimal matches while protecting donor home privacy.
          </p>
        </div>

        <div className="bg-slate-900/60 border border-slate-800/80 rounded-3xl p-8 flex flex-col items-start hover:border-cyan-500/40 transition-colors">
          <div className="w-14 h-14 bg-cyan-500/10 text-cyan-400 rounded-2xl flex items-center justify-center mb-6">
            <Lock size={28} />
          </div>
          <h3 className="text-xl font-bold text-white mb-2">3. Two-Party OTP Handover</h3>
          <p className="text-sm text-slate-400 leading-relaxed">
            Physical handovers are sealed with secure, timed 6-digit verification codes, ensuring transparent chain-of-custody and zero item loss.
          </p>
        </div>
      </section>

      {/* Trust & Safety Highlights */}
      <section className="bg-gradient-to-b from-slate-900 to-slate-950 border border-slate-800 rounded-3xl p-8 sm:p-12">
        <div className="max-w-3xl mx-auto text-center space-y-4 mb-10">
          <h2 className="text-2xl sm:text-3xl font-bold text-white">Trust, Privacy & Accountability by Design</h2>
          <p className="text-sm text-slate-400">Every feature is built around platform safety and data integrity.</p>
        </div>

        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
          <div className="flex items-start gap-3">
            <CheckCircle2 className="w-5 h-5 text-emerald-400 shrink-0 mt-0.5" />
            <div>
              <h4 className="text-sm font-semibold text-white">Verified NGOs Only</h4>
              <p className="text-xs text-slate-400 mt-1">Manual document review by system admins before matching eligibility.</p>
            </div>
          </div>

          <div className="flex items-start gap-3">
            <CheckCircle2 className="w-5 h-5 text-emerald-400 shrink-0 mt-0.5" />
            <div>
              <h4 className="text-sm font-semibold text-white">Location Privacy</h4>
              <p className="text-xs text-slate-400 mt-1">Exact addresses remain hidden on a ~550m grid until pickup confirmation.</p>
            </div>
          </div>

          <div className="flex items-start gap-3">
            <CheckCircle2 className="w-5 h-5 text-emerald-400 shrink-0 mt-0.5" />
            <div>
              <h4 className="text-sm font-semibold text-white">Zero-Oversell Engine</h4>
              <p className="text-xs text-slate-400 mt-1">Atomic row-level reservation guarantees stock is never double-allocated.</p>
            </div>
          </div>

          <div className="flex items-start gap-3">
            <CheckCircle2 className="w-5 h-5 text-emerald-400 shrink-0 mt-0.5" />
            <div>
              <h4 className="text-sm font-semibold text-white">Audit Trail</h4>
              <p className="text-xs text-slate-400 mt-1">Immutable timelines tracking each handover lifecycle from draft to completion.</p>
            </div>
          </div>
        </div>
      </section>

      {/* CTA Footer Banner */}
      <section className="text-center bg-gradient-to-r from-emerald-900/30 via-slate-900 to-teal-900/30 border border-emerald-500/20 rounded-3xl p-10 sm:p-16">
        <h2 className="text-3xl font-bold text-white mb-4">Ready to Make an Impact?</h2>
        <p className="text-slate-300 text-sm max-w-xl mx-auto mb-8">
          Join hundreds of neighbors and charitable organizations making a difference today.
        </p>
        <Link
          to="/signup"
          className="inline-flex items-center gap-2 px-8 py-3.5 bg-emerald-600 hover:bg-emerald-500 text-white font-semibold rounded-xl shadow-lg transition-all"
        >
          <span>Create Free Account</span>
          <ArrowRight size={16} />
        </Link>
      </section>
    </div>
  );
}
