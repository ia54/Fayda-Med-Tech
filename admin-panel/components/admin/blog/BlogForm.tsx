"use client";

import { useState, useEffect } from "react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Upload, X, FileText, Loader2, Calendar, CheckCircle2 } from "lucide-react";
import { LoadingButton } from "@/components/ui/loading-button";
import type {
  Blog,
  CreateBlogData,
  UpdateBlogData,
} from "@/store/api/blogsApiSlice";
import { useGetBlogCategoriesQuery } from "@/store/api/blogCategoriesApiSlice";
import { Badge } from "@/components/ui/badge";

interface BlogFormProps {
  blog?: Blog | null;
  onSubmit: (data: CreateBlogData | UpdateBlogData) => Promise<void>;
  onCancel: () => void;
  isLoading?: boolean;
  apiErrors?: Record<string, string[]>;
}

export function BlogForm({
  blog,
  onSubmit,
  onCancel,
  isLoading = false,
  apiErrors,
}: BlogFormProps) {
  // Fetch blog categories
  const { data: categoriesResponse, isLoading: isLoadingCategories } =
    useGetBlogCategoriesQuery({
      page: 1,
      per_page: 100, // Get all categories
    });

  const categories = categoriesResponse?.data?.blog_categories || [];

  const [formData, setFormData] = useState({
    title: blog?.title || "",
    // url_slug: blog?.url_slug || "",
    author: blog?.author || "",
    category: blog?.category || "",
    excerpt: blog?.excerpt || "",
    content: blog?.content || "",
    tags: blog?.tags?.join(", ") || "",
    status: blog?.status || "draft",
    published_at: blog?.published_at ? blog.published_at.split("T")[0] : "",
  });

  const [errors, setErrors] = useState<Record<string, string>>({});
  const [blogImages, setBlogImages] = useState<File[]>([]);
  const [imagePreviews, setImagePreviews] = useState<string[]>(
    blog?.blog_images_urls || []
  );

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

  // Auto-generate slug from title
  // useEffect(() => {
  //   if (!blog && formData.title && !formData.url_slug) {
  //     const slug = formData.title
  //       .toLowerCase()
  //       .replace(/[^a-z0-9]+/g, "-")
  //       .replace(/(^-|-$)/g, "");
  //     setFormData((prev) => ({ ...prev, url_slug: slug }));
  //   }
  // }, [formData.title, blog, formData.url_slug]);

  const handleImageChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const files = Array.from(e.target.files || []);
    if (files.length === 0) return;

    const maxSize = 5 * 1024 * 1024; // 5MB
    const acceptedTypes = [
      "image/jpeg",
      "image/jpg",
      "image/png",
      "image/webp",
    ];

    const validFiles: File[] = [];

    for (const file of files) {
      if (file.size > maxSize) {
        setErrors((prev) => ({
          ...prev,
          blog_images: "Max file size is 5MB per image",
        }));
        continue;
      }

      if (!acceptedTypes.includes(file.type)) {
        setErrors((prev) => ({
          ...prev,
          blog_images: "Only .jpg, .jpeg, .png and .webp formats are supported",
        }));
        continue;
      }

      validFiles.push(file);
    }

    if (validFiles.length > 0) {
      setErrors((prev) => ({ ...prev, blog_images: "" }));
      setBlogImages((prev) => [...prev, ...validFiles]);
      
      // Create previews for new valid files
      validFiles.forEach(file => {
        const reader = new FileReader();
        reader.onloadend = () => {
          setImagePreviews((prev) => [...prev, reader.result as string]);
        };
        reader.readAsDataURL(file);
      });
    }
  };

  const removeImage = (index: number) => {
    setBlogImages((prev) => prev.filter((_, i) => i !== index));
    setImagePreviews((prev) => prev.filter((_, i) => i !== index));
  };

  const handleChange = (field: string, value: string) => {
    setFormData((prev) => ({ ...prev, [field]: value }));
    setErrors((prev) => ({ ...prev, [field]: "" }));
  };

  const validate = () => {
    const newErrors: Record<string, string> = {};

    if (!formData.title.trim()) newErrors.title = "Title is required";
    if (!formData.author.trim()) newErrors.author = "Author is required";
    if (!formData.category.trim()) newErrors.category = "Category is required";
    if (!formData.content.trim()) newErrors.content = "Content is required";
    if (formData.status === "schedule" && !formData.published_at) {
      newErrors.published_at = "Published date is required for scheduled posts";
    }

    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!validate()) return;

    const submitData: any = {
      title: formData.title,
      // url_slug: formData.url_slug,
      author: formData.author,
      category: formData.category,
      excerpt: formData.excerpt || undefined,
      content: formData.content,
      tags: formData.tags
        ? JSON.stringify(formData.tags.split(",").map((t) => t.trim()))
        : undefined,
      status: formData.status as "draft" | "published" | "schedule",
      published_at: formData.published_at || undefined,
      blog_images: blogImages.length > 0 ? blogImages : undefined,
    };

    if (blog) {
      // If any of the previews are full URLs, we are keeping some existing images
      const hasExistingImages = imagePreviews.some((p) => p.startsWith("http"));
      submitData.keep_existing_images = hasExistingImages;
    }

    await onSubmit(submitData);
  };

  return (
    <form onSubmit={handleSubmit} className="space-y-6">
      {/* Title */}
      <div className="space-y-2">
        <Label htmlFor="title">
          Title <span className="text-destructive">*</span>
        </Label>
        <Input
          id="title"
          value={formData.title}
          onChange={(e) => handleChange("title", e.target.value)}
          placeholder="Enter blog post title"
          disabled={isLoading}
        />
        {displayErrors.title && (
          <p className="text-sm text-destructive">{displayErrors.title}</p>
        )}
      </div>

      {/* URL Slug */}
      {/* <div className="space-y-2">
        <Label htmlFor="url_slug">URL Slug</Label>
        <Input
          id="url_slug"
          value={formData.url_slug}
          onChange={(e) => handleChange("url_slug", e.target.value)}
          placeholder="url-friendly-slug (auto-generated)"
          disabled={isLoading}
        />
        {displayErrors.url_slug && (
          <p className="text-sm text-destructive">{displayErrors.url_slug}</p>
        )}
      </div> */}

      {/* Author & Category */}
      <div className="grid grid-cols-2 gap-4">
        <div className="space-y-2">
          <Label htmlFor="author">
            Author <span className="text-destructive">*</span>
          </Label>
          <Input
            id="author"
            value={formData.author}
            onChange={(e) => handleChange("author", e.target.value)}
            placeholder="Author name"
            disabled={isLoading}
          />
          {displayErrors.author && (
            <p className="text-sm text-destructive">{displayErrors.author}</p>
          )}
        </div>

        <div className="space-y-2">
          <Label htmlFor="category">
            Category <span className="text-destructive">*</span>
          </Label>
          <Select
            value={formData.category}
            onValueChange={(value) => handleChange("category", value)}
            disabled={isLoading || isLoadingCategories}
          >
            <SelectTrigger>
              <SelectValue placeholder="Select category" />
            </SelectTrigger>
            <SelectContent>
              {categories.map((cat) => (
                <SelectItem key={cat.id} value={cat.category}>
                  {cat.category}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
          {displayErrors.category && (
            <p className="text-sm text-destructive">{displayErrors.category}</p>
          )}
        </div>
      </div>

      {/* Excerpt */}
      <div className="space-y-2">
        <Label htmlFor="excerpt">Meta Description</Label>
        <Textarea
          id="excerpt"
          value={formData.excerpt}
          onChange={(e) => handleChange("excerpt", e.target.value)}
          placeholder="Brief description of the post..."
          rows={3}
          disabled={isLoading}
        />
        {displayErrors.excerpt && (
          <p className="text-sm text-destructive">{displayErrors.excerpt}</p>
        )}
      </div>

      {/* Content */}
      <div className="space-y-2">
        <Label htmlFor="content">
          Content <span className="text-destructive">*</span>
        </Label>
        <Textarea
          id="content"
          value={formData.content}
          onChange={(e) => handleChange("content", e.target.value)}
          placeholder="Write your blog post content here..."
          rows={10}
          disabled={isLoading}
        />
        {displayErrors.content && (
          <p className="text-sm text-destructive">{displayErrors.content}</p>
        )}
      </div>

      {/* Tags */}
      <div className="space-y-2">
        <Label htmlFor="tags">Tags</Label>
        <Input
          id="tags"
          value={formData.tags}
          onChange={(e) => handleChange("tags", e.target.value)}
          placeholder="medical, billing, healthcare (comma separated)"
          disabled={isLoading}
        />
        {displayErrors.tags && (
          <p className="text-sm text-destructive">{displayErrors.tags}</p>
        )}
      </div>

      {/* Status & Published Date */}
      <div className="grid grid-cols-2 gap-4">
        <div className="space-y-2">
          <Label htmlFor="status">Status</Label>
          <Select
            value={formData.status}
            onValueChange={(value) => handleChange("status", value)}
            disabled={isLoading}
          >
            <SelectTrigger>
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="draft">Draft</SelectItem>
              <SelectItem value="published">Published</SelectItem>
              <SelectItem value="schedule">Scheduled</SelectItem>
            </SelectContent>
          </Select>
          {displayErrors.status && (
            <p className="text-sm text-destructive">{displayErrors.status}</p>
          )}
        </div>

        <div className="space-y-2">
          <Label htmlFor="published_at">
            Published Date{" "}
            {formData.status === "schedule" && (
              <span className="text-destructive">*</span>
            )}
          </Label>
          <Input
            id="published_at"
            type="datetime-local"
            value={formData.published_at}
            onChange={(e) => handleChange("published_at", e.target.value)}
            disabled={isLoading}
          />
          {displayErrors.published_at && (
            <p className="text-sm text-destructive">
              {displayErrors.published_at}
            </p>
          )}
        </div>
      </div>

      {/* Blog Images */}
      <div className="space-y-2">
        <Label htmlFor="blog_images">Blog Images</Label>
        <div className="flex items-center gap-4">
          <Button
            type="button"
            variant="outline"
            onClick={() => document.getElementById("blog_images")?.click()}
            disabled={isLoading}
          >
            <Upload className="h-4 w-4 mr-2" />
            Upload Images
          </Button>
          <input
            id="blog_images"
            type="file"
            accept="image/jpeg,image/jpg,image/png,image/webp"
            multiple
            onChange={handleImageChange}
            className="hidden"
          />
          <span className="text-sm text-muted-foreground">
            Max 5MB per image, formats: JPG, PNG, WEBP
          </span>
        </div>

        {imagePreviews.length > 0 && (
          <div className="grid grid-cols-3 gap-4 mt-4">
            {imagePreviews.map((preview, index) => (
              <div key={index} className="relative group">
                <img
                  src={preview}
                  alt={`Preview ${index + 1}`}
                  className="w-full h-32 object-cover rounded-md border shadow-sm group-hover:ring-2 ring-primary/50 transition-all"
                />
                <div className="absolute top-2 left-2 pointer-events-none">
                  {preview.startsWith("data:") ? (
                    <Badge className="bg-emerald-500 hover:bg-emerald-600 border-0 shadow-md animate-in fade-in zoom-in duration-300">
                      <CheckCircle2 className="h-3 w-3 mr-1" /> New
                    </Badge>
                  ) : (
                    <Badge
                      variant="secondary"
                      className="bg-background/90 backdrop-blur-sm shadow-md border-primary/10"
                    >
                      Stored
                    </Badge>
                  )}
                </div>
                <button
                  type="button"
                  onClick={() => removeImage(index)}
                  className="absolute top-2 right-2 bg-destructive text-white p-1 rounded-full opacity-0 group-hover:opacity-100 transition-opacity"
                  disabled={isLoading}
                >
                  <X className="h-4 w-4" />
                </button>
              </div>
            ))}
          </div>
        )}

        {displayErrors.blog_images && (
          <p className="text-sm text-destructive">
            {displayErrors.blog_images}
          </p>
        )}
      </div>

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
          {blog ? "Update Post" : "Create Post"}
        </LoadingButton>
      </div>
    </form>
  );
}
