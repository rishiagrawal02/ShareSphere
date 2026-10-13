import React from 'react';
import { Link } from 'react-router-dom';
import {
  Leaf,
  Package,
  ShieldCheck,
  Recycle,
  Sparkles,
  ArrowRight,
  CheckCircle2,
  MapPin,
  Users,
  Search,
} from 'lucide-react';

const steps = [
  {
    icon: Package,
    title: 'Donate Items',
    desc: 'Easily upload items you want to donate.',
    color: '#3a7c52',
    bg: '#eef6f1',
  },
  {
    icon: ShieldCheck,
    title: 'Support NGOs',
    desc: 'Help verified and trusted organisations.',
    color: '#3b82f6',
    bg: '#eff6ff',
  },
  {
    icon: Recycle,
    title: 'Reduce Waste',
    desc: 'Give items a second life with purpose.',
    color: '#f59e0b',
    bg: '#fffbeb',
  },
  {
    icon: Sparkles,
    title: 'Create Impact',
    desc: 'Give items a more sustainable second.',
    color: '#8b5cf6',
    bg: '#f5f3ff',
  },
];

const categories = [
  { label: 'Clothes', icon: '👕' },
  { label: 'Books', icon: '📚' },
  { label: 'Furniture', icon: '🪑' },
  { label: 'Electronics', icon: '📱' },
  { label: 'Food', icon: '🥫' },
  { label: 'Toys', icon: '🧸' },
];

export function LandingPage() {
  return (
    <div style={{ background: 'var(--ss-bg)' }}>
      {/* ── Hero ─────────────────────────────────────────── */}
      <section
        style={{
          background: 'linear-gradient(135deg,#e8f5ee 0%,#f5f7f5 55%,#eef0f8 100%)',
          padding: '0',
          overflow: 'hidden',
        }}
      >
        <div
          style={{
            maxWidth: 1200,
            margin: '0 auto',
            padding: '4rem 2rem 3rem',
            display: 'grid',
            gridTemplateColumns: '1fr 1fr',
            gap: '3rem',
            alignItems: 'center',
          }}
        >
          {/* Left text */}
          <div className="ss-fade-in">
            <h1
              style={{
                fontSize: 'clamp(1.75rem, 4vw, 2.75rem)',
                fontWeight: 800,
                color: 'var(--ss-text)',
                lineHeight: 1.2,
                margin: '0 0 1rem',
              }}
            >
              Give What You Don't Need{' '}
              <span style={{ color: 'var(--ss-green)' }}>
                To Those Who Do
              </span>
            </h1>
            <p style={{ fontSize: '.9375rem', color: 'var(--ss-muted)', lineHeight: 1.7, marginBottom: '1.75rem', maxWidth: 440 }}>
              Share household items, books, clothes, furniture and more with verified NGOs near you.
            </p>

            {/* Search bar */}
            <div
              className="ss-search-box"
              style={{ maxWidth: 420, marginBottom: '1.5rem' }}
            >
              <Search size={16} style={{ color: 'var(--ss-subtle)', flexShrink: 0 }} />
              <input placeholder="Search items or NGOs near you..." />
              <button className="ss-btn ss-btn-primary ss-btn-sm" style={{ flexShrink: 0 }}>
                <Search size={14} />
              </button>
            </div>

            {/* CTAs */}
            <div style={{ display: 'flex', gap: '.75rem', flexWrap: 'wrap' }}>
              <Link to="/signup?role=donor" className="ss-btn ss-btn-primary" style={{ padding: '.6rem 1.375rem' }}>
                Start Donating
                <ArrowRight size={15} />
              </Link>
              <Link to="/signup?role=ngo" className="ss-btn ss-btn-ghost" style={{ padding: '.6rem 1.375rem' }}>
                <ShieldCheck size={15} style={{ color: 'var(--ss-green)' }} />
                Register as NGO
              </Link>
            </div>
          </div>

          {/* Right illustration box */}
          <div style={{ display: 'flex', justifyContent: 'center', alignItems: 'center' }}>
            <div
              style={{
                width: 320,
                height: 260,
                background: 'linear-gradient(135deg,#3a7c52 0%,#4caf70 100%)',
                borderRadius: 24,
                display: 'flex',
                flexDirection: 'column',
                alignItems: 'center',
                justifyContent: 'center',
                color: '#fff',
                gap: '1rem',
                boxShadow: '0 20px 60px rgba(58,124,82,.3)',
                position: 'relative',
                overflow: 'hidden',
              }}
            >
              <div style={{ position: 'absolute', top: -30, right: -30, width: 120, height: 120, background: 'rgba(255,255,255,.08)', borderRadius: '50%' }} />
              <Package size={60} style={{ opacity: .9 }} />
              <div style={{ textAlign: 'center' }}>
                <div style={{ fontSize: '1.5rem', fontWeight: 800 }}>DONATION</div>
                <div style={{ fontSize: '.8rem', opacity: .85 }}>Making a difference, one item at a time</div>
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* ── How it works ─────────────────────────────────── */}
      <section style={{ maxWidth: 1200, margin: '0 auto', padding: '3.5rem 2rem' }}>
        <div style={{ textAlign: 'center', marginBottom: '2.5rem' }}>
          <div className="ss-badge ss-badge-info" style={{ marginBottom: '.75rem' }}>
            How it works
          </div>
          <h2 style={{ fontSize: '1.625rem', fontWeight: 800, color: 'var(--ss-text)', margin: '0 0 .5rem' }}>
            Simple Steps, Big Changes
          </h2>
          <p style={{ color: 'var(--ss-muted)', fontSize: '.875rem' }}>Connect. Share. Support.</p>
        </div>

        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(200px,1fr))', gap: '1.25rem' }}>
          {steps.map((s, i) => {
            const Icon = s.icon;
            return (
              <div
                key={i}
                className="ss-card"
                style={{ padding: '1.5rem 1.25rem', textAlign: 'center', transition: 'all .18s ease' }}
                onMouseEnter={e => { e.currentTarget.style.transform = 'translateY(-4px)'; e.currentTarget.style.boxShadow = 'var(--shadow-md)'; }}
                onMouseLeave={e => { e.currentTarget.style.transform = ''; e.currentTarget.style.boxShadow = ''; }}
              >
                <div style={{ width: 52, height: 52, background: s.bg, borderRadius: 14, display: 'flex', alignItems: 'center', justifyContent: 'center', margin: '0 auto 1rem' }}>
                  <Icon size={26} style={{ color: s.color }} />
                </div>
                <h3 style={{ fontSize: '.9375rem', fontWeight: 700, color: 'var(--ss-text)', marginBottom: '.375rem' }}>{s.title}</h3>
                <p style={{ fontSize: '.8125rem', color: 'var(--ss-muted)', lineHeight: 1.6 }}>{s.desc}</p>
              </div>
            );
          })}
        </div>
      </section>

      {/* ── Browse categories ─────────────────────────────── */}
      <section style={{ background: 'var(--ss-green-bg)', borderTop: '1px solid var(--ss-green-border)', borderBottom: '1px solid var(--ss-green-border)', padding: '2.5rem 2rem' }}>
        <div style={{ maxWidth: 1200, margin: '0 auto' }}>
          <h2 style={{ fontSize: '1.25rem', fontWeight: 700, color: 'var(--ss-text)', marginBottom: '1.25rem', textAlign: 'center' }}>
            Popular Categories
          </h2>
          <div style={{ display: 'flex', gap: '.875rem', flexWrap: 'wrap', justifyContent: 'center' }}>
            {categories.map(c => (
              <Link
                key={c.label}
                to={`/signup?role=donor&category=${c.label.toLowerCase()}`}
                style={{
                  display: 'flex', alignItems: 'center', gap: '.5rem',
                  padding: '.5rem 1.125rem', background: 'var(--ss-surface)',
                  border: '1px solid var(--ss-border)', borderRadius: 99,
                  fontSize: '.8125rem', fontWeight: 500, color: 'var(--ss-text)',
                  textDecoration: 'none', transition: 'all .13s',
                }}
                onMouseEnter={e => { e.currentTarget.style.borderColor = 'var(--ss-green)'; e.currentTarget.style.color = 'var(--ss-green)'; }}
                onMouseLeave={e => { e.currentTarget.style.borderColor = 'var(--ss-border)'; e.currentTarget.style.color = 'var(--ss-text)'; }}
              >
                <span style={{ fontSize: '1rem' }}>{c.icon}</span>
                {c.label}
              </Link>
            ))}
          </div>
        </div>
      </section>

      {/* ── Trust/Safety ─────────────────────────────────── */}
      <section style={{ maxWidth: 1200, margin: '0 auto', padding: '3.5rem 2rem' }}>
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(220px,1fr))', gap: '1rem' }}>
          {[
            { icon: ShieldCheck, title: 'Verified NGOs Only', desc: 'Manual document review before matching eligibility.' },
            { icon: MapPin, title: 'Location Privacy', desc: 'Exact addresses hidden until pickup is confirmed.' },
            { icon: Package, title: 'Zero-Oversell Engine', desc: 'Atomic reservations guarantee no double-allocation.' },
            { icon: CheckCircle2, title: 'Audit Trail', desc: 'Immutable timelines from draft to completion.' },
          ].map((item, i) => {
            const Icon = item.icon;
            return (
              <div key={i} style={{ display: 'flex', alignItems: 'flex-start', gap: '.875rem', padding: '1.25rem', background: 'var(--ss-surface)', borderRadius: 14, border: '1px solid var(--ss-border)' }}>
                <div style={{ width: 38, height: 38, background: 'var(--ss-green-bg)', borderRadius: 10, display: 'flex', alignItems: 'center', justifyContent: 'center', flexShrink: 0 }}>
                  <Icon size={19} style={{ color: 'var(--ss-green)' }} />
                </div>
                <div>
                  <div style={{ fontSize: '.875rem', fontWeight: 600, color: 'var(--ss-text)', marginBottom: '.25rem' }}>{item.title}</div>
                  <div style={{ fontSize: '.75rem', color: 'var(--ss-muted)', lineHeight: 1.5 }}>{item.desc}</div>
                </div>
              </div>
            );
          })}
        </div>
      </section>

      {/* ── CTA Banner ───────────────────────────────────── */}
      <section style={{ background: 'var(--ss-green)', padding: '3rem 2rem', textAlign: 'center' }}>
        <h2 style={{ fontSize: '1.5rem', fontWeight: 800, color: '#fff', marginBottom: '.75rem' }}>
          Ready to Make an Impact?
        </h2>
        <p style={{ color: 'rgba(255,255,255,.8)', fontSize: '.875rem', marginBottom: '1.5rem' }}>
          Join hundreds of neighbors and charitable organisations making a difference today.
        </p>
        <Link
          to="/signup"
          className="ss-btn"
          style={{ background: '#fff', color: 'var(--ss-green)', fontWeight: 700, padding: '.75rem 2rem', fontSize: '.9375rem', borderRadius: 12 }}
        >
          Create Free Account
          <ArrowRight size={16} />
        </Link>
      </section>
    </div>
  );
}
