import React, { useState } from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { ErrorBoundary } from './ErrorBoundary';

const ProblemChild = ({ shouldThrow }) => {
  if (shouldThrow) {
    const err = new Error('Database connection failed');
    err.requestId = 'REQ-TEST-1234';
    throw err;
  }
  return <div>Normal Content</div>;
};

const TestApp = () => {
  const [shouldThrow, setShouldThrow] = useState(true);
  return (
    <div>
      <button onClick={() => setShouldThrow(false)}>Fix Error</button>
      <ErrorBoundary>
        <ProblemChild shouldThrow={shouldThrow} />
      </ErrorBoundary>
    </div>
  );
};

describe('T-17.1-07: Error Boundary Component', () => {
  const originalError = console.error;
  beforeEach(() => {
    console.error = vi.fn();
  });
  afterEach(() => {
    console.error = originalError;
  });

  it('renders children normally when no error occurs', () => {
    render(
      <ErrorBoundary>
        <ProblemChild shouldThrow={false} />
      </ErrorBoundary>
    );

    expect(screen.getByText('Normal Content')).toBeInTheDocument();
  });

  it('catches thrown error and renders friendly page with request ID and no raw stack trace', () => {
    render(
      <ErrorBoundary>
        <ProblemChild shouldThrow={true} />
      </ErrorBoundary>
    );

    expect(screen.getByTestId('error-boundary')).toBeInTheDocument();
    expect(screen.getByText('Something Went Wrong')).toBeInTheDocument();
    expect(screen.getByText(/REQ-TEST-1234/)).toBeInTheDocument();
    expect(screen.queryByText(/at ProblemChild/)).not.toBeInTheDocument();
  });

  it('allows user to retry via Try Again button after underlying issue is fixed', () => {
    render(<TestApp />);

    expect(screen.getByTestId('error-boundary')).toBeInTheDocument();

    // Fix the underlying state issue first, then click Try Again
    fireEvent.click(screen.getByText('Fix Error'));
    const resetBtn = screen.getByRole('button', { name: /Try Again/i });
    fireEvent.click(resetBtn);

    expect(screen.getByText('Normal Content')).toBeInTheDocument();
  });
});
