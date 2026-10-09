import { useState, useEffect } from 'react';

export default function App() {
  const [health, setHealth] = useState({
    loading: true,
    status: 'checking',
    db: false,
    time: null,
    error: null,
  });

  useEffect(() => {
    let isMounted = true;

    async function checkHealth() {
      try {
        const res = await fetch('/api/health');
        if (!res.ok) {
          throw new Error(`HTTP error ${res.status}`);
        }
        const json = await res.json();
        if (isMounted) {
          if (json.success && json.data) {
            setHealth({
              loading: false,
              status: 'ok',
              db: json.data.db,
              time: json.data.time,
              error: null,
            });
          } else {
            setHealth({
              loading: false,
              status: 'down',
              db: false,
              time: null,
              error: json.error?.message || 'Invalid API response format',
            });
          }
        }
      } catch (err) {
        if (isMounted) {
          setHealth({
            loading: false,
            status: 'down',
            db: false,
            time: null,
            error: err.message,
          });
        }
      }
    }

    checkHealth();
    return () => {
      isMounted = false;
    };
  }, []);

  return (
    <main className="min-h-screen flex flex-col items-center justify-center p-6 bg-slate-900 text-slate-100">
      <div className="w-full max-w-xl p-8 rounded-2xl bg-slate-800/80 backdrop-blur border border-slate-700 shadow-2xl space-y-6 text-center">
        <div className="inline-flex items-center justify-center w-16 h-16 rounded-full bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 font-bold text-2xl">
          SS
        </div>

        <div>
          <h1 className="text-3xl font-extrabold tracking-tight text-white sm:text-4xl">
            ShareSphere
          </h1>
          <p className="mt-2 text-sm text-slate-400">
            Smart Community Donation & NGO Matching Platform
          </p>
        </div>

        <div className="p-4 rounded-xl bg-slate-900/60 border border-slate-700/60 flex items-center justify-between text-left">
          <div>
            <div className="text-xs uppercase tracking-wider text-slate-400 font-medium">
              Backend Service Status
            </div>
            <div className="text-base font-semibold mt-1 flex items-center gap-2">
              <span>API:</span>
              {health.loading ? (
                <span className="text-amber-400">checking...</span>
              ) : health.status === 'ok' ? (
                <span className="text-emerald-400 font-bold">ok</span>
              ) : (
                <span className="text-rose-400 font-bold">down</span>
              )}
            </div>
            {health.time && (
              <div className="text-xs text-slate-500 mt-1">
                Server Time: {health.time}
              </div>
            )}
            {health.error && (
              <div className="text-xs text-rose-400 mt-1">
                Error: {health.error}
              </div>
            )}
          </div>

          <div className="flex items-center">
            {health.loading ? (
              <span className="relative flex h-4 w-4">
                <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                <span className="relative inline-flex rounded-full h-4 w-4 bg-amber-500"></span>
              </span>
            ) : health.status === 'ok' ? (
              <span className="relative flex h-4 w-4">
                <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                <span className="relative inline-flex rounded-full h-4 w-4 bg-emerald-500"></span>
              </span>
            ) : (
              <span className="relative flex h-4 w-4">
                <span className="relative inline-flex rounded-full h-4 w-4 bg-rose-500"></span>
              </span>
            )}
          </div>
        </div>

        <div className="pt-2 border-t border-slate-700/40 text-xs text-slate-500 flex justify-between">
          <span>PostgreSQL + PostGIS & Mailpit active</span>
          <span>Phase 0 Baseline</span>
        </div>
      </div>
    </main>
  );
}
