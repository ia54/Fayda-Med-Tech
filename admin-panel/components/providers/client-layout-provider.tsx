"use client";

import { useEffect, useState } from "react";
import { SSRLoadingSkeleton } from "@/components/ui/loading/LoadingComponents";
import { ThemeProvider } from "@/components/theme-provider";
import { ReduxProvider } from "@/components/providers/redux-provider";
import { Toaster } from "@/components/ui/toaster";
import { Toaster as SonnerToaster } from "sonner";
import { ModalHost } from "@/components/providers/modal-host";
import { Analytics } from "@vercel/analytics/next";
import "react-quill-new/dist/quill.snow.css";

export function ClientLayoutProvider({
  children,
}: {
  children: React.ReactNode;
}) {
  const [mounted, setMounted] = useState(false);

  // Handle client-side mounting for SSR compatibility
  useEffect(() => {
    setMounted(true);
  }, []);

  // Show SSR loading skeleton while mounting
  if (!mounted) {
    return <SSRLoadingSkeleton />;
  }

  return (
    <ReduxProvider>
      <ThemeProvider
        attribute="class"
        defaultTheme="system"
        enableSystem
        disableTransitionOnChange
      >
        {children}
        <Toaster />
        <SonnerToaster />
        <Analytics />
        <ModalHost />
      </ThemeProvider>
    </ReduxProvider>
  );
}
