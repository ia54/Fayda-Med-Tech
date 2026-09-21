"use client";

import { cn } from "@/lib/utils";
import { Card } from "@/components/ui/card";
import { FilterSectionProps } from "./types";

export function FilterSection({ children, className }: FilterSectionProps) {
  if (!children) {
    return null;
  }

  return (
    <Card className={cn("p-4 mb-4 bg-card/80 backdrop-blur-sm hover:shadow-md transition-all duration-300 relative z-5", className)}>
      <div className="flex flex-col md:flex-row md:items-center gap-4">
        {children}
      </div>
    </Card>
  );
}

// Alternative: Inline filter section without card wrapper
export function InlineFilterSection({
  children,
  className,
}: FilterSectionProps) {
  if (!children) {
    return null;
  }

  return (
    <div className={cn("mb-4", className)}>
      <div className="flex flex-col md:flex-row md:items-center gap-4">
        {children}
      </div>
    </div>
  );
}