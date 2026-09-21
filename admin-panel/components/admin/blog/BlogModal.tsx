"use client";

import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { BlogForm } from "./BlogForm";
import type {
  Blog,
  CreateBlogData,
  UpdateBlogData,
} from "@/store/api/blogsApiSlice";
import type { BlogModalMode } from "./useBlogModal";
import { Badge } from "@/components/ui/badge";
import dayjs from "dayjs";
import { Calendar, User, Tag, Eye } from "lucide-react";

interface BlogModalProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  mode: BlogModalMode;
  blog: Blog | null;
  onSubmit: (data: CreateBlogData | UpdateBlogData) => Promise<void>;
  isLoading?: boolean;
  apiErrors?: Record<string, string[]>;
  errorMessage?: string;
}

export function BlogModal({
  open,
  onOpenChange,
  mode,
  blog,
  onSubmit,
  isLoading = false,
  apiErrors,
  errorMessage,
}: BlogModalProps) {
  const isViewMode = mode === "view";

  const getTitle = () => {
    switch (mode) {
      case "create":
        return "Create New Blog Post";
      case "edit":
        return "Edit Blog Post";
      case "view":
        return "View Blog Post";
      default:
        return "Blog Post";
    }
  };

  const getDescription = () => {
    switch (mode) {
      case "create":
        return "Add a new blog post to your website";
      case "edit":
        return "Update blog post information";
      case "view":
        return "View blog post details";
      default:
        return "";
    }
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent
        onPointerDownOutside={(e) => e.preventDefault()} // prevent closing on outside click
        className={` !w-full sm:!w-[500px] md:!w-[600px] lg:!w-[700px] xl:!w-[800px] 2xl:!w-[900px] `}
      >
        <DialogHeader className="px-6 pt-6">
          <DialogTitle>{getTitle()}</DialogTitle>
          <DialogDescription>{getDescription()}</DialogDescription>
        </DialogHeader>

        <div className="max-h-[calc(95vh-120px)] overflow-y-auto px-6 pb-6">
          {errorMessage && (
            <div className="mb-4 p-3 bg-destructive/10 border border-destructive/20 rounded-md text-sm text-destructive">
              {errorMessage}
            </div>
          )}

          {isViewMode && blog ? (
            <div className="space-y-6 py-4">
              {/* Title */}
              <div>
                <h3 className="text-2xl font-bold mb-2">{blog.title}</h3>
                <p className="text-sm text-muted-foreground">
                  Slug: {blog.url_slug}
                </p>
              </div>

              {/* Metadata */}
              <div className="flex flex-wrap gap-4 text-sm">
                <div className="flex items-center gap-2">
                  <User className="h-4 w-4 text-muted-foreground" />
                  <span>{blog.author}</span>
                </div>
                <div className="flex items-center gap-2">
                  <Tag className="h-4 w-4 text-muted-foreground" />
                  <Badge variant="outline">{blog.category}</Badge>
                </div>
                <div className="flex items-center gap-2">
                  <Calendar className="h-4 w-4 text-muted-foreground" />
                  <span>
                    {blog.published_at
                      ? dayjs(blog.published_at).format("MMM DD, YYYY")
                      : "Not published"}
                  </span>
                </div>
              </div>

              {/* Status */}
              <div>
                <Badge
                  variant={
                    blog.status === "published"
                      ? "default"
                      : blog.status === "draft"
                      ? "secondary"
                      : "outline"
                  }
                  className={
                    blog.status === "published"
                      ? "bg-accent/20 text-accent"
                      : ""
                  }
                >
                  {blog.status.charAt(0).toUpperCase() + blog.status.slice(1)}
                </Badge>
              </div>

              {/* Tags */}
              {blog.tags && blog.tags.length > 0 && (
                <div className="space-y-2">
                  <h4 className="text-sm font-semibold">Tags</h4>
                  <div className="flex flex-wrap gap-2">
                    {blog.tags.map((tag, index) => (
                      <Badge key={index} variant="secondary">
                        {tag}
                      </Badge>
                    ))}
                  </div>
                </div>
              )}

              {/* Excerpt */}
              {blog.excerpt && (
                <div className="space-y-2">
                  <h4 className="text-sm font-semibold">Meta Description</h4>
                  <p className="text-sm text-muted-foreground">
                    {blog.excerpt}
                  </p>
                </div>
              )}

              {/* Content */}
              <div className="space-y-2">
                <h4 className="text-sm font-semibold">Content</h4>
                <div className="prose prose-sm max-w-none bg-muted/30 p-4 rounded-md">
                  <p className="whitespace-pre-wrap">{blog.content}</p>
                </div>
              </div>

              {/* Images */}
              {blog.blog_images && blog.blog_images.length > 0 && (
                <div className="space-y-2">
                  <h4 className="text-sm font-semibold">Images</h4>
                  <div className="grid grid-cols-2 gap-4">
                    {blog.blog_images.map((image, index) => (
                      <img
                        key={index}
                        src={blog.blog_images_urls?.[index] || image}
                        alt={`Blog image ${index + 1}`}
                        className="w-full h-48 object-cover rounded-md border"
                      />
                    ))}
                  </div>
                </div>
              )}

              {/* Timestamps */}
              <div className="pt-4 border-t text-xs text-muted-foreground space-y-1">
                <p>
                  Created: {dayjs(blog.created_at).format("MMM DD, YYYY HH:mm")}
                </p>
                <p>
                  Updated: {dayjs(blog.updated_at).format("MMM DD, YYYY HH:mm")}
                </p>
              </div>
            </div>
          ) : (
            <BlogForm
              blog={blog}
              onSubmit={onSubmit}
              onCancel={() => onOpenChange(false)}
              isLoading={isLoading}
              apiErrors={apiErrors}
            />
          )}
        </div>
      </DialogContent>
    </Dialog>
  );
}
