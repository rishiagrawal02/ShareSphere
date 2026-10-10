import React, { useState, useRef, useEffect, useCallback } from 'react';
import { AlertCircle, Lock, Clock } from 'lucide-react';

/**
 * 6-digit numeric OTP input component for secure physical handover verification.
 * Adheres to Spec §15.1 and WCAG 2.1 AA accessibility guidelines.
 *
 * Security guidelines:
 * - OTP is never logged, never stored in localStorage, and cleared on unmount.
 * - Paste events are sanitized without console/debug logging.
 */
export function OtpInput({
  length = 6,
  value = '',
  onChange,
  onComplete,
  disabled = false,
  error = null,
  attemptsRemaining = null,
  isLocked = false,
  isExpired = false,
  autoFocus = true,
}) {
  const [digits, setDigits] = useState(() => {
    const arr = Array(length).fill('');
    if (value) {
      for (let i = 0; i < Math.min(value.length, length); i++) {
        arr[i] = value[i];
      }
    }
    return arr;
  });

  const inputRefs = useRef([]);

  // Clear digits from memory on unmount (Spec §15.1)
  useEffect(() => {
    return () => {
      setDigits(Array(length).fill(''));
    };
  }, [length]);

  // Sync if value prop changes
  useEffect(() => {
    if (value !== undefined) {
      const arr = Array(length).fill('');
      for (let i = 0; i < Math.min(value.length, length); i++) {
        arr[i] = value[i];
      }
      setDigits(arr);
    }
  }, [value, length]);

  // Autofocus the first empty box
  useEffect(() => {
    if (autoFocus && !disabled && !isLocked && !isExpired) {
      const firstEmptyIndex = digits.findIndex((d) => !d);
      const targetIndex = firstEmptyIndex === -1 ? 0 : firstEmptyIndex;
      inputRefs.current[targetIndex]?.focus();
    }
  }, []); // eslint-disable-line react-hooks/exhaustive-deps

  const triggerChange = useCallback(
    (newDigits) => {
      setDigits(newDigits);
      const fullCode = newDigits.join('');
      if (onChange) {
        onChange(fullCode);
      }
      if (fullCode.length === length && !newDigits.includes('')) {
        if (onComplete) {
          onComplete(fullCode);
        }
      }
    },
    [length, onChange, onComplete]
  );

  const handleKeyDown = (index, e) => {
    if (disabled || isLocked || isExpired) return;

    if (e.key === 'Backspace') {
      e.preventDefault();
      const newDigits = [...digits];
      if (newDigits[index]) {
        newDigits[index] = '';
        triggerChange(newDigits);
      } else if (index > 0) {
        newDigits[index - 1] = '';
        triggerChange(newDigits);
        inputRefs.current[index - 1]?.focus();
      }
    } else if (e.key === 'Delete') {
      e.preventDefault();
      const newDigits = [...digits];
      newDigits[index] = '';
      triggerChange(newDigits);
    } else if (e.key === 'ArrowLeft') {
      e.preventDefault();
      if (index > 0) {
        inputRefs.current[index - 1]?.focus();
      }
    } else if (e.key === 'ArrowRight') {
      e.preventDefault();
      if (index < length - 1) {
        inputRefs.current[index + 1]?.focus();
      }
    }
  };

  const handleChange = (index, e) => {
    if (disabled || isLocked || isExpired) return;

    const val = e.target.value;
    // Extract only digits
    const cleanDigits = val.replace(/\D/g, '');

    if (!cleanDigits) {
      const newDigits = [...digits];
      newDigits[index] = '';
      triggerChange(newDigits);
      return;
    }

    // Single digit input
    const char = cleanDigits.slice(-1);
    const newDigits = [...digits];
    newDigits[index] = char;
    triggerChange(newDigits);

    // Advance focus
    if (index < length - 1) {
      inputRefs.current[index + 1]?.focus();
    }
  };

  const handlePaste = (e) => {
    if (disabled || isLocked || isExpired) return;
    e.preventDefault();

    // Spec §15.1 / T-15.1-09: Normalise pasted content with spaces/hyphens (e.g. "12 34 56")
    const pastedText = e.clipboardData ? e.clipboardData.getData('text') : '';
    const cleanDigits = pastedText.replace(/\D/g, '').slice(0, length);

    if (!cleanDigits) return;

    const newDigits = [...digits];
    for (let i = 0; i < cleanDigits.length; i++) {
      newDigits[i] = cleanDigits[i];
    }
    triggerChange(newDigits);

    // Focus last filled or next empty
    const nextIndex = Math.min(cleanDigits.length, length - 1);
    inputRefs.current[nextIndex]?.focus();
  };

  const isDisabled = disabled || isLocked || isExpired;

  return (
    <div className="space-y-3" role="group" aria-label="One-time verification code">
      {/* 6 Digit Inputs */}
      <div className="flex items-center justify-center gap-2 sm:gap-3">
        {Array.from({ length }).map((_, index) => {
          const hasVal = Boolean(digits[index]);
          return (
            <input
              key={index}
              ref={(el) => (inputRefs.current[index] = el)}
              type="text"
              inputMode="numeric"
              pattern="[0-9]*"
              autoComplete="one-time-code"
              maxLength={1}
              value={digits[index]}
              disabled={isDisabled}
              onChange={(e) => handleChange(index, e)}
              onKeyDown={(e) => handleKeyDown(index, e)}
              onPaste={handlePaste}
              aria-label={`Digit ${index + 1} of ${length}`}
              aria-invalid={Boolean(error)}
              aria-describedby={error ? 'otp-error-msg' : undefined}
              className={`w-11 h-14 sm:w-13 sm:h-16 text-center text-2xl font-bold font-mono rounded-xl border transition-all outline-none ${
                isDisabled
                  ? 'bg-slate-900/40 border-slate-800 text-slate-500 cursor-not-allowed'
                  : error
                  ? 'bg-red-500/10 border-red-500/60 text-red-300 focus:border-red-400 focus:ring-2 focus:ring-red-500/20'
                  : hasVal
                  ? 'bg-emerald-500/10 border-emerald-500/60 text-emerald-300 focus:border-emerald-400 focus:ring-2 focus:ring-emerald-500/20'
                  : 'bg-slate-900 border-slate-700 text-white hover:border-slate-600 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20'
              }`}
            />
          );
        })}
      </div>

      {/* Lockout Banner */}
      {isLocked && (
        <div
          role="alert"
          className="flex items-center gap-2.5 p-3 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-300 text-xs"
        >
          <Lock size={15} className="shrink-0" />
          <span>
            Pickup code is locked due to too many failed attempts. The NGO pickup representative
            must reissue a new code from their dashboard.
          </span>
        </div>
      )}

      {/* Expired Banner */}
      {isExpired && !isLocked && (
        <div
          role="alert"
          className="flex items-center gap-2.5 p-3 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-300 text-xs"
        >
          <Clock size={15} className="shrink-0" />
          <span>
            Pickup code has expired. Please ask the NGO pickup representative to request a new
            code.
          </span>
        </div>
      )}

      {/* Error Message & Attempts Remaining */}
      {error && !isLocked && !isExpired && (
        <div
          id="otp-error-msg"
          role="alert"
          className="flex items-start gap-2 p-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-300 text-xs"
        >
          <AlertCircle size={15} className="shrink-0 mt-0.5" />
          <div className="space-y-0.5">
            <p className="font-medium">{error}</p>
            {attemptsRemaining !== null && (
              <p className="text-[11px] text-red-400/80">
                {attemptsRemaining} attempt{attemptsRemaining === 1 ? '' : 's'} remaining before
                lockout.
              </p>
            )}
          </div>
        </div>
      )}
    </div>
  );
}
