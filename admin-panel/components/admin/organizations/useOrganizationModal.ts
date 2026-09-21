import { useState, useCallback } from "react";
import { Organization } from "@/store/api/organizationsApiSlice";
import type { OrganizationFormValues } from "@/lib/validations/organization";

type ModalMode = "create" | "edit" | "view";

interface ModalState {
  open: boolean;
  mode: ModalMode;
  organization?: Organization;
}

export function useOrganizationModal(
  onCreate: (data: OrganizationFormValues) => Promise<void>,
  onUpdate: (data: OrganizationFormValues & { id: number }) => Promise<void>
) {
  const [modalState, setModalState] = useState<ModalState>({
    open: false,
    mode: "create",
  });
  const [apiErrors, setApiErrors] = useState<Record<string, string[]>>();
  const [errorMessage, setErrorMessage] = useState<string>();

  const openModal = useCallback((mode: ModalMode, organization?: Organization) => {
    setModalState({ open: true, mode, organization });
    setApiErrors(undefined);
    setErrorMessage(undefined);
  }, []);

  const closeModal = useCallback(() => {
    setModalState((prev) => ({ ...prev, open: false }));
    setApiErrors(undefined);
    setErrorMessage(undefined);
  }, []);

  const handleSubmit = useCallback(
    async (data: OrganizationFormValues) => {
      try {
        setApiErrors(undefined);
        setErrorMessage(undefined);
        if (modalState.mode === "create") {
          await onCreate(data);
        } else if (modalState.mode === "edit" && modalState.organization) {
          await onUpdate({ ...data, id: modalState.organization.id });
        }
        closeModal();
      } catch (error: any) {
        if (error?.data?.errors) {
          setApiErrors(error.data.errors);
        }
        if (error?.data?.message) {
          setErrorMessage(error.data.message);
        }
        throw error;
      }
    },
    [modalState.mode, modalState.organization, onCreate, onUpdate, closeModal]
  );

  return {
    modalState,
    apiErrors,
    errorMessage,
    openModal,
    closeModal,
    handleSubmit,
  };
}
