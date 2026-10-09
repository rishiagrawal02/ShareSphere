import React, { useEffect, useState } from 'react';
import { PageHeader } from '../../components/PageHeader';
import { api } from '../../api/client';
import { LoadingState } from '../../components/LoadingState';
import { Layers, Sparkles, HeartHandshake, CheckCircle2 } from 'lucide-react';

export function ImpactPage() {
  const [categories, setCategories] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    api
      .get('/api/categories')
      .then((res) => {
        setCategories(res.data || []);
      })
      .catch(() => {
        setCategories([]);
      })
      .finally(() => {
        setLoading(false);
      });
  }, []);

  return (
    <div className="max-w-4xl mx-auto space-y-12">
      <PageHeader
        title="Community Impact & Redistribution"
        subtitle="Transparent metrics on community categories and active resource coordination."
        breadcrumbs={[{ label: 'Home', to: '/' }, { label: 'Impact' }]}
      />

      <section className="bg-slate-900/60 border border-slate-800 rounded-3xl p-8 sm:p-10 text-center space-y-4">
        <div className="w-12 h-12 rounded-2xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center mx-auto mb-2">
          <HeartHandshake size={24} />
        </div>
        <h2 className="text-2xl font-bold text-white">Direct Community Redistribution</h2>
        <p className="text-slate-400 text-sm max-w-xl mx-auto leading-relaxed">
          ShareSphere connects community surplus with verified charitable requirements. All metrics represent authentic handovers coordinated across verified partners.
        </p>
      </section>

      {/* Active Resource Categories */}
      <section className="space-y-6">
        <div className="flex items-center justify-between">
          <h3 className="text-xl font-bold text-white flex items-center gap-2">
            <Layers className="text-emerald-400" size={20} />
            <span>Supported Item Categories</span>
          </h3>
          <span className="text-xs text-slate-400">{categories.length} Categories Supported</span>
        </div>

        {loading ? (
          <LoadingState message="Loading platform impact data..." />
        ) : categories.length > 0 ? (
          <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
            {categories.map((cat) => (
              <div
                key={cat.id}
                className="bg-slate-900/40 border border-slate-800 rounded-2xl p-5 hover:border-slate-700 transition-colors"
              >
                <div className="flex items-center gap-2.5 mb-2">
                  <CheckCircle2 size={16} className="text-emerald-400 shrink-0" />
                  <h4 className="text-sm font-bold text-white">{cat.name}</h4>
                </div>
                <p className="text-xs text-slate-400 leading-relaxed">
                  {cat.description || 'Active surplus item category supporting local non-profit requirements.'}
                </p>
              </div>
            ))}
          </div>
        ) : (
          <div className="p-8 bg-slate-900/30 border border-slate-800 rounded-2xl text-center text-slate-400 text-xs">
            Live impact metrics updating. Check back shortly.
          </div>
        )}
      </section>
    </div>
  );
}
