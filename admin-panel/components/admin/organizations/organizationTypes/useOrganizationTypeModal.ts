import { useState, useCallback } from "react";
import type { OrganizationType } from "@/store/api/organizationTypesApiSlice";

export type OrganizationTypeModalMode = "create" | "edit" | "view";

interface ModalState {
  open: boolean;
  mode: OrganizationTypeModalMode;
  organizationType: OrganizationType | null;
}

export function useOrganizationTypeModal(
  onCreate: (data: { type_name: string; description: string }) => Promise<any>,
  onUpdate: (id: number, data: { type_name?: string; description?: string }) => Promise<any>
) {
  const [modalState, setModalState] = useState<ModalState>({
    open: false,
    mode: "create",
    organizationType: null,
  });

  const [apiErrors, setApiErrors] = useState<Record<string, string[]>>({});
  const [errorMessage, setErrorMessage] = useState<string>("");

  const openModal = useCallback(
    (mode: OrganizationTypeModalMode, organizationType?: OrganizationType) => {
      setModalState({
        open: true,
        mode,
        organizationType: organizationType || null,
      });
      setApiErrors({});
      setErrorMessage("");
    },
    []
  );

  const closeModal = useCallback(() => {
    setModalState({
      open: false,
      mode: "create",
      organizationType: null,
    });
    setApiErrors({});
    setErrorMessage("");
  }, []);

  const handleSubmit = useCallback(
    async (data: { type_name: string; description: string }) => {
      setApiErrors({});
      setErrorMessage("");

      try {
        if (modalState.mode === "create") {
          await onCreate(data);
        } else if (modalState.mode === "edit" && modalState.organizationType) {
          await onUpdate(modalState.organizationType?.id, data);
        }
        closeModal();
      } catch (error: any) {
        console.error("Organization type modal error:", error);

        if (error?.data?.errors) {
          setApiErrors(error.data.errors);
        }

        if (error?.data?.message) {
          setErrorMessage(error.data.message);
        } else if (error?.message) {
          setErrorMessage(error.message);
        }
      }
    },
    [modalState, onCreate, onUpdate, closeModal]
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
