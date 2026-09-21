"use client";

import { Button } from "@/components/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/antd-dropdown";
import { Download } from "lucide-react";
import { PageHeaderConfig } from "./types";

interface PageHeaderProps {
  config: PageHeaderConfig;
}

export function PageHeader({ config }: PageHeaderProps) {
  const { title, description, actions, exportConfig } = config;

  return (
    <div className="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
      {/* Left: Title & Description */}
      <div>
        <h1 className="text-2xl md:text-3xl font-bold tracking-tight text-emerald-900 dark:text-white">
          {title}
        </h1>
        {description && (
          <p className="text-sm md:text-base text-emerald-600 dark:text-slate-300 mt-1">
            {description}
          </p>
        )}
      </div>

      {/* Right: Actions & Export */}
      <div className="flex items-center gap-2 shrink-0">
        {/* Custom Actions */}
        {actions?.map((action, idx) => {
          if (action.hidden) return null;
          return (
            <Button
              key={action.label + idx}
              onClick={action.onClick}
              variant={action.variant || "default"}
              size="sm"
              disabled={action.disabled}
              className="gap-2 h-9 cursor-pointer"
            >
              {action.icon}
              <span className="hidden sm:inline">{action.label}</span>
              <span className="sm:hidden">{action.label.split(" ")[0]}</span>
            </Button>
          );
        })}

        {/* Export Dropdown */}
        {exportConfig && (
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <Button
                size="sm"
                variant="outline"
                className="gap-2 h-9 cursor-pointer"
                disabled={exportConfig.isExporting}
              >
                <Download className="h-4 w-4" />
                <span className="hidden sm:inline">Export</span>
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
              {(exportConfig.formats || ["csv", "excel", "json"]).map(
                (format) => (
                  <DropdownMenuItem
                    key={format}
                    onClick={() => exportConfig.onExport(format)}
                    className="cursor-pointer"
                  >
                    Export as {format.toUpperCase()}
                  </DropdownMenuItem>
                )
              )}
            </DropdownMenuContent>
          </DropdownMenu>
        )}
      </div>
    </div>
  );
}
