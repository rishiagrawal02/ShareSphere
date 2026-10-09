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

// Donor Pages (Phase 13)
import { DonorDashboard } from './pages/donor/DonorDashboard';
import { PostDonation } from './pages/donor/PostDonation';
import { MyDonations } from './pages/donor/MyDonations';
import { DonationDetail } from './pages/donor/DonationDetail';
import { RequestsReceived } from './pages/donor/RequestsReceived';
import { MatchedNgos } from './pages/donor/MatchedNgos';

// Role Dashboard Stubs (Phase 12 Foundation)
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

              {/* Donor Routes (Phase 13) */}
              <Route
                path="/donor"
                element={
                  <RoleRoute roles={['donor', 'admin']}>
                    <DonorDashboard />
                  </RoleRoute>
                }
              />
              <Route
                path="/donor/donations"
                element={
                  <RoleRoute roles={['donor', 'admin']}>
                    <MyDonations />
                  </RoleRoute>
                }
              />
              <Route
                path="/donor/donations/new"
                element={
                  <RoleRoute roles={['donor', 'admin']}>
                    <PostDonation />
                  </RoleRoute>
                }
              />
              <Route
                path="/donor/donations/:id"
                element={
                  <RoleRoute roles={['donor', 'admin']}>
                    <DonationDetail />
                  </RoleRoute>
                }
              />
              <Route
                path="/donor/donations/:id/edit"
                element={
                  <RoleRoute roles={['donor', 'admin']}>
                    <PostDonation />
                  </RoleRoute>
                }
              />
              <Route
                path="/donor/donations/:id/matches"
                element={
                  <RoleRoute roles={['donor', 'admin']}>
                    <MatchedNgos />
                  </RoleRoute>
                }
              />
              <Route
                path="/donor/requests"
                element={
                  <RoleRoute roles={['donor', 'admin']}>
                    <RequestsReceived />
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
