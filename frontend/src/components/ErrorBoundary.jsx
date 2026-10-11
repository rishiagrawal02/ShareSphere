import React from 'react';
import { ShieldAlert, RefreshCw, Home } from 'lucide-react';
import { getErrorMessage } from '../lib/errorMessages';

export class ErrorBoundary extends React.Component {
  constructor(props) {
    super(props);
    this.state = {
      hasError: false,
      error: null,
      requestId: null,
    };
  }

  static getDerivedStateFromError(error) {
    // Generate fallback request ID if none present on error
    const reqId =
      error?.requestId ||
      `REQ-${Math.random().toString(36).substring(2, 10).toUpperCase()}`;

    return {
      hasError: true,
      error,
      requestId: reqId,
    };
  }

  componentDidCatch(error, errorInfo) {
    // Log error internally without exposing sensitive stack trace to user
    console.error('Unhandled Application Error:', error, errorInfo);
  }

  handleReset = () => {
    this.setState({
      hasError: false,
      error: null,
      requestId: null,
    });
  };

  render() {
    if (this.state.hasError) {
      const userFriendlyMessage = getErrorMessage(
        this.state.error,
        'An unexpected error occurred while rendering this page.'
      );

      return (
        <div
          data-testid="error-boundary"
          className="min-h-[60vh] flex items-center justify-center p-6 bg-slate-900 text-slate-100"
        >
          <div className="max-w-md w-full bg-slate-950 border border-slate-800 rounded-2xl p-6 sm:p-8 text-center shadow-2xl space-y-5">
            <div className="w-14 h-14 bg-red-500/10 text-red-400 border border-red-500/20 rounded-full flex items-center justify-center mx-auto">
              <ShieldAlert size={28} />
            </div>

            <div className="space-y-2">
              <h2 className="text-xl font-bold text-white">Something Went Wrong</h2>
              <p className="text-xs text-slate-400 leading-relaxed">
                {userFriendlyMessage}
              </p>
            </div>

            {this.state.requestId && (
              <div className="inline-block px-3 py-1.5 rounded-lg bg-slate-900 border border-slate-800 text-[11px] text-slate-400 font-mono">
                Request ID: <span className="text-slate-200 font-semibold">{this.state.requestId}</span>
              </div>
            )}

            <div className="flex items-center justify-center gap-3 pt-2">
              <button
                type="button"
                onClick={this.handleReset}
                id="error-boundary-reset-btn"
                className="inline-flex items-center gap-1.5 px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-xl text-xs font-medium border border-slate-700 transition-all"
              >
                <RefreshCw size={14} />
                <span>Try Again</span>
              </button>
              <a
                href="/"
                id="error-boundary-home-btn"
                className="inline-flex items-center gap-1.5 px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-semibold shadow-md transition-all"
              >
                <Home size={14} />
                <span>Return to Home</span>
              </a>
            </div>
          </div>
        </div>
      );
    }

    return this.props.children;
  }
}
