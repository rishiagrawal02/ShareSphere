import React from 'react';
import { Package } from 'lucide-react';

export function EmptyState({ icon: Icon = Package, title = 'Nothing here yet', description = '', actionLabel, onAction }) {
  return (
    <div className="ss-empty">
      <div style={{ width: 56, height: 56, background: 'var(--ss-green-bg)', borderRadius: 16, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
        <Icon size={28} style={{ color: 'var(--ss-green)' }} />
      </div>
      <div className="ss-empty-title">{title}</div>
      {description && <div className="ss-empty-desc">{description}</div>}
      {actionLabel && onAction && (
        <button
          type="button"
          onClick={onAction}
          className="ss-btn ss-btn-primary ss-btn-sm"
          style={{ marginTop: '.375rem' }}
        >
          {actionLabel}
        </button>
      )}
    </div>
  );
}
