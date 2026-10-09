import React, { createContext, useContext, useState, useEffect, useCallback } from 'react';
import { api, clearCsrfToken } from '../api/client';

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  const refresh = useCallback(async () => {
    try {
      setLoading(true);
      const res = await api.get('/api/auth/me');
      setUser(res?.data || null);
      setError(null);
      return res?.data || null;
    } catch (err) {
      setUser(null);
      if (err.status !== 401) {
        setError(err.message);
      }
      return null;
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    refresh();

    // Listen for global 401 unauthorized events
    const handleUnauthorized = () => {
      clearCsrfToken();
      setUser(null);
      setLoading(false);
    };

    window.addEventListener('sharesphere:unauthorized', handleUnauthorized);
    return () => window.removeEventListener('sharesphere:unauthorized', handleUnauthorized);
  }, [refresh]);

  const login = async (email, password) => {
    const res = await api.post('/api/auth/login', { email, password });
    const loggedUser = res?.data?.user || res?.data || null;
    setUser(loggedUser);
    return loggedUser;
  };

  const register = async (formData) => {
    const res = await api.post('/api/auth/register', formData);
    const newUser = res?.data?.user || res?.data || null;
    setUser(newUser);
    return newUser;
  };

  const logout = async () => {
    try {
      await api.post('/api/auth/logout');
    } catch {
      // Proceed with client logout regardless of network error
    } finally {
      clearCsrfToken();
      setUser(null);
    }
  };

  return (
    <AuthContext.Provider value={{ user, setUser, loading, error, login, register, logout, refresh }}>
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth() {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error('useAuth must be used within an AuthProvider');
  }
  return context;
}
