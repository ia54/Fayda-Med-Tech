"use client";

import React from "react";
import { Search, X } from "lucide-react";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";

export interface SearchFilterProps {
  value?: string;
  onChange: (value: string) => void;
  placeholder?: string;
  className?: string;
  size?: "sm" | "md" | "lg";
  showClearButton?: boolean;
  debounceMs?: number;
  disabled?: boolean;
}

export const SearchFilter: React.FC<SearchFilterProps> = ({
  value = "",
  onChange,
  placeholder = "Search...",
  className,
  size = "md",
  showClearButton = true,
  debounceMs = 300,
  disabled = false,
}) => {
  const [internalValue, setInternalValue] = React.useState(value || "");
  const debounceRef = React.useRef<NodeJS.Timeout>();

  // Error boundary for invalid props
  if (!onChange || typeof onChange !== 'function') {
    console.error('SearchFilter: onChange prop is required and must be a function');
  }

  React.useEffect(() => {
    setInternalValue(value || "");
  }, [value]);

  const handleChange = React.useCallback((newValue: string) => {
    try {
      setInternalValue(newValue || "");
      
      if (debounceRef.current) {
        clearTimeout(debounceRef.current);
      }
      
      debounceRef.current = setTimeout(() => {
        onChange(newValue || "");
      }, Math.max(0, debounceMs || 300));
    } catch (error) {
      console.error('SearchFilter: Error in handleChange:', error);
    }
  }, [onChange, debounceMs]);

  const handleClear = React.useCallback(() => {
    try {
      setInternalValue("");
      onChange("");
      if (debounceRef.current) {
        clearTimeout(debounceRef.current);
      }
    } catch (error) {
      console.error('SearchFilter: Error in handleClear:', error);
    }
  }, [onChange]);

  React.useEffect(() => {
    return () => {
      if (debounceRef.current) {
        clearTimeout(debounceRef.current);
      }
    };
  }, []);

  const sizeClasses = {
    sm: "h-9 text-sm",
    md: "h-10 text-sm",
    lg: "h-11 text-base",
  };

  const iconSizeClasses = {
    sm: "h-4 w-4",
    md: "h-4 w-4",
    lg: "h-5 w-5",
  };

  return (
    <div className={cn("relative w-full", className)}>
      <Search
        className={cn(
          "absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground pointer-events-none",
          iconSizeClasses[size]
        )}
      />
      <Input
        value={internalValue}
        onChange={(e) => handleChange(e.target.value)}
        placeholder={placeholder}
        disabled={disabled}
        className={cn(
          "w-full pl-10",
          sizeClasses[size],
          showClearButton && internalValue && "pr-10"
        )}
      />
      {showClearButton && internalValue && (
        <Button
          type="button"
          variant="ghost"
          size="icon"
          onClick={handleClear}
          className="absolute right-1 top-1/2 -translate-y-1/2 h-7 w-7"
          disabled={disabled}
        >
          <X className="h-4 w-4" />
        </Button>
      )}
    </div>
  );
};