import React from 'react';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { AuthProvider } from './auth/AuthContext';
import { ToastProvider } from './components/Toast';
import { AppShell } from './components/AppShell';

// Route Guards
import { RoleRoute } from './routes/RoleRoute';
import { VerifiedNgoRoute } from './routes/VerifiedNgoRoute';

// Public Pages
import { LandingPage } from './pages/public/LandingPage';
import { AboutPage } from './pages/public/AboutPage';
import { HowItWorksPage } from './pages/public/HowItWorksPage';
import { ImpactPage } from './pages/public/ImpactPage';
import { ContactPage } from './pages/public/ContactPage';
import { NotFoundPage } from './pages/public/NotFoundPage';

// Auth Pages
import { LoginPage } from './pages/auth/LoginPage';
import { SignupPage } from './pages/auth/SignupPage';
import { ProfilePage } from './pages/profile/ProfilePage';

// Role Dashboard Stubs (Phase 12 Foundation)
import { DonorDashboardStub } from './pages/donor/DonorDashboardStub';
import { NgoDashboardStub } from './pages/ngo/NgoDashboardStub';
import { AdminDashboardStub } from './pages/admin/AdminDashboardStub';

export function App() {
  return (
    <BrowserRouter>
      <AuthProvider>
        <ToastProvider>
          <AppShell>
            <Routes>
              {/* Public Routes */}
              <Route path="/" element={<LandingPage />} />
              <Route path="/about" element={<AboutPage />} />
              <Route path="/how-it-works" element={<HowItWorksPage />} />
              <Route path="/impact" element={<ImpactPage />} />
              <Route path="/contact" element={<ContactPage />} />

              {/* Authentication */}
              <Route path="/login" element={<LoginPage />} />
              <Route path="/signup" element={<SignupPage />} />

              {/* Profile (Authenticated) */}
              <Route
                path="/profile"
                element={
                  <RoleRoute>
                    <ProfilePage />
                  </RoleRoute>
                }
              />

              {/* Donor Routes */}
              <Route
                path="/donor"
                element={
                  <RoleRoute roles={['donor', 'admin']}>
                    <DonorDashboardStub />
                  </RoleRoute>
                }
              />
              <Route
                path="/donor/donations"
                element={
                  <RoleRoute roles={['donor', 'admin']}>
                    <DonorDashboardStub />
                  </RoleRoute>
                }
              />
              <Route
                path="/donor/donations/new"
                element={
                  <RoleRoute roles={['donor', 'admin']}>
                    <DonorDashboardStub />
                  </RoleRoute>
                }
              />
              <Route
                path="/donor/map"
                element={
                  <RoleRoute roles={['donor', 'admin']}>
                    <DonorDashboardStub />
                  </RoleRoute>
                }
              />

              {/* NGO Routes */}
              <Route
                path="/ngo"
                element={
                  <RoleRoute roles={['ngo', 'admin']}>
                    <NgoDashboardStub />
                  </RoleRoute>
                }
              />
              <Route
                path="/ngo/requirements"
                element={
                  <RoleRoute roles={['ngo', 'admin']}>
                    <VerifiedNgoRoute>
                      <NgoDashboardStub />
                    </VerifiedNgoRoute>
                  </RoleRoute>
                }
              />
              <Route
                path="/ngo/matches"
                element={
                  <RoleRoute roles={['ngo', 'admin']}>
                    <VerifiedNgoRoute>
                      <NgoDashboardStub />
                    </VerifiedNgoRoute>
                  </RoleRoute>
                }
              />
              <Route
                path="/ngo/pickups"
                element={
                  <RoleRoute roles={['ngo', 'admin']}>
                    <VerifiedNgoRoute>
                      <NgoDashboardStub />
                    </VerifiedNgoRoute>
                  </RoleRoute>
                }
              />

              {/* Admin Routes */}
              <Route
                path="/admin"
                element={
                  <RoleRoute roles={['admin']}>
                    <AdminDashboardStub />
                  </RoleRoute>
                }
              />
              <Route
                path="/admin/ngos"
                element={
                  <RoleRoute roles={['admin']}>
                    <AdminDashboardStub />
                  </RoleRoute>
                }
              />
              <Route
                path="/admin/users"
                element={
                  <RoleRoute roles={['admin']}>
                    <AdminDashboardStub />
                  </RoleRoute>
                }
              />
              <Route
                path="/admin/categories"
                element={
                  <RoleRoute roles={['admin']}>
                    <AdminDashboardStub />
                  </RoleRoute>
                }
              />
              <Route
                path="/admin/reports"
                element={
                  <RoleRoute roles={['admin']}>
                    <AdminDashboardStub />
                  </RoleRoute>
                }
              />
              <Route
                path="/admin/audit-logs"
                element={
                  <RoleRoute roles={['admin']}>
                    <AdminDashboardStub />
                  </RoleRoute>
                }
              />

              {/* Notifications */}
              <Route
                path="/notifications"
                element={
                  <RoleRoute>
                    <div className="p-8 text-center text-slate-400">Notification Center</div>
                  </RoleRoute>
                }
              />

              {/* 404 Fallback */}
              <Route path="*" element={<NotFoundPage />} />
            </Routes>
          </AppShell>
        </ToastProvider>
      </AuthProvider>
    </BrowserRouter>
  );
}

export default App;
