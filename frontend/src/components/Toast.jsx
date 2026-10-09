import React, { createContext, useContext, useState, useCallback } from 'react';
import { CheckCircle2, AlertCircle, Info, AlertTriangle, X } from 'lucide-react';

const ToastContext = createContext(null);

const ICONS = {
  success: CheckCircle2,
  error: AlertCircle,
  info: Info,
  warning: AlertTriangle,
};

const STYLES = {
  success: 'bg-emerald-950/90 border-emerald-500/40 text-emerald-200',
  error: 'bg-red-950/90 border-red-500/40 text-red-200',
  info: 'bg-blue-950/90 border-blue-500/40 text-blue-200',
  warning: 'bg-amber-950/90 border-amber-500/40 text-amber-200',
};

export function ToastProvider({ children }) {
  const [toasts, setToasts] = useState([]);

  const addToast = useCallback((type, message, duration = 4000) => {
    const id = 'toast_' + Math.random().toString(36).substring(2, 9);
    setToasts((prev) => [...prev, { id, type, message }]);

    if (duration > 0) {
      setTimeout(() => {
        setToasts((prev) => prev.filter((t) => t.id !== id));
      }, duration);
    }
  }, []);

  const removeToast = useCallback((id) => {
    setToasts((prev) => prev.filter((t) => t.id !== id));
  }, []);

  const toast = {
    success: (msg, dur) => addToast('success', msg, dur),
    error: (msg, dur) => addToast('error', msg, dur),
    info: (msg, dur) => addToast('info', msg, dur),
    warning: (msg, dur) => addToast('warning', msg, dur),
  };

  return (
    <ToastContext.Provider value={toast}>
      {children}
      <div
        aria-live="polite"
        className="fixed bottom-5 right-5 z-50 flex flex-col gap-2.5 max-w-sm w-full pointer-events-none"
      >
        {toasts.map((t) => {
          const Icon = ICONS[t.type] || Info;
          const styleClass = STYLES[t.type] || STYLES.info;

          return (
            <div
              key={t.id}
              role="alert"
              className={`pointer-events-auto flex items-start gap-3 p-4 rounded-2xl border shadow-2xl backdrop-blur-md animate-in slide-in-from-bottom-3 duration-200 ${styleClass}`}
            >
              <Icon size={18} className="shrink-0 mt-0.5" />
              <div className="flex-1 text-xs font-medium leading-relaxed">{t.message}</div>
              <button
                type="button"
                onClick={() => removeToast(t.id)}
                aria-label="Close notification"
                className="opacity-70 hover:opacity-100 p-0.5 rounded-lg transition-opacity"
              >
                <X size={14} />
              </button>
            </div>
          );
        })}
      </div>
    </ToastContext.Provider>
  );
}

export function useToast() {
  const context = useContext(ToastContext);
  if (!context) {
    throw new Error('useToast must be used within a ToastProvider');
  }
  return context;
}
