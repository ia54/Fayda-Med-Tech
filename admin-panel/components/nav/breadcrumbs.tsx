import { ChevronRight } from "lucide-react";
import Link from "next/link";
import { useBreadcrumbs } from "@/hooks/useBreadcrumbs";

export function Breadcrumbs() {
  const breadcrumbs = useBreadcrumbs();

  if (breadcrumbs.length <= 1) {
    return null;
  }

  return (
    <div className="flex items-center text-sm text-muted-foreground">
      {breadcrumbs.map((item, index) => (
        <div key={item.label} className="flex items-center">
          {index > 0 && (
            <ChevronRight className="mx-2 h-4 w-4" />
          )}
          {item.href ? (
            <Link
              href={item.href}
              className="hover:text-foreground transition-colors"
              title={item.fullLabel || item.label}
            >
              {item.label}
            </Link>
          ) : (
            <span
              className="font-medium text-foreground"
              title={item.fullLabel || item.label}
            >
              {item.label}
            </span>
          )}
        </div>
      ))}
    </div>
  );
}