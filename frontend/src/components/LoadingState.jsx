import React from 'react';
import { Leaf } from 'lucide-react';

export function LoadingState({ message = 'Loading…' }) {
  return (
    <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', padding: '4rem 2rem', gap: '.875rem' }}>
      <div style={{ width: 42, height: 42, background: 'var(--ss-green-bg)', borderRadius: 12, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
        <div className="ss-spinner" />
      </div>
      <p style={{ fontSize: '.875rem', color: 'var(--ss-muted)', margin: 0 }}>{message}</p>
    </div>
  );
}
