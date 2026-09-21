import { toast } from 'sonner';

export function useNotifications() {
  const showSuccess = (message: string, description?: string) => {
    toast.success(message, {
      description,
    });
  };

  const showError = (message: string, description?: string) => {
    toast.error(message, {
      description,
    });
  };

  const showInfo = (message: string, description?: string) => {
    toast.info(message, {
      description,
    });
  };

  const showWarning = (message: string, description?: string) => {
    toast.warning(message, {
      description,
    });
  };

  const dismiss = (id?: string | number) => {
    if (id) {
      toast.dismiss(id);
    } else {
      toast.dismiss();
    }
  };

  return {
    showSuccess,
    showError,
    showInfo,
    showWarning,
    dismiss,
  };
}