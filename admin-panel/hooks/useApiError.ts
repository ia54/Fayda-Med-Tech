import { useState, useCallback } from 'react';

interface ApiError {
  message: string;
  code?: string;
  status?: number;
}

export function useApiError() {
  const [error, setError] = useState<ApiError | null>(null);
  const [isLoading, setIsLoading] = useState(false);

  const handleError = useCallback((err: any) => {
    setIsLoading(false);
    
    if (err.name === 'AbortError') {
      setError({ message: 'Request timeout', code: 'TIMEOUT' });
      return;
    }
    
    if (err.status) {
      // Handle HTTP errors
      switch (err.status) {
        case 401:
          setError({ message: 'Unauthorized access', code: 'UNAUTHORIZED', status: 401 });
          break;
        case 403:
          setError({ message: 'Forbidden access', code: 'FORBIDDEN', status: 403 });
          break;
        case 404:
          setError({ message: 'Resource not found', code: 'NOT_FOUND', status: 404 });
          break;
        case 500:
          setError({ message: 'Internal server error', code: 'SERVER_ERROR', status: 500 });
          break;
        default:
          setError({ 
            message: err.data?.message || 'An unexpected error occurred', 
            code: 'UNKNOWN',
            status: err.status
          });
      }
    } else {
      // Handle network or other errors
      setError({ 
        message: err.message || 'Network error - please check your connection', 
        code: 'NETWORK_ERROR' 
      });
    }
  }, []);

  const clearError = useCallback(() => {
    setError(null);
  }, []);

  return {
    error,
    isLoading,
    setIsLoading,
    handleError,
    clearError,
  };
}