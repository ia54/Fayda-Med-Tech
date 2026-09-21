import { useCallback } from 'react';
import { useAppDispatch } from '../store/hooks';
import { openModal, closeModal } from '../store/slices/modalSlice';

export function useModal() {
  const dispatch = useAppDispatch();

  const openConfirmModal = useCallback((title: string, message: string, onConfirm?: () => void) => {
    dispatch(openModal({
      type: 'confirm',
      props: {
        title,
        message,
        onConfirm,
      },
    }));
  }, [dispatch]);

  const openCustomModal = useCallback((component: React.ComponentType<any>, props?: Record<string, any>) => {
    dispatch(openModal({
      type: 'custom',
      props: {
        component,
        ...props,
      },
    }));
  }, [dispatch]);

  const close = useCallback(() => {
    dispatch(closeModal());
  }, [dispatch]);

  return {
    openConfirmModal,
    openCustomModal,
    close,
  };
}