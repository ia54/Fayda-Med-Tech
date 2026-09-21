"use client";

import { Button } from "@/components/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/antd-dropdown";
import { Download, FileDown, Loader2 } from "lucide-react";
import { ExportConfig, TableAction } from "./types";
import { cn } from "@/lib/utils";

interface TableActionsProps {
  actions?: TableAction[];
  addNewComponent?: React.ReactNode;
  exportConfig?: ExportConfig;
  className?: string;
}

export function TableActions({
  actions = [],
  addNewComponent,
  exportConfig,
  className,
}: TableActionsProps) {
  const visibleActions = actions.filter((action) => !action.hidden);

  if (visibleActions.length === 0 && !addNewComponent && !exportConfig) {
    return null;
  }

  return (
    <div className={cn("flex items-center gap-2 flex-wrap w-full sm:w-auto", className)}>
      {/* Custom action buttons */}
      {visibleActions.map((action, index) => (
        <Button
          key={`action-${index}`}
          variant={action.variant || "outline"}
          size="sm"
          onClick={action.onClick}
          disabled={action.disabled}
          className="gap-2 text-xs sm:text-sm h-8 sm:h-9"
        >
          {action.icon}
          <span className="hidden sm:inline">{action.label}</span>
        </Button>
      ))}

      {/* Add New Component */}
      {addNewComponent && <>{addNewComponent}</>}

      {/* Export Button */}
      {exportConfig && <ExportButton exportConfig={exportConfig} />}
    </div>
  );
}

function ExportButton({ exportConfig }: { exportConfig: ExportConfig }) {
  const {
    onExport,
    formats = ["csv", "excel"],
    isExporting = false,
  } = exportConfig;

  const formatLabels: Record<string, string> = {
    csv: "Export as CSV",
    excel: "Export as Excel",
    pdf: "Export as PDF",
    json: "Export as JSON",
  };

  const formatIcons: Record<string, React.ReactNode> = {
    csv: <FileDown className="h-4 w-4" />,
    excel: <FileDown className="h-4 w-4" />,
    pdf: <FileDown className="h-4 w-4" />,
    json: <FileDown className="h-4 w-4" />,
  };

  if (formats.length === 1) {
    // Single format - direct button
    return (
      <Button
        variant="outline"
        size="sm"
        onClick={() => onExport(formats[0])}
        disabled={isExporting}
        className="gap-2"
      >
        {isExporting ? (
          <Loader2 className="h-4 w-4 animate-spin" />
        ) : (
          <Download className="h-4 w-4" />
        )}
        {isExporting ? "Exporting..." : `Export ${formats[0].toUpperCase()}`}
      </Button>
    );
  }

  // Multiple formats - dropdown
  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button
          variant="outline"
          size="sm"
          disabled={isExporting}
          className="gap-2"
        >
          {isExporting ? (
            <Loader2 className="h-4 w-4 animate-spin" />
          ) : (
            <Download className="h-4 w-4" />
          )}
          {isExporting ? "Exporting..." : "Export"}
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="start">
        {formats.map((format) => (
          <DropdownMenuItem
            key={format}
            onClick={() => onExport(format)}
            className="gap-2"
          >
            {formatIcons[format]}
            {formatLabels[format]}
          </DropdownMenuItem>
        ))}
      </DropdownMenuContent>
    </DropdownMenu>
  );
}

// Bulk Actions Component (shown when rows are selected)
interface BulkActionsProps {
  selectedCount: number;
  actions: TableAction[];
  onClearSelection: () => void;
  className?: string;
}

export function BulkActions({
  selectedCount,
  actions,
  onClearSelection,
  className,
}: BulkActionsProps) {
  if (selectedCount === 0) {
    return null;
  }

  return (
    <div
      className={cn(
        "flex items-center gap-2 p-2 bg-muted rounded-md border",
        className
      )}
    >
      <span className="text-sm font-medium">
        {selectedCount} row{selectedCount > 1 ? "s" : ""} selected
      </span>
      <div className="flex items-center gap-2 ml-4">
        {actions.map((action, index) => (
          <Button
            key={`bulk-action-${index}`}
            variant={action.variant || "outline"}
            size="sm"
            onClick={action.onClick}
            disabled={action.disabled}
            className="gap-2"
          >
            {action.icon}
            {action.label}
          </Button>
        ))}
        <Button
          variant="ghost"
          size="sm"
          onClick={onClearSelection}
          className="ml-2"
        >
          Clear
        </Button>
      </div>
    </div>
  );
}