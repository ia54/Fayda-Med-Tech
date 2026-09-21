"use client";

import { Button } from "@/components/ui/button";
import {
  DropdownMenu,
  DropdownMenuCheckboxItem,
  DropdownMenuContent,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/antd-dropdown";
import { Columns3, Loader2, RefreshCw } from "lucide-react";
import { ColumnDef, ColumnVisibility, ToolbarConfig } from "./types";
import { cn } from "@/lib/utils";
import { useState } from "react";

interface TableToolbarProps<TData> {
  config: ToolbarConfig;
  columns?: ColumnDef<TData>[];
  columnVisibility?: ColumnVisibility;
  onColumnVisibilityChange?: (visibility: ColumnVisibility) => void;
  onRefresh?: () => void | Promise<void>;
  className?: string;
}

export function TableToolbar<TData>({
  config,
  columns = [],
  columnVisibility = {},
  onColumnVisibilityChange,
  onRefresh,
  className,
}: TableToolbarProps<TData>) {
  const {
    showColumnToggle = true,
    showRefresh = true,
    customActions,
  } = config;

  const [isRefreshing, setIsRefreshing] = useState(false);

  const handleRefresh = async () => {
    if (!onRefresh) return;

    setIsRefreshing(true);
    try {
      await onRefresh();
    } catch (error) {
      console.error("Error refreshing data:", error);
    } finally {
      setIsRefreshing(false);
    }
  };

  if (!showColumnToggle && !showRefresh && !customActions) {
    return null;
  }

  return (
    <div className={cn("flex items-center gap-2", className)}>
      {/* Custom Actions */}
      {customActions && <>{customActions}</>}

      {/* Refresh Button */}
      {showRefresh && onRefresh && (
        <Button
          variant="outline"
          size="sm"
          onClick={handleRefresh}
          disabled={isRefreshing}
          className="gap-2"
          aria-label="Refresh table data"
        >
          {isRefreshing ? (
            <Loader2 className="h-4 w-4 animate-spin" />
          ) : (
            <RefreshCw className="h-4 w-4" />
          )}
          {isRefreshing ? "Refreshing..." : "Refresh"}
        </Button>
      )}

      {/* Column Toggle */}
      {showColumnToggle && columns.length > 0 && onColumnVisibilityChange && (
        <ColumnToggle
          columns={columns}
          columnVisibility={columnVisibility}
          onColumnVisibilityChange={onColumnVisibilityChange}
        />
      )}
    </div>
  );
}

interface ColumnToggleProps<TData> {
  columns: ColumnDef<TData>[];
  columnVisibility: ColumnVisibility;
  onColumnVisibilityChange: (visibility: ColumnVisibility) => void;
}

function ColumnToggle<TData>({
  columns,
  columnVisibility,
  onColumnVisibilityChange,
}: ColumnToggleProps<TData>) {
  // Filter columns that can be hidden
  const toggleableColumns = columns.filter(
    (col) => col.enableHiding !== false
  );

  const handleToggle = (columnId: string, checked: boolean) => {
    onColumnVisibilityChange({
      ...columnVisibility,
      [columnId]: checked,
    });
  };

  const handleShowAll = () => {
    const allVisible: ColumnVisibility = {};
    toggleableColumns.forEach((col) => {
      allVisible[col.id] = true;
    });
    onColumnVisibilityChange({
      ...columnVisibility,
      ...allVisible,
    });
  };

  const handleHideAll = () => {
    const allHidden: ColumnVisibility = {};
    toggleableColumns.forEach((col) => {
      allHidden[col.id] = false;
    });
    onColumnVisibilityChange({
      ...columnVisibility,
      ...allHidden,
    });
  };

  const visibleCount = toggleableColumns.filter(
    (col) => columnVisibility[col.id] !== false
  ).length;

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button
          variant="outline"
          size="sm"
          className="gap-2"
          aria-label="Toggle columns"
        >
          <Columns3 className="h-4 w-4" />
          Columns
          {visibleCount < toggleableColumns.length && (
            <span className="ml-1 text-xs text-muted-foreground">
              ({visibleCount}/{toggleableColumns.length})
            </span>
          )}
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end" className="w-[200px]">
        <DropdownMenuLabel>Toggle columns</DropdownMenuLabel>
        <DropdownMenuSeparator />

        {/* Quick actions */}
        <div className="flex items-center justify-between px-2 py-1.5">
          <Button
            variant="ghost"
            size="sm"
            onClick={handleShowAll}
            className="h-7 text-xs"
          >
            Show all
          </Button>
          <Button
            variant="ghost"
            size="sm"
            onClick={handleHideAll}
            className="h-7 text-xs"
          >
            Hide all
          </Button>
        </div>

        <DropdownMenuSeparator />

        {/* Column checkboxes */}
        <div className="max-h-[300px] overflow-y-auto">
          {toggleableColumns.map((column) => {
            const label =
              typeof column.header === "string"
                ? column.header
                : column.id;
            const isVisible = columnVisibility[column.id] !== false;

            return (
              <DropdownMenuCheckboxItem
                key={column.id}
                checked={isVisible}
                onCheckedChange={(checked: boolean) => handleToggle(column.id, checked)}
                className="capitalize"
              >
                {label}
              </DropdownMenuCheckboxItem>
            );
          })}
        </div>
      </DropdownMenuContent>
    </DropdownMenu>
  );
}