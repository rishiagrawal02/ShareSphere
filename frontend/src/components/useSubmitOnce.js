import { useState, useCallback } from 'react';

/**
 * Hook to prevent accidental double-submits and provide idempotency key generators.
 */
export function useSubmitOnce() {
  const [isSubmitting, setIsSubmitting] = useState(false);

  const handleSubmit = useCallback(
    (asyncCallback) => async (e) => {
      if (e?.preventDefault) {
        e.preventDefault();
      }

      if (isSubmitting) {
        return;
      }

      try {
        setIsSubmitting(true);
        return await asyncCallback(e);
      } finally {
        setIsSubmitting(false);
      }
    },
    [isSubmitting]
  );

  const generateIdempotencyKey = useCallback(() => {
    return 'idemp_' + Math.random().toString(36).substring(2, 11) + '_' + Date.now().toString(36);
  }, []);

  return { isSubmitting, handleSubmit, generateIdempotencyKey };
}
