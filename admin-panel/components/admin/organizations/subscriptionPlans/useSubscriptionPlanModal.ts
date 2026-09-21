import { useState, useCallback } from "react";
import type { SubscriptionPlan } from "@/store/api/subscriptionPlansApiSlice";

export type SubscriptionPlanModalMode = "create" | "edit" | "view";

interface ModalState {
  open: boolean;
  mode: SubscriptionPlanModalMode;
  subscriptionPlan: SubscriptionPlan | null;
}

export interface SubscriptionPlanFormData {
  plan_name: string;
  price: number;
  users: number;
  features: string[];
  organizations: number;
  status: "active" | "inactive";
}

export function useSubscriptionPlanModal(
  onCreate: (data: SubscriptionPlanFormData) => Promise<any>,
  onUpdate: (id: number, data: Partial<SubscriptionPlanFormData>) => Promise<any>
) {
  const [modalState, setModalState] = useState<ModalState>({
    open: false,
    mode: "create",
    subscriptionPlan: null,
  });

  const [apiErrors, setApiErrors] = useState<Record<string, string[]>>({});
  const [errorMessage, setErrorMessage] = useState<string>("");

  const openModal = useCallback(
    (mode: SubscriptionPlanModalMode, subscriptionPlan?: SubscriptionPlan) => {
      setModalState({
        open: true,
        mode,
        subscriptionPlan: subscriptionPlan || null,
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
      subscriptionPlan: null,
    });
    setApiErrors({});
    setErrorMessage("");
  }, []);

  const handleSubmit = useCallback(
    async (data: SubscriptionPlanFormData) => {
      setApiErrors({});
      setErrorMessage("");

      try {
        if (modalState.mode === "create") {
          await onCreate(data);
        } else if (modalState.mode === "edit" && modalState.subscriptionPlan) {
          await onUpdate(modalState.subscriptionPlan.id, data);
        }
        closeModal();
      } catch (error: any) {
        console.error("Subscription plan modal error:", error);

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
