"use client";

import { SearchFilter, DateRangeFilter } from "@/components/ui/filters";
import { FilterBarConfig } from "./types";

interface FilterBarProps {
  config: FilterBarConfig;
}

export function FilterBar({ config }: FilterBarProps) {
  const {
    searchValue,
    onSearchChange,
    searchPlaceholder,
    showDateRange,
    dateRangeValue,
    onDateRangeChange,
    dateRangePlaceholder,
    customFilters,
  } = config;

  return (
    <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
      {/* Left: Date Range, Search & Custom Filters */}
      <div className="flex-1 flex flex-wrap items-center gap-2">
        {/* Date Range Filter (First) */}
        {showDateRange && onDateRangeChange && (
          <div className="w-full sm:w-auto">
            <DateRangeFilter
              value={dateRangeValue}
              onChange={onDateRangeChange}
              placeholder={dateRangePlaceholder || "Select date range..."}
              size="sm"
              showPresets={true}
              clearable={true}
            />
          </div>
        )}

        {/* Search Filter (Second) */}
        {onSearchChange && (
          <div className="w-full sm:w-auto sm:min-w-[200px] sm:max-w-[300px]">
            <SearchFilter
              value={searchValue || ""}
              onChange={onSearchChange}
              placeholder={searchPlaceholder || "Search..."}
              size="sm"
            />
          </div>
        )}

        {/* Custom Filters */}
        {customFilters}
      </div>
    </div>
  );
}
