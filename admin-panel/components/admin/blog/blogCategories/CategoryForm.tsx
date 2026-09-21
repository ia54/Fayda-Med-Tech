"use client";

import { useState } from "react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { LoadingButton } from "@/components/ui/loading-button";
import type {
  BlogCategory,
  CreateBlogCategoryData,
  UpdateBlogCategoryData,
} from "@/store/api/blogCategoriesApiSlice";

interface CategoryFormProps {
  category?: BlogCategory | null;
  onSubmit: (
    data: CreateBlogCategoryData | UpdateBlogCategoryData
  ) => Promise<void>;
  onCancel: () => void;
  isLoading?: boolean;
  apiErrors?: Record<string, string[]>;
}

export function CategoryForm({
  category,
  onSubmit,
  onCancel,
  isLoading = false,
  apiErrors,
}: CategoryFormProps) {
  const [formData, setFormData] = useState({
    category: category?.category || "",
    description: category?.description || "",
    // post: category?.post || 0,
  });

  const [errors, setErrors] = useState<Record<string, string>>({});

  // Merge API errors with local errors
  const displayErrors = {
    ...errors,
    ...(apiErrors
      ? Object.entries(apiErrors).reduce((acc, [key, messages]) => {
          acc[key] = messages[0];
          return acc;
        }, {} as Record<string, string>)
      : {}),
  };

  const handleChange = (field: string, value: string | number) => {
    setFormData((prev) => ({ ...prev, [field]: value }));
    setErrors((prev) => ({ ...prev, [field]: "" }));
  };

  const validate = () => {
    const newErrors: Record<string, string> = {};

    if (!formData.category.trim()) {
      newErrors.category = "Category name is required";
    }

    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!validate()) return;

    const submitData: any = {
      category: formData.category,
      description: formData.description || undefined,
      // post: formData.post || 0,
    };

    await onSubmit(submitData);
  };

  return (
    <form onSubmit={handleSubmit} className="space-y-6">
      {/* Category Name */}
      <div className="space-y-2">
        <Label htmlFor="category">
          Category Name <span className="text-destructive">*</span>
        </Label>
        <Input
          id="category"
          value={formData.category}
          onChange={(e) => handleChange("category", e.target.value)}
          placeholder="Enter category name"
          disabled={isLoading}
        />
        {displayErrors.category && (
          <p className="text-sm text-destructive">{displayErrors.category}</p>
        )}
      </div>

      {/* Description */}
      <div className="space-y-2">
        <Label htmlFor="description">Description</Label>
        <Textarea
          id="description"
          value={formData.description}
          onChange={(e) => handleChange("description", e.target.value)}
          placeholder="Brief description of the category..."
          rows={3}
          disabled={isLoading}
        />
        {displayErrors.description && (
          <p className="text-sm text-destructive">
            {displayErrors.description}
          </p>
        )}
      </div>

      {/* Post Count (Read-only for edit mode) */}
      {/* {category && (
        <div className="space-y-2">
          <Label htmlFor="post">Number of Posts</Label>
          <Input
            id="post"
            type="number"
            value={formData.post}
            disabled={true}
            readOnly
            className="bg-muted"
          />
          <p className="text-xs text-muted-foreground">
            This field is automatically updated based on blog posts using this
            category
          </p>
        </div>
      )} */}

      {/* Form Actions */}
      <div className="flex justify-end gap-3 pt-4">
        <Button
          type="button"
          variant="outline"
          onClick={onCancel}
          disabled={isLoading}
        >
          Cancel
        </Button>
        <LoadingButton type="submit" loading={isLoading}>
          {category ? "Update Category" : "Create Category"}
        </LoadingButton>
      </div>
    </form>
  );
}
