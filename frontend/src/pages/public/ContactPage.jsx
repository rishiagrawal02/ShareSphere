import React from 'react';
import { PageHeader } from '../../components/PageHeader';
import { Mail, MapPin, Clock, ShieldAlert, FileText } from 'lucide-react';

export function ContactPage() {
  return (
    <div className="max-w-4xl mx-auto space-y-12">
      <PageHeader
        title="Contact & Support"
        subtitle="Get in touch with the ShareSphere team or submit an administrative inquiry."
        breadcrumbs={[{ label: 'Home', to: '/' }, { label: 'Contact' }]}
      />

      <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
        {/* Support Channels */}
        <div className="bg-slate-900/60 border border-slate-800 rounded-3xl p-8 space-y-6">
          <h2 className="text-xl font-bold text-white">Direct Support Inquiries</h2>
          <p className="text-xs text-slate-400 leading-relaxed">
            For technical assistance, NGO registration support, or partnership inquiries, reach out to our team.
          </p>

          <div className="space-y-4 pt-2">
            <div className="flex items-start gap-3.5">
              <div className="w-9 h-9 rounded-xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center shrink-0">
                <Mail size={18} />
              </div>
              <div>
                <span className="text-xs text-slate-400 block">General & Technical Email</span>
                <a href="mailto:support@sharesphere.local" className="text-sm font-semibold text-white hover:text-emerald-400 transition-colors">
                  support@sharesphere.local
                </a>
              </div>
            </div>

            <div className="flex items-start gap-3.5">
              <div className="w-9 h-9 rounded-xl bg-teal-500/10 text-teal-400 flex items-center justify-center shrink-0">
                <Clock size={18} />
              </div>
              <div>
                <span className="text-xs text-slate-400 block">Support Response Window</span>
                <span className="text-sm font-semibold text-slate-200">
                  Monday – Friday, 9:00 AM – 6:00 PM (UTC)
                </span>
              </div>
            </div>

            <div className="flex items-start gap-3.5">
              <div className="w-9 h-9 rounded-xl bg-purple-500/10 text-purple-400 flex items-center justify-center shrink-0">
                <MapPin size={18} />
              </div>
              <div>
                <span className="text-xs text-slate-400 block">Headquarters</span>
                <span className="text-sm font-semibold text-slate-200">
                  ShareSphere Foundation, Civic Tech Center
                </span>
              </div>
            </div>
          </div>
        </div>

        {/* Account Suspension & Appeals */}
        <div className="bg-slate-900/60 border border-slate-800 rounded-3xl p-8 space-y-6">
          <div className="flex items-center gap-3 text-amber-400">
            <ShieldAlert size={22} />
            <h2 className="text-xl font-bold text-white">Administrative Appeals</h2>
          </div>

          <p className="text-xs text-slate-300 leading-relaxed">
            If your account was suspended and you wish to file a formal appeal, please consult our published administrative policy for procedural guidelines.
          </p>

          <div className="bg-slate-800/40 border border-slate-700/60 rounded-2xl p-5 space-y-3 text-xs text-slate-400">
            <div className="font-semibold text-white flex items-center gap-2">
              <FileText size={15} className="text-emerald-400" />
              <span>How to submit an appeal:</span>
            </div>
            <ol className="list-decimal list-inside space-y-1.5 leading-relaxed text-slate-300">
              <li>Review the suspension justification sent to your registered email.</li>
              <li>Send an email to <span className="text-emerald-400 font-mono">support@sharesphere.local</span> with subject <span className="font-mono text-slate-200">"APPEAL: [Your Email]"</span>.</li>
              <li>Provide organizational documentation or relevant factual context.</li>
              <li>An administrator will review the audit log records within 3 business days.</li>
            </ol>
          </div>
        </div>
      </div>
    </div>
  );
}
