import React from 'react';

export function FormField({ id, label, required, hint, error, children }) {
  return (
    <div className="ss-form-group">
      {label && (
        <label htmlFor={id} className="ss-form-label">
          {label}
          {required && <span style={{ color: 'var(--ss-red)', marginLeft: '.2rem' }}>*</span>}
        </label>
      )}
      {children}
      {hint && <p className="ss-help">{hint}</p>}
      {error && <p className="ss-field-error">{error}</p>}
    </div>
  );
}
