import { useSelector } from 'react-redux';
import { RootState } from '@/store/store';

export function useAuth() {
  const { user, token, expiresAt } = useSelector((state: RootState) => state.auth);

  const isAuthenticated = !!token && !!user;
  const isTokenExpired = expiresAt ? Date.now() > expiresAt : true;

  return {
    user,
    token,
    isAuthenticated,
    isTokenExpired,
  };
}