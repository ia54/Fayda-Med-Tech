import { useState } from 'react';
import type { User } from '@/store/api/usersApiSlice';
import type { UserRole } from '@/lib/validations/user';

export interface UseUserFiltersReturn {
  // Filter state
  searchTerm: string;
  setSearchTerm: (term: string) => void;
  roleFilter: UserRole | 'all';
  setRoleFilter: (role: UserRole | 'all') => void;
  statusFilter: 'all' | 'active' | 'inactive';
  setStatusFilter: (status: 'all' | 'active' | 'inactive') => void;


  // Reset
  resetFilters: () => void;
}

export function useUserFilters(): UseUserFiltersReturn {
  const [searchTerm, setSearchTerm] = useState('');
  const [roleFilter, setRoleFilter] = useState<UserRole | 'all'>('all');
  const [statusFilter, setStatusFilter] = useState<'all' | 'active' | 'inactive'>('all');


  const resetFilters = () => {
    setSearchTerm('');
    setRoleFilter('all');
    setStatusFilter('all');
  };

  return {
    searchTerm,
    setSearchTerm,
    roleFilter,
    setRoleFilter,
    statusFilter,
    setStatusFilter,
    resetFilters,
  };
}
