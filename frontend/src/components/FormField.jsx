import React from 'react';
import { AlertCircle } from 'lucide-react';

export function FormField({
  id,
  label,
  hint,
  error,
  required = false,
  className = '',
  children,
}) {
  const hintId = hint ? `${id}-hint` : undefined;
  const errorId = error ? `${id}-error` : undefined;

  const describedBy = [hintId, errorId].filter(Boolean).join(' ') || undefined;

  // Clone single input/select/textarea child to automatically inject id, aria-invalid, aria-describedby
  const enhancedChild = React.isValidElement(children)
    ? React.cloneElement(children, {
        id,
        'aria-invalid': error ? 'true' : undefined,
        'aria-describedby': describedBy,
        className: `${children.props.className || ''} ${
          error ? '!border-red-500/80 !focus:ring-red-500/30' : ''
        }`,
      })
    : children;

  return (
    <div className={`space-y-1.5 ${className}`}>
      {label && (
        <label htmlFor={id} className="block text-xs font-semibold uppercase tracking-wider text-slate-300">
          {label} {required && <span className="text-emerald-400">*</span>}
        </label>
      )}

      {hint && (
        <p id={hintId} className="text-xs text-slate-400">
          {hint}
        </p>
      )}

      {enhancedChild}

      {error && (
        <p id={errorId} role="alert" className="flex items-center gap-1.5 text-xs text-red-400 mt-1">
          <AlertCircle size={12} className="shrink-0" />
          <span>{error}</span>
        </p>
      )}
    </div>
  );
}
