import React from 'react';
import { HeartHandshake, Shield, Recycle, Users, Target } from 'lucide-react';
import { PageHeader } from '../../components/PageHeader';

export function AboutPage() {
  return (
    <div className="max-w-4xl mx-auto space-y-12">
      <PageHeader
        title="About ShareSphere"
        subtitle="Empowering communities through smart redistribution and transparent non-profit matching."
        breadcrumbs={[{ label: 'Home', to: '/' }, { label: 'About' }]}
      />

      <section className="bg-slate-900/60 border border-slate-800 rounded-3xl p-8 sm:p-10 space-y-6">
        <h2 className="text-2xl font-bold text-white">Our Mission</h2>
        <p className="text-slate-300 text-sm sm:text-base leading-relaxed">
          Millions of high-quality items—educational books, clothing, electronics, and household goods—sit idle or end up in landfills, while registered charities and local relief organizations struggle to procure those exact items for community members in need.
        </p>
        <p className="text-slate-300 text-sm sm:text-base leading-relaxed">
          ShareSphere was created to bridge this gap. By leveraging geographic optimization, category compatibility algorithms, and a tamper-resistant two-party pickup protocol, we eliminate logistics friction and foster a safer, more transparent circular economy.
        </p>
      </section>

      <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div className="bg-slate-900/40 border border-slate-800 rounded-2xl p-6">
          <div className="w-10 h-10 rounded-xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center mb-4">
            <Shield size={20} />
          </div>
          <h3 className="text-base font-bold text-white mb-2">Verified Non-Profit Network</h3>
          <p className="text-xs text-slate-400 leading-relaxed">
            Every participating NGO undergoes strict verification of legal registration and tax-exempt status by platform administrators before being allowed to request items.
          </p>
        </div>

        <div className="bg-slate-900/40 border border-slate-800 rounded-2xl p-6">
          <div className="w-10 h-10 rounded-xl bg-teal-500/10 text-teal-400 flex items-center justify-center mb-4">
            <Recycle size={20} />
          </div>
          <h3 className="text-base font-bold text-white mb-2">Circular Impact</h3>
          <p className="text-xs text-slate-400 leading-relaxed">
            We prioritize extending item lifecycles, directly reducing municipal waste while fulfilling urgent community requirements.
          </p>
        </div>

        <div className="bg-slate-900/40 border border-slate-800 rounded-2xl p-6">
          <div className="w-10 h-10 rounded-xl bg-cyan-500/10 text-cyan-400 flex items-center justify-center mb-4">
            <Target size={20} />
          </div>
          <h3 className="text-base font-bold text-white mb-2">Smart Spatial Matching</h3>
          <p className="text-xs text-slate-400 leading-relaxed">
            Our multi-variable matching engine accounts for distance radius, urgency deadlines, and quantity thresholds to ensure donations go where they are needed most.
          </p>
        </div>

        <div className="bg-slate-900/40 border border-slate-800 rounded-2xl p-6">
          <div className="w-10 h-10 rounded-xl bg-purple-500/10 text-purple-400 flex items-center justify-center mb-4">
            <Users size={20} />
          </div>
          <h3 className="text-base font-bold text-white mb-2">Donor Privacy & Safety</h3>
          <p className="text-xs text-slate-400 leading-relaxed">
            Donor street addresses and exact GPS coordinates are snapped on a 0.005° grid and never revealed until a pickup is officially confirmed.
          </p>
        </div>
      </div>
    </div>
  );
}
