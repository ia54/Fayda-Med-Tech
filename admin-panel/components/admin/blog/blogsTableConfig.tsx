"use client";

import { Eye, Edit, Trash2, Calendar, Tag, User, MoreHorizontal } from "lucide-react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/antd-dropdown";
import { ColumnDef } from "@/components/global/table";
import type { Blog } from "@/store/api/blogsApiSlice";
import dayjs from "dayjs";
import { useAppDispatch } from "@/store/hooks";
import { openModal } from "@/store/slices/modalSlice";

interface BlogActionsProps {
  blog: Blog;
  onView: (blog: Blog) => void;
  onEdit: (blog: Blog) => void;
  onDelete: (id: number) => void;
}

function BlogActions({ blog, onView, onEdit, onDelete }: BlogActionsProps) {
  const dispatch = useAppDispatch();

  const handleDelete = () => {
    dispatch(
      openModal({
        type: "confirm",
        props: {
          title: "Delete Blog Post",
          message: `Are you sure you want to delete "${blog.title}"? This action cannot be undone.`,
          onConfirm: () => onDelete(blog.id),
        },
      })
    );
  };

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button variant="ghost" className="h-8 w-8 p-0 cursor-pointer">
          <MoreHorizontal className="h-4 w-4" />
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end">
        <DropdownMenuItem onClick={() => onView(blog)}>
          <Eye className="mr-2 h-4 w-4" />
          View
        </DropdownMenuItem>
        <DropdownMenuItem onClick={() => onEdit(blog)}>
          <Edit className="mr-2 h-4 w-4" />
          Edit
        </DropdownMenuItem>
        <DropdownMenuSeparator />
        <DropdownMenuItem onClick={handleDelete} className="text-destructive">
          <Trash2 className="mr-2 h-4 w-4" />
          Delete
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  );
}

interface BlogColumnActions {
  onView: (blog: Blog) => void;
  onEdit: (blog: Blog) => void;
  onDelete: (id: number) => void;
}

export function getBlogColumns({
  onView,
  onEdit,
  onDelete,
}: BlogColumnActions): ColumnDef<Blog>[] {
  return [
    {
      id: "title",
      accessorKey: "title",
      header: "Title",
      enableSorting: true,
      cell: ({ row }) => (
        <div className="max-w-xs">
          <p className="font-semibold text-sm truncate">{row.original.title}</p>
          <p className="text-xs text-muted-foreground truncate">{row.original.excerpt}</p>
        </div>
      ),
    },
    {
      id: "author",
      accessorKey: "author",
      header: "Author",
      enableSorting: true,
      cell: ({ row }) => (
        <div className="flex items-center gap-2">
          <User className="h-4 w-4 text-muted-foreground" />
          <span className="text-sm">{row.original.author}</span>
        </div>
      ),
    },
    {
      id: "category",
      accessorKey: "category",
      header: "Category",
      enableSorting: true,
      cell: ({ row }) => (
        <Badge variant="outline" className="text-xs">
          <Tag className="h-3 w-3 mr-1" />
          {row.original.category}
        </Badge>
      ),
    },
    {
      id: "status",
      accessorKey: "status",
      header: "Status",
      enableSorting: true,
      cell: ({ row }) => {
        const statusConfig = {
          published: { variant: "default" as const, className: "bg-accent/20 text-accent" },
          draft: { variant: "secondary" as const, className: "" },
          schedule: { variant: "outline" as const, className: "border-chart-4 text-chart-4" },
        };

        const config = statusConfig[row.original.status] || statusConfig.draft;

        return (
          <Badge variant={config.variant} className={config.className}>
            {row.original.status.charAt(0).toUpperCase() + row.original.status.slice(1)}
          </Badge>
        );
      },
    },
    {
      id: "tags",
      accessorKey: "tags",
      header: "Tags",
      cell: ({ row }) => {
        const tags = row.original.tags || [];
        if (tags.length === 0) {
          return <span className="text-xs text-muted-foreground">No tags</span>;
        }
        return (
          <div className="flex flex-wrap gap-1">
            {tags.slice(0, 2).map((tag, index) => (
              <Badge key={index} variant="secondary" className="text-xs">
                {tag}
              </Badge>
            ))}
            {tags.length > 2 && (
              <Badge variant="secondary" className="text-xs">
                +{tags.length - 2}
              </Badge>
            )}
          </div>
        );
      },
    },
    {
      id: "published_at",
      accessorKey: "published_at",
      header: "Published Date",
      enableSorting: true,
      cell: ({ row }) => (
        <div className="flex items-center gap-2 text-sm">
          <Calendar className="h-4 w-4 text-muted-foreground" />
          {row.original.published_at
            ? dayjs(row.original.published_at).format("MMM DD, YYYY")
            : "Not published"}
        </div>
      ),
    },
    {
      id: "created_at",
      accessorKey: "created_at",
      header: "Created",
      enableSorting: true,
      cell: ({ row }) => (
        <span className="text-sm text-muted-foreground">
          {dayjs(row.original.created_at).format("MMM DD, YYYY")}
        </span>
      ),
    },
    {
      id: "actions",
      header: "Actions",
      cell: ({ row }) => (
        <BlogActions
          blog={row.original}
          onView={onView}
          onEdit={onEdit}
          onDelete={onDelete}
        />
      ),
    },
  ];
}
