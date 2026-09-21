"use client";

import React from "react";
import { Check, ChevronDown, Search, X } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from "@/components/ui/popover";
import { Badge } from "@/components/ui/badge";
import { cn } from "@/lib/utils";

export interface DropdownOption {
  value: string;
  label: string;
  disabled?: boolean;
}

export interface DropdownFilterProps {
  options: DropdownOption[];
  value?: string | string[];
  onChange: (value: string | string[]) => void;
  placeholder?: string;
  className?: string;
  size?: "sm" | "md" | "lg";
  multiple?: boolean;
  searchable?: boolean;
  searchPlaceholder?: string;
  disabled?: boolean;
  maxSelectedDisplay?: number;
  clearable?: boolean;
  loading?: boolean;
}

export const DropdownFilter: React.FC<DropdownFilterProps> = ({
  options = [],
  value,
  onChange,
  placeholder = "Select option...",
  className,
  size = "md",
  multiple = false,
  searchable = false,
  searchPlaceholder = "Search options...",
  disabled = false,
  maxSelectedDisplay = 2,
  clearable = true,
  loading = false,
}) => {
  const [open, setOpen] = React.useState(false);
  const [searchValue, setSearchValue] = React.useState("");

  // Error boundary for invalid props
  if (!onChange || typeof onChange !== "function") {
    console.error(
      "DropdownFilter: onChange prop is required and must be a function",
    );
  }

  const selectedValues = React.useMemo(() => {
    if (multiple) {
      return Array.isArray(value) ? value : [];
    }
    return value ? [value] : [];
  }, [value, multiple]);

  const filteredOptions = React.useMemo(() => {
    if (!Array.isArray(options)) return [];
    if (!searchable || !searchValue) return options;
    return options.filter((option) => {
      try {
        return (
          option?.label?.toLowerCase?.().includes(searchValue.toLowerCase()) ||
          false
        );
      } catch {
        return false;
      }
    });
  }, [options, searchValue, searchable]);

  const handleSelect = (optionValue: string) => {
    try {
      if (multiple) {
        const currentValues = Array.isArray(value) ? value : [];
        const newValues = currentValues.includes(optionValue)
          ? currentValues.filter((v) => v !== optionValue)
          : [...currentValues, optionValue];
        onChange(newValues);
      } else {
        onChange(optionValue);
        setOpen(false);
      }
    } catch (error) {
      console.error("DropdownFilter: Error in handleSelect:", error);
    }
  };

  const handleSelectAll = () => {
    if (!multiple) return;
    const currentValues = Array.isArray(value) ? value : [];
    const availableValues = filteredOptions
      .filter((opt) => !opt.disabled)
      .map((opt) => opt.value);
    const allSelected = availableValues.every((val) =>
      currentValues.includes(val),
    );

    if (allSelected) {
      // Deselect all filtered options
      const newValues = currentValues.filter(
        (val) => !availableValues.includes(val),
      );
      onChange(newValues);
    } else {
      // Select all filtered options
      const newValues = [...new Set([...currentValues, ...availableValues])];
      onChange(newValues);
    }
  };

  const isAllSelected = React.useMemo(() => {
    if (!multiple || filteredOptions.length === 0) return false;
    const availableValues = filteredOptions
      .filter((opt) => !opt.disabled)
      .map((opt) => opt.value);
    const currentValues = Array.isArray(value) ? value : [];
    return (
      availableValues.length > 0 &&
      availableValues.every((val) => currentValues.includes(val))
    );
  }, [multiple, filteredOptions, value]);

  const isIndeterminate = React.useMemo(() => {
    if (!multiple || filteredOptions.length === 0) return false;
    const availableValues = filteredOptions
      .filter((opt) => !opt.disabled)
      .map((opt) => opt.value);
    const currentValues = Array.isArray(value) ? value : [];
    const selectedCount = availableValues.filter((val) =>
      currentValues.includes(val),
    ).length;
    return selectedCount > 0 && selectedCount < availableValues.length;
  }, [multiple, filteredOptions, value]);

  const handleClear = (e: React.MouseEvent) => {
    e.stopPropagation();
    onChange(multiple ? [] : "");
  };

  const getDisplayText = () => {
    try {
      if (!selectedValues || selectedValues.length === 0) return placeholder;

      if (!multiple) {
        const option = Array.isArray(options)
          ? options.find((opt) => opt?.value === selectedValues[0])
          : null;
        return option?.label || selectedValues[0] || placeholder;
      }

      if (selectedValues.length <= maxSelectedDisplay) {
        return selectedValues
          .map((val) => {
            const option = Array.isArray(options)
              ? options.find((opt) => opt?.value === val)
              : null;
            return option?.label || val || "Unknown";
          })
          .join(", ");
      }

      return `${selectedValues.length} selected`;
    } catch {
      return placeholder;
    }
  };

  return (
    <div className={cn("filter-dropdown-container", className)}>
      <Popover open={open} onOpenChange={setOpen}>
        <PopoverTrigger asChild>
          <Button
            variant="outline"
            role="combobox"
            aria-expanded={open}
            disabled={disabled}
            className={cn(
              "filter-dropdown-trigger",
              size === "sm" && "filter-dropdown-trigger--sm",
              size === "lg" && "filter-dropdown-trigger--lg",
              selectedValues.length > 0 && "filter-dropdown-trigger--selected",
            )}
          >
            <span className="filter-dropdown-text">{getDisplayText()}</span>
            <div className="filter-dropdown-actions">
              {clearable && selectedValues.length > 0 && (
                <X
                  className="filter-dropdown-clear-icon"
                  onClick={handleClear}
                />
              )}
              <ChevronDown className="filter-dropdown-chevron" />
            </div>
          </Button>
        </PopoverTrigger>
        <PopoverContent
          className="filter-dropdown-content"
          align="start"
          style={{ zIndex: 9999 }}
        >
          {searchable && (
            <div className="relative p-2 border-b">
              <Search className="absolute left-4 top-1/2 transform -translate-y-1/2 h-4 w-4 text-muted-foreground pointer-events-none" />
              <Input
                placeholder={searchPlaceholder}
                value={searchValue}
                onChange={(e) => setSearchValue(e.target.value)}
                className="pl-10 h-9 w-full"
              />
            </div>
          )}
          <div className="filter-dropdown-options">
            {loading ? (
              <div className="filter-dropdown-empty">Loading...</div>
            ) : filteredOptions.length === 0 ? (
              <div className="filter-dropdown-empty">No options found</div>
            ) : (
              <>
                {multiple &&
                  filteredOptions.filter((opt) => !opt.disabled).length > 1 && (
                    <div
                      className="filter-dropdown-select-all"
                      onClick={handleSelectAll}
                    >
                      <div className="filter-dropdown-select-all-checkbox">
                        <input
                          type="checkbox"
                          checked={isAllSelected}
                          ref={(el) => {
                            if (el) el.indeterminate = isIndeterminate;
                          }}
                          onChange={() => {}} // Handled by onClick
                          className="filter-dropdown-checkbox"
                        />
                      </div>
                      <span className="filter-dropdown-select-all-label">
                        Select All (
                        {filteredOptions.filter((opt) => !opt.disabled).length})
                      </span>
                    </div>
                  )}
                {filteredOptions.map((option) => (
                  <div
                    key={option.value}
                    className={cn(
                      "filter-dropdown-option",
                      selectedValues.includes(option.value) &&
                        "filter-dropdown-option--selected",
                      option.disabled && "filter-dropdown-option--disabled",
                    )}
                    onClick={() =>
                      !option.disabled && handleSelect(option.value)
                    }
                  >
                    {multiple && (
                      <div className="filter-dropdown-option-checkbox">
                        <input
                          type="checkbox"
                          checked={selectedValues.includes(option.value)}
                          onChange={() => {}} // Handled by onClick
                          className="filter-dropdown-checkbox"
                          disabled={option.disabled}
                        />
                      </div>
                    )}
                    <span className="filter-dropdown-option-label">
                      {option.label}
                    </span>
                    {!multiple && selectedValues.includes(option.value) && (
                      <Check className="filter-dropdown-option-check" />
                    )}
                  </div>
                ))}
              </>
            )}
          </div>
          {multiple && selectedValues.length > 0 && (
            <div className="filter-dropdown-footer">
              <div className="filter-dropdown-selected-badges">
                {selectedValues.slice(0, 3).map((val) => {
                  const option = options.find((opt) => opt.value === val);
                  return (
                    <Badge
                      key={val as string}
                      variant="secondary"
                      className="filter-dropdown-badge"
                    >
                      {option?.label || String(val)}
                      <X
                        className="filter-dropdown-badge-remove"
                        onClick={(e) => {
                          e.stopPropagation();
                          handleSelect(String(val));
                        }}
                      />
                    </Badge>
                  );
                })}
                {selectedValues.length > 3 && (
                  <span className="filter-dropdown-more">
                    +{selectedValues.length - 3} more
                  </span>
                )}
              </div>
            </div>
          )}
        </PopoverContent>
      </Popover>
    </div>
  );
};
