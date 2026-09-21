"use client";

import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { CategoryForm } from "./CategoryForm";
import type {
  BlogCategory,
  CreateBlogCategoryData,
  UpdateBlogCategoryData,
} from "@/store/api/blogCategoriesApiSlice";
import type { CategoryModalMode } from "./useCategoryModal";

interface CategoryModalProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  mode: CategoryModalMode;
  category: BlogCategory | null;
  onSubmit: (
    data: CreateBlogCategoryData | UpdateBlogCategoryData
  ) => Promise<void>;
  isLoading?: boolean;
  apiErrors?: Record<string, string[]>;
  errorMessage?: string;
}

export function CategoryModal({
  open,
  onOpenChange,
  mode,
  category,
  onSubmit,
  isLoading = false,
  apiErrors,
  errorMessage,
}: CategoryModalProps) {
  const getTitle = () => {
    switch (mode) {
      case "create":
        return "Create New Category";
      case "edit":
        return "Edit Category";
      default:
        return "Category";
    }
  };

  const getDescription = () => {
    switch (mode) {
      case "create":
        return "Add a new blog category";
      case "edit":
        return "Update category information";
      default:
        return "";
    }
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent
        onPointerDownOutside={(e) => e.preventDefault()} // prevent closing on outside click
      >
        <DialogHeader>
          <DialogTitle>{getTitle()}</DialogTitle>
          <DialogDescription>{getDescription()}</DialogDescription>
        </DialogHeader>

        <div className="max-h-[calc(90vh-120px)] overflow-y-auto">
          {errorMessage && (
            <div className="mb-4 p-3 bg-destructive/10 border border-destructive/20 rounded-md text-sm text-destructive">
              {errorMessage}
            </div>
          )}

          <CategoryForm
            category={category}
            onSubmit={onSubmit}
            onCancel={() => onOpenChange(false)}
            isLoading={isLoading}
            apiErrors={apiErrors}
          />
        </div>
      </DialogContent>
    </Dialog>
  );
}
