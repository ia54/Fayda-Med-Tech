"use client";

import { Button } from "@/components/ui/button";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/custom-select";
import {
  ChevronLeft,
  ChevronRight,
  ChevronsLeft,
  ChevronsRight,
} from "lucide-react";
import { PaginationConfig, PaginationHandlers } from "./types";
import { cn } from "@/lib/utils";

interface PaginationProps {
  pagination: PaginationConfig;
  handlers: PaginationHandlers;
  className?: string;
}

export function Pagination({
  pagination,
  handlers,
  className,
}: PaginationProps) {
  const { page, pageSize, total, pageSizeOptions = [10, 20, 30, 50, 100] } =
    pagination;
  const { onPageChange, onPageSizeChange } = handlers;

  // Calculate pagination info
  const totalPages = Math.ceil(total / pageSize);
  const startIndex = (page - 1) * pageSize + 1;
  const endIndex = Math.min(page * pageSize, total);

  // Generate page numbers to display
  const getPageNumbers = () => {
    const delta = 2;
    const range: number[] = [];
    const rangeWithDots: (number | string)[] = [];

    for (
      let i = Math.max(2, page - delta);
      i <= Math.min(totalPages - 1, page + delta);
      i++
    ) {
      range.push(i);
    }

    if (page - delta > 2) {
      rangeWithDots.push(1, "...");
    } else {
      rangeWithDots.push(1);
    }

    rangeWithDots.push(...range);

    if (page + delta < totalPages - 1) {
      rangeWithDots.push("...", totalPages);
    } else if (totalPages > 1) {
      rangeWithDots.push(totalPages);
    }

    return rangeWithDots;
  };

  const pageNumbers = totalPages > 0 ? getPageNumbers() : [];

  return (
    <div
      className={cn(
        "flex flex-col lg:flex-row items-center justify-between gap-3 md:gap-4 px-3 md:px-4 py-3 md:py-4",
        className
      )}
    >
      {/* Left: Items info and page size selector */}
      <div className="flex flex-col sm:flex-row items-center gap-2 md:gap-4 w-full lg:w-auto">
        <div className="text-xs sm:text-sm text-emerald-600 dark:text-slate-300 text-center sm:text-left">
          Showing{" "}
          <span className="font-medium text-emerald-900 dark:text-white">
            {total > 0 ? startIndex : 0}
          </span>{" "}
          to <span className="font-medium text-emerald-900 dark:text-white">{endIndex}</span> of{" "}
          <span className="font-medium text-emerald-900 dark:text-white">{total}</span> results
        </div>
 
        <div className="flex items-center gap-2">
          <span className="text-xs sm:text-sm text-emerald-600 dark:text-slate-300 whitespace-nowrap">
            Rows per page:
          </span>
          <Select
            value={pageSize.toString()}
            onValueChange={(value) => {
              onPageSizeChange(Number(value));
              // Reset to page 1 when changing page size
              if (page !== 1) {
                onPageChange(1);
              }
            }}
          >
            <SelectTrigger className="h-8 w-[60px] sm:w-[70px] text-xs sm:text-sm border-emerald-100 dark:border-emerald-900/50">
              <SelectValue placeholder={pageSize.toString()} />
            </SelectTrigger>
            <SelectContent>
              {pageSizeOptions.map((size) => (
                <SelectItem key={size} value={size.toString()}>
                  {size}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
      </div>

      {/* Right: Pagination controls */}
      <div className="flex items-center gap-1 sm:gap-2 w-full lg:w-auto justify-center lg:justify-end">
        {/* First page - Hidden on mobile */}
        <Button
          variant="outline"
          size="sm"
          onClick={() => onPageChange(1)}
          disabled={page === 1 || totalPages === 0}
          aria-label="Go to first page"
          className="hidden sm:inline-flex h-8 w-8 p-0 border-emerald-100 dark:border-emerald-900/50"
        >
          <ChevronsLeft className="h-4 w-4" />
        </Button>

        {/* Previous page */}
        <Button
          variant="outline"
          size="sm"
          onClick={() => onPageChange(page - 1)}
          disabled={page === 1 || totalPages === 0}
          aria-label="Go to previous page"
          className="h-8 w-8 p-0 border-emerald-100 dark:border-emerald-900/50"
        >
          <ChevronLeft className="h-4 w-4" />
        </Button>

        {/* Page numbers - Responsive */}
        <div className="flex items-center gap-1">
          {pageNumbers.map((pageNum, idx) => {
            if (pageNum === "...") {
              return (
                <span
                  key={`ellipsis-${idx}`}
                  className="px-1 sm:px-2 text-emerald-600 dark:text-slate-400 text-xs sm:text-sm hidden sm:inline"
                >
                  ...
                </span>
              );
            }

            // On mobile, only show current page and adjacent pages
            const isMobileVisible = Math.abs((pageNum as number) - page) <= 1;

            return (
              <Button
                key={pageNum}
                variant={page === pageNum ? "default" : "outline"}
                size="sm"
                onClick={() => onPageChange(pageNum as number)}
                className={cn(
                  "min-w-[32px] sm:min-w-[36px] h-8 p-0 text-xs sm:text-sm border-emerald-100 dark:border-emerald-900/50",
                  page === pageNum ? "bg-emerald-600 dark:bg-emerald-500 text-white" : "text-emerald-700 dark:text-white hover:bg-emerald-50 dark:hover:bg-emerald-900/50",
                  !isMobileVisible && "hidden sm:inline-flex"
                )}
                aria-label={`Go to page ${pageNum}`}
                aria-current={page === pageNum ? "page" : undefined}
              >
                {pageNum}
              </Button>
            );
          })}
        </div>

        {/* Next page */}
        <Button
          variant="outline"
          size="sm"
          onClick={() => onPageChange(page + 1)}
          disabled={page === totalPages || totalPages === 0}
          aria-label="Go to next page"
          className="h-8 w-8 p-0 border-emerald-100 dark:border-emerald-900/50"
        >
          <ChevronRight className="h-4 w-4" />
        </Button>

        {/* Last page - Hidden on mobile */}
        <Button
          variant="outline"
          size="sm"
          onClick={() => onPageChange(totalPages)}
          disabled={page === totalPages || totalPages === 0}
          aria-label="Go to last page"
          className="hidden sm:inline-flex h-8 w-8 p-0 border-emerald-100 dark:border-emerald-900/50"
        >
          <ChevronsRight className="h-4 w-4" />
        </Button>
      </div>
    </div>
  );
}

// Simple pagination variant (without page numbers)
export function SimplePagination({
  pagination,
  handlers,
  className,
}: PaginationProps) {
  const { page, pageSize, total } = pagination;
  const { onPageChange } = handlers;

  const totalPages = Math.ceil(total / pageSize);
  const startIndex = (page - 1) * pageSize + 1;
  const endIndex = Math.min(page * pageSize, total);

  return (
    <div
      className={cn(
        "flex items-center justify-between px-2 py-4",
        className
      )}
    >
      <div className="text-sm text-muted-foreground">
        Showing{" "}
        <span className="font-medium text-foreground">
          {total > 0 ? startIndex : 0}
        </span>{" "}
        to <span className="font-medium text-foreground">{endIndex}</span> of{" "}
        <span className="font-medium text-foreground">{total}</span> results
      </div>

      <div className="flex items-center gap-2">
        <span className="text-sm text-muted-foreground">
          Page {page} of {totalPages}
        </span>
        <Button
          variant="outline"
          size="sm"
          onClick={() => onPageChange(page - 1)}
          disabled={page === 1 || totalPages === 0}
        >
          Previous
        </Button>
        <Button
          variant="outline"
          size="sm"
          onClick={() => onPageChange(page + 1)}
          disabled={page === totalPages || totalPages === 0}
        >
          Next
        </Button>
      </div>
    </div>
  );
}