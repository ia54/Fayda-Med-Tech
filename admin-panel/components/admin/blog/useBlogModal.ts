import { useState } from "react";
import type { Blog, CreateBlogData, UpdateBlogData } from "@/store/api/blogsApiSlice";

export type BlogModalMode = "create" | "edit" | "view";

export interface BlogModalState {
  open: boolean;
  mode: BlogModalMode;
  blog: Blog | null;
}

export function useBlogModal(
  onCreate: (data: CreateBlogData) => Promise<{ success: boolean; error?: any }>,
  onUpdate: (id: number, data: UpdateBlogData) => Promise<{ success: boolean; error?: any }>
) {
  const [modalState, setModalState] = useState<BlogModalState>({
    open: false,
    mode: "create",
    blog: null,
  });

  const [apiErrors, setApiErrors] = useState<any>(null);
  const [errorMessage, setErrorMessage] = useState<string>("");

  const openModal = (mode: BlogModalMode, blog: Blog | null = null) => {
    setModalState({ open: true, mode, blog });
    setApiErrors(null);
    setErrorMessage("");
  };

  const closeModal = () => {
    setModalState({ open: false, mode: "create", blog: null });
    setApiErrors(null);
    setErrorMessage("");
  };

  const handleSubmit = async (data: CreateBlogData | UpdateBlogData) => {
    setApiErrors(null);
    setErrorMessage("");

    let result;
    if (modalState.mode === "create") {
      result = await onCreate(data as CreateBlogData);
    } else if (modalState.mode === "edit" && modalState.blog) {
      result = await onUpdate(modalState.blog.id, data as UpdateBlogData);
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
