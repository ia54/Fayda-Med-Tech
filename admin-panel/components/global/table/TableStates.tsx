"use client";

import { Component, ErrorInfo, ReactNode } from "react";
import { Card, CardContent } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import {
  TableBody,
  TableCell,
  TableRow,
} from "@/components/ui/table";
import { AlertCircle, FileX2, Inbox, RefreshCw } from "lucide-react";
import { EmptyStateConfig, ErrorStateConfig, LoadingConfig } from "./types";
import { cn } from "@/lib/utils";

// Loading Skeleton for Table
interface TableLoadingProps {
  config: LoadingConfig;
  columnCount: number;
}

export function TableLoading({ config, columnCount }: TableLoadingProps) {
  const { rowCount = 5, message } = config;

  return (
    <>
      {message && (
        <TableRow>
          <TableCell
            colSpan={columnCount}
            className="h-12 text-center text-sm text-muted-foreground"
          >
            {message}
          </TableCell>
        </TableRow>
      )}
      {Array.from({ length: rowCount }).map((_, index) => (
        <TableRow key={`skeleton-${index}`}>
          {Array.from({ length: columnCount }).map((_, colIndex) => (
            <TableCell key={`skeleton-cell-${index}-${colIndex}`}>
              <Skeleton className="h-5 w-full" />
            </TableCell>
          ))}
        </TableRow>
      ))}
    </>
  );
}

// Empty State Component
interface EmptyStateProps {
  config?: EmptyStateConfig;
  columnCount: number;
}

export function EmptyState({ config, columnCount }: EmptyStateProps) {
  const {
    icon = <Inbox className="h-12 w-12 text-muted-foreground/50" />,
    title = "No data available",
    description = "There are no records to display at this time.",
    action,
  } = config || {};

  return (
    <TableRow>
      <TableCell colSpan={columnCount} className="h-[400px]">
        <div className="flex flex-col items-center justify-center space-y-4">
          {icon}
          <div className="space-y-2 text-center">
            <h3 className="text-lg font-semibold">{title}</h3>
            <p className="text-sm text-muted-foreground max-w-sm">
              {description}
            </p>
          </div>
          {action && (
            <Button onClick={action.onClick} variant="outline">
              {action.label}
            </Button>
          )}
        </div>
      </TableCell>
    </TableRow>
  );
}

// Error State Component
interface ErrorStateProps {
  config: ErrorStateConfig;
  columnCount: number;
}

export function ErrorState({ config, columnCount }: ErrorStateProps) {
  const { error, onRetry } = config;

  if (!error) return null;

  return (
    <TableRow>
      <TableCell colSpan={columnCount} className="h-[400px]">
        <div className="flex flex-col items-center justify-center space-y-4">
          <div className="rounded-full bg-destructive/10 p-4">
            <AlertCircle className="h-12 w-12 text-destructive" />
          </div>
          <div className="space-y-2 text-center max-w-md">
            <h3 className="text-lg font-semibold">Something went wrong</h3>
            <p className="text-sm text-muted-foreground">
              {error.message || "An error occurred while loading the data."}
            </p>
          </div>
          {onRetry && (
            <Button onClick={onRetry} variant="outline" className="gap-2">
              <RefreshCw className="h-4 w-4" />
              Try Again
            </Button>
          )}
        </div>
      </TableCell>
    </TableRow>
  );
}

// Error Boundary Component
interface ErrorBoundaryProps {
  children: ReactNode;
  fallback?: ReactNode;
  onError?: (error: Error, errorInfo: ErrorInfo) => void;
}

interface ErrorBoundaryState {
  hasError: boolean;
  error: Error | null;
}

export class TableErrorBoundary extends Component<
  ErrorBoundaryProps,
  ErrorBoundaryState
> {
  constructor(props: ErrorBoundaryProps) {
    super(props);
    this.state = { hasError: false, error: null };
  }

  static getDerivedStateFromError(error: Error): ErrorBoundaryState {
    return { hasError: true, error };
  }

  componentDidCatch(error: Error, errorInfo: ErrorInfo) {
    console.error("Table Error Boundary caught an error:", error, errorInfo);
    this.props.onError?.(error, errorInfo);
  }

  render() {
    if (this.state.hasError) {
      if (this.props.fallback) {
        return this.props.fallback;
      }

      return (
        <Card className="border-destructive">
          <CardContent className="pt-6">
            <div className="flex flex-col items-center justify-center space-y-4 py-8">
              <div className="rounded-full bg-destructive/10 p-4">
                <AlertCircle className="h-12 w-12 text-destructive" />
              </div>
              <div className="space-y-2 text-center max-w-md">
                <h3 className="text-lg font-semibold">Table Error</h3>
                <p className="text-sm text-muted-foreground">
                  {this.state.error?.message ||
                    "An unexpected error occurred in the table component."}
                </p>
              </div>
              <Button
                onClick={() => this.setState({ hasError: false, error: null })}
                variant="outline"
                className="gap-2"
              >
                <RefreshCw className="h-4 w-4" />
                Try Again
              </Button>
            </div>
          </CardContent>
        </Card>
      );
    }

    return this.props.children;
  }
}

// No Results Found (for filtered data)
interface NoResultsProps {
  columnCount: number;
  onClearFilters?: () => void;
}

export function NoResults({ columnCount, onClearFilters }: NoResultsProps) {
  return (
    <TableRow>
      <TableCell colSpan={columnCount} className="h-[300px]">
        <div className="flex flex-col items-center justify-center space-y-4">
          <FileX2 className="h-12 w-12 text-muted-foreground/50" />
          <div className="space-y-2 text-center">
            <h3 className="text-lg font-semibold">No results found</h3>
            <p className="text-sm text-muted-foreground max-w-sm">
              No data matches your current filters. Try adjusting your search or
              filter criteria.
            </p>
          </div>
          {onClearFilters && (
            <Button onClick={onClearFilters} variant="outline">
              Clear Filters
            </Button>
          )}
        </div>
      </TableCell>
    </TableRow>
  );
}
