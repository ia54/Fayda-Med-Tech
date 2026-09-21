import { useState } from "react";
import type { BlogCategory, CreateBlogCategoryData, UpdateBlogCategoryData } from "@/store/api/blogCategoriesApiSlice";

export type CategoryModalMode = "create" | "edit";

export interface CategoryModalState {
  open: boolean;
  mode: CategoryModalMode;
  category: BlogCategory | null;
}

export function useCategoryModal(
  onCreate: (data: CreateBlogCategoryData) => Promise<{ success: boolean; error?: any }>,
  onUpdate: (id: number, data: UpdateBlogCategoryData) => Promise<{ success: boolean; error?: any }>
) {
  const [modalState, setModalState] = useState<CategoryModalState>({
    open: false,
    mode: "create",
    category: null,
  });

  const [apiErrors, setApiErrors] = useState<any>(null);
  const [errorMessage, setErrorMessage] = useState<string>("");

  const openModal = (mode: CategoryModalMode, category: BlogCategory | null = null) => {
    setModalState({ open: true, mode, category });
    setApiErrors(null);
    setErrorMessage("");
  };

  const closeModal = () => {
    setModalState({ open: false, mode: "create", category: null });
    setApiErrors(null);
    setErrorMessage("");
  };

  const handleSubmit = async (data: CreateBlogCategoryData | UpdateBlogCategoryData) => {
    setApiErrors(null);
    setErrorMessage("");

    let result;
    if (modalState.mode === "create") {
      result = await onCreate(data as CreateBlogCategoryData);
    } else if (modalState.mode === "edit" && modalState.category) {
      result = await onUpdate(modalState.category.id, data as UpdateBlogCategoryData);
    }

    if (result?.success) {
      closeModal();
    } else if (result?.error) {
      setApiErrors(result.error);
    }
  };

  return {
    modalState,
    apiErrors,
    errorMessage,
    openModal,
    closeModal,
    handleSubmit,
  };
}
