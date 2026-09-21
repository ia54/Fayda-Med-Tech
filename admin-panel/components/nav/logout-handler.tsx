'use client';

import { useEffect } from 'react';
import { useRouter } from 'next/navigation';
import { useDispatch } from 'react-redux';
import { logout } from '@/store/slices/authSlice';
import { useLogoutMutation } from '@/store/api/authApiSlice';

export function LogoutHandler() {
  const dispatch = useDispatch();
  const router = useRouter();
  const [logoutApi] = useLogoutMutation();

  const handleLogout = async () => {
    try {
      // Call the logout API
      await logoutApi().unwrap();
      
      // Clear Redux state
      dispatch(logout());
      
      // Redirect to login page
      router.push('/auth/login');
    } catch (error) {
      console.error('Logout failed:', error);
      // Even if API fails, clear local state
      dispatch(logout());
      router.push('/auth/login');
    }
  };

  return (
    <button 
      onClick={handleLogout}
      className="w-full text-left px-2 py-1 text-sm hover:bg-accent hover:text-accent-foreground rounded-sm"
    >
      Log out
    </button>
  );
}