"use client";

import { Edit, Trash2, FileText, MoreHorizontal } from "lucide-react";
import { Button } from "@/components/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/antd-dropdown";
import { ColumnDef } from "@/components/global/table";
import type { BlogCategory } from "@/store/api/blogCategoriesApiSlice";
import dayjs from "dayjs";
import { useAppDispatch } from "@/store/hooks";
import { openModal } from "@/store/slices/modalSlice";

interface CategoryActionsProps {
  category: BlogCategory;
  onEdit: (category: BlogCategory) => void;
  onDelete: (id: number) => void;
}

function CategoryActions({ category, onEdit, onDelete }: CategoryActionsProps) {
  const dispatch = useAppDispatch();

  const handleDelete = () => {
    if (category.post > 0) {
      dispatch(
        openModal({
          type: "confirm",
          props: {
            title: "Cannot Delete Category",
            message: `Category "${category.category}" is being used by ${category.post} blog post(s). Please reassign or delete those posts before deleting this category.`,
            onConfirm: () => {},
            confirmText: "OK",
            showCancel: false,
          },
        })
      );
      return;
    }

    dispatch(
      openModal({
        type: "confirm",
        props: {
          title: "Delete Category",
          message: `Are you sure you want to delete "${category.category}"? This action cannot be undone.`,
          onConfirm: () => onDelete(category.id),
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
        <DropdownMenuItem onClick={() => onEdit(category)}>
          <Edit className="mr-2 h-4 w-4" />
          Edit
        </DropdownMenuItem>
        <DropdownMenuSeparator />
        <DropdownMenuItem
          onClick={handleDelete}
          className="text-destructive"
          disabled={category.post > 0}
        >
          <Trash2 className="mr-2 h-4 w-4" />
          Delete
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  );
}

interface CategoryColumnActions {
  onEdit: (category: BlogCategory) => void;
  onDelete: (id: number) => void;
}

export function getCategoryColumns({
  onEdit,
  onDelete,
}: CategoryColumnActions): ColumnDef<BlogCategory>[] {
  return [
    {
      id: "category",
      accessorKey: "category",
      header: "Category",
      enableSorting: true,
      cell: ({ row }) => (
        <div className="font-semibold text-sm">{row.original.category}</div>
      ),
    },
    {
      id: "description",
      accessorKey: "description",
      header: "Description",
      cell: ({ row }) => (
        <p className="text-sm text-muted-foreground">
          {row.original.description || "No description"}
        </p>
      ),
    },
    // {
    //   id: "post",
    //   accessorKey: "post",
    //   header: "Posts",
    //   enableSorting: true,
    //   cell: ({ row }) => (
    //     <div className="flex items-center gap-2 text-sm">
    //       <FileText className="h-4 w-4 text-muted-foreground" />
    //       <span className="font-medium">{row.original.post}</span>
    //     </div>
    //   ),
    // },
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
        <CategoryActions
          category={row.original}
          onEdit={onEdit}
          onDelete={onDelete}
        />
      ),
    },
  ];
}
