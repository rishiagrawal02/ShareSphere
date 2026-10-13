import React from 'react';
import { Link } from 'react-router-dom';
import { ChevronRight } from 'lucide-react';

export function PageHeader({ title, subtitle, breadcrumbs = [], action }) {
  return (
    <div style={{ marginBottom: '1.5rem' }}>
      {breadcrumbs.length > 0 && (
        <nav aria-label="Breadcrumb" style={{ display: 'flex', alignItems: 'center', gap: '.25rem', fontSize: '.75rem', color: 'var(--ss-muted)', marginBottom: '.5rem' }}>
          {breadcrumbs.map((crumb, idx) => {
            const isLast = idx === breadcrumbs.length - 1;
            return (
              <React.Fragment key={crumb.label}>
                {crumb.to && !isLast ? (
                  <Link to={crumb.to} style={{ color: 'var(--ss-green)', textDecoration: 'none', fontWeight: 500 }}>{crumb.label}</Link>
                ) : (
                  <span style={{ color: isLast ? 'var(--ss-text)' : 'var(--ss-muted)', fontWeight: isLast ? 600 : 400 }}>
                    {crumb.label}
                  </span>
                )}
                {!isLast && <ChevronRight size={12} style={{ color: 'var(--ss-subtle)', flexShrink: 0 }} />}
              </React.Fragment>
            );
          })}
        </nav>
      )}

      <div style={{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', gap: '1rem', flexWrap: 'wrap' }}>
        <div>
          <h1 className="ss-page-title">{title}</h1>
          {subtitle && <p style={{ fontSize: '.8125rem', color: 'var(--ss-muted)', marginTop: '.25rem' }}>{subtitle}</p>}
        </div>
        {action && <div style={{ display: 'flex', alignItems: 'center', gap: '.75rem', flexShrink: 0 }}>{action}</div>}
      </div>
    </div>
  );
}
