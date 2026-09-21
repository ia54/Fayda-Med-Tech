"use client";

import { useState, useEffect } from "react";
import { Loader2, Circle } from "lucide-react";
import Image from "next/image";
import { cn } from "@/lib/utils";

// Shared logo component for consistency
export const AppLogo = ({
  size = 70,
  showRing = false,
  pulseAnimation = false,
}: {
  size?: number;
  showRing?: boolean;
  pulseAnimation?: boolean;
}) => (
  <div
    className={cn("relative", pulseAnimation && "animate-pulse")}
    style={{
      animation: pulseAnimation ? "pulse 2s ease-in-out infinite" : "none",
    }}
  >
    <div
      className="rounded-full border-4 border-blue-100 dark:border-gray-700 shadow-lg bg-white dark:bg-gray-800 flex items-center justify-center"
      style={{
        width: size,
        height: size,
        boxShadow: `0 ${size / 10}px ${size / 2.5}px rgba(25, 118, 210, 0.2)`,
      }}
    >
      <div className="flex h-full w-full items-center justify-center p-2">
        <Image
          src="/images/fayda-logo.png"
          alt="Fayda Wellness Pharmacy"
          width={size * 0.8}
          height={size * 0.8}
          className="object-contain"
        />
      </div>
    </div>

    {showRing && (
      <div
        className="absolute top-[-4px] left-[-4px] right-[-4px] bottom-[-4px] rounded-full border-2 border-transparent border-t-blue-600 dark:border-t-blue-400 animate-spin"
        style={{
          animation: "spin 1s linear infinite",
        }}
      />
    )}

    <style jsx>{`
      @keyframes pulse {
        0% {
          transform: scale(1);
          opacity: 1;
        }
        50% {
          transform: scale(1.05);
          opacity: 0.9;
        }
        100% {
          transform: scale(1);
          opacity: 1;
        }
      }
      @keyframes spin {
        0% {
          transform: rotate(0deg);
        }
        100% {
          transform: rotate(360deg);
        }
      }
    `}</style>
  </div>
);

// Shared app title component
export const AppTitle = ({
  variant = "h4",
  subtitle = null,
}: {
  variant?: "h4" | "h5";
  subtitle?: string | null;
}) => {
  const headingClasses =
    variant === "h4"
      ? "text-3xl mb-3 md:mb-4 font-bold bg-gradient-to-r from-blue-600 to-teal-500 dark:from-blue-400 dark:to-teal-300 bg-clip-text text-transparent tracking-wide"
      : "text-2xl mb-2 md:mb-3 font-bold bg-gradient-to-r from-blue-600 to-teal-500 dark:from-blue-400 dark:to-teal-300 bg-clip-text text-transparent tracking-wide";

  const subtitleClasses = "mb-3 text-base text-muted-foreground font-medium";

  return (
    <>
      <h1 className={headingClasses}>Fayda Wellness Pharmacy</h1>
      {subtitle && <p className={subtitleClasses}>{subtitle}</p>}
    </>
  );
};

// Animated loading dots component
export const LoadingDots = ({
  count = 3,
  size = 8,
  color = "blue",
}: {
  count?: number;
  size?: number;
  color?: string;
}) => {
  const colorClasses =
    {
      blue: "bg-blue-600 dark:bg-blue-400",
      gray: "bg-gray-400 dark:bg-gray-300",
      green: "bg-green-500 dark:bg-green-400",
    }[color] || "bg-blue-600";

  return (
    <div className="flex gap-2 items-center">
      {Array.from({ length: count }, (_, index) => (
        <div
          key={index}
          className={cn("rounded-full", colorClasses, "animate-bounce")}
          style={{
            width: `${size}px`,
            height: `${size}px`,
            animation: `bounce ${1 + index * 0.1}s ease-in-out infinite`,
            animationDelay: `${index * 0.1}s`,
          }}
        />
      ))}
      <style jsx>{`
        @keyframes bounce {
          0%,
          100% {
            transform: translateY(0);
          }
          50% {
            transform: translateY(-5px);
          }
        }
      `}</style>
    </div>
  );
};

// Skeleton card component for theme loading
export const SkeletonCard = ({
  index,
  width = 180,
  height = 120,
}: {
  index: number;
  width?: number;
  height?: number;
}) => (
  <div
    className="p-4 rounded-xl border bg-gradient-to-br from-white to-gray-50 dark:from-gray-800 dark:to-gray-900 flex flex-col gap-3 shadow-sm animate-in fade-in slide-in-from-bottom-4"
    style={{
      width,
      height,
      animation: `fadeInUp ${0.6 + index * 0.2}s ease-out`,
    }}
  >
    {/* Card Header */}
    <div className="flex items-center gap-2">
      <div className="rounded-full bg-gray-200 dark:bg-gray-700 w-8 h-8 animate-pulse" />
      <div className="bg-gray-200 dark:bg-gray-700 rounded h-4 w-24 animate-pulse" />
    </div>

    {/* Card Content */}
    <div className="bg-gray-200 dark:bg-gray-700 rounded h-12 w-full animate-pulse" />

    {/* Card Footer */}
    <div className="flex justify-between mt-auto">
      <div className="bg-gray-200 dark:bg-gray-700 rounded h-3 w-16 animate-pulse" />
      <div className="bg-gray-200 dark:bg-gray-700 rounded h-3 w-10 animate-pulse" />
    </div>

    <style jsx>{`
      @keyframes fadeInUp {
        0% {
          opacity: 0;
          transform: translateY(20px);
        }
        100% {
          opacity: 1;
          transform: translateY(0);
        }
      }
    `}</style>
  </div>
);

// Navigation skeleton component
export const NavigationSkeleton = ({
  itemCount = 4,
  itemWidth = 80,
}: {
  itemCount?: number;
  itemWidth?: number;
}) => (
  <div
    className="flex gap-3 animate-in fade-in"
    style={{
      animation: "fadeIn 1.2s ease-out",
    }}
  >
    {Array.from({ length: itemCount }, (_, index) => (
      <div
        key={index}
        className="bg-gray-100 dark:bg-gray-800 rounded-lg h-8 animate-pulse"
        style={{
          width: itemWidth,
        }}
      />
    ))}
    <style jsx>{`
      @keyframes fadeIn {
        0% {
          opacity: 0;
        }
        100% {
          opacity: 1;
        }
      }
    `}</style>
  </div>
);

// Full-screen loading container
const LoadingContainer = ({ children }: { children: React.ReactNode }) => (
  <div className="fixed top-0 left-0 right-0 bottom-0 bg-white dark:bg-gray-950 flex flex-col items-center justify-center z-[9999] font-sans">
    {children}
  </div>
);

// Theme loading skeleton with modern cards
export function ThemeLoadingSkeleton() {
  const [isClient, setIsClient] = useState(false);

  useEffect(() => {
    setIsClient(true);
  }, []);

  // During SSR, return a minimal loading state
  if (!isClient) {
    return (
      <LoadingContainer>
        <div className="text-center">
          <div
            className="mx-auto mb-2 rounded-full bg-gray-100 dark:bg-gray-800"
            style={{
              width: 80,
              height: 80,
            }}
          />
          <div className="mx-auto bg-gray-100 dark:bg-gray-800 rounded h-5 w-40" />
        </div>
      </LoadingContainer>
    );
  }

  return (
    <LoadingContainer>
      <AppLogo size={80} showRing pulseAnimation />
      <AppTitle subtitle="Initializing theme..." />

      {/* Modern Skeleton Cards */}
      <div className="flex gap-4 mb-6 flex-wrap justify-center max-w-2xl">
        {Array.from({ length: 3 }, (_, index) => (
          <SkeletonCard key={index} index={index} />
        ))}
      </div>

      <NavigationSkeleton />
    </LoadingContainer>
  );
}

// Persist loading skeleton
export function PersistLoadingSkeleton() {
  const [isClient, setIsClient] = useState(false);

  useEffect(() => {
    setIsClient(true);
  }, []);

  // During SSR, return null to prevent hydration issues
  if (!isClient) {
    return null;
  }

  return (
    <LoadingContainer>
      <AppLogo size={70} showRing />
      <AppTitle variant="h5" subtitle="Loading application..." />
      <LoadingDots />
    </LoadingContainer>
  );
}

// SSR loading skeleton with proper name
export function SSRLoadingSkeleton() {
  return (
    <LoadingContainer>
      {/* Pharmacy Logo Placeholder for SSR */}
      <div
        className="mb-4 rounded-full bg-blue-100 dark:bg-gray-800 shadow-md flex items-center justify-center"
        style={{
          width: 80,
          height: 80,
          boxShadow: "0 4px 20px rgba(25, 118, 210, 0.15)",
        }}
      >
        <div className="flex items-center justify-center w-full h-full p-2">
          <Image
            src="/images/fayda-logo.png"
            alt="Fayda Wellness Pharmacy"
            width={50}
            height={50}
            className="object-contain"
          />
        </div>
      </div>

      <div className="mt-4 flex items-center gap-2">
        <Loader2 className="h-10 w-10 animate-spin text-blue-600 dark:text-blue-400" />
      </div>
    </LoadingContainer>
  );
}
