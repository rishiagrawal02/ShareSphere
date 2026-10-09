import React from 'react';
import { Link } from 'react-router-dom';
import { ChevronRight } from 'lucide-react';

export function PageHeader({
  title,
  subtitle,
  breadcrumbs = [],
  action,
}) {
  return (
    <div className="mb-8">
      {breadcrumbs.length > 0 && (
        <nav aria-label="Breadcrumb" className="flex items-center gap-2 text-xs text-slate-400 mb-3">
          {breadcrumbs.map((crumb, idx) => {
            const isLast = idx === breadcrumbs.length - 1;
            return (
              <React.Fragment key={crumb.label}>
                {crumb.to && !isLast ? (
                  <Link to={crumb.to} className="hover:text-emerald-400 transition-colors">
                    {crumb.label}
                  </Link>
                ) : (
                  <span className={isLast ? 'text-slate-200 font-medium' : ''}>
                    {crumb.label}
                  </span>
                )}
                {!isLast && <ChevronRight size={12} className="text-slate-600" />}
              </React.Fragment>
            );
          })}
        </nav>
      )}

      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl sm:text-3xl font-bold text-white tracking-tight">{title}</h1>
          {subtitle && <p className="text-sm text-slate-400 mt-1">{subtitle}</p>}
        </div>
        {action && <div className="flex items-center gap-3 shrink-0">{action}</div>}
      </div>
    </div>
  );
}
