"use client";

import React from "react";
import { cn } from "@/lib/utils";

export interface FilterContainerProps {
  children: React.ReactNode;
  className?: string;
  orientation?: "horizontal" | "vertical";
  spacing?: "sm" | "md" | "lg";
  wrap?: boolean;
  align?: "start" | "center" | "end";
  justify?: "start" | "center" | "end" | "between";
}

export const FilterContainer: React.FC<FilterContainerProps> = ({
  children,
  className,
  orientation = "horizontal",
  spacing = "md",
  wrap = true,
  align = "center",
  justify = "start",
}) => {
  // Handle empty or invalid children
  if (!children) {
    return null;
  }

  try {
    return (
      <div
        className={cn(
          "filter-container flex",
          orientation === "horizontal" ? "flex-row" : "flex-col",
          spacing === "sm" && "gap-2",
          spacing === "md" && "gap-3",
          spacing === "lg" && "gap-4",
          wrap && "flex-wrap",
          align === "start" && "items-start",
          align === "center" && "items-center",
          align === "end" && "items-end",
          justify === "start" && "justify-start",
          justify === "center" && "justify-center",
          justify === "end" && "justify-end",
          justify === "between" && "justify-between",
          className
        )}
      >
        {children}
      </div>
    );
  } catch (error) {
    console.error('FilterContainer: Error rendering component:', error);
    return (
      <div className="filter-container-error">
        <span>Filter container error</span>
      </div>
    );
  }
};