import { useState } from 'react';
import type { User, UserFormData } from '@/store/api/usersApiSlice';

export type ModalMode = 'create' | 'edit' | 'view';

export interface UseUserModalReturn {
  // Modal state
  isOpen: boolean;
  mode: ModalMode;
  selectedUser: User | null;

  // Actions
  openModal: (mode: ModalMode, user?: User) => void;
  closeModal: () => void;

  // Form submission
  handleSubmit: (userData: UserFormData) => Promise<void>;

  // Error handling
  apiErrors: Record<string, string[]> | null;
  setApiErrors: (errors: Record<string, string[]> | null) => void;
}

export function useUserModal(
  onCreate: (userData: UserFormData) => Promise<void>,
  onUpdate: (id: number, userData: Partial<UserFormData>) => Promise<void>
): UseUserModalReturn {
  const [isOpen, setIsOpen] = useState(false);
  const [mode, setMode] = useState<ModalMode>('create');
  const [selectedUser, setSelectedUser] = useState<User | null>(null);
  const [apiErrors, setApiErrors] = useState<Record<string, string[]> | null>(null);

  const openModal = (modalMode: ModalMode, user?: User) => {
    setMode(modalMode);
    setSelectedUser(user || null);
    setApiErrors(null);
    setIsOpen(true);
  };

  const closeModal = () => {
    setIsOpen(false);
    setMode('create');
    setSelectedUser(null);
    setApiErrors(null);
  };

  const handleSubmit = async (userData: UserFormData) => {
    try {
      setApiErrors(null);

      if (mode === 'create') {
        await onCreate(userData);
      } else if (mode === 'edit' && selectedUser) {
        await onUpdate(selectedUser.id, userData);
      }

      closeModal();
    } catch (error: any) {
      // Capture API validation errors
      if (error?.data?.errors) {
        setApiErrors(error.data.errors);
      }
      throw error;
    }
  };

  return {
    isOpen,
    mode,
    selectedUser,
    openModal,
    closeModal,
    handleSubmit,
    apiErrors,
    setApiErrors,
  };
}
