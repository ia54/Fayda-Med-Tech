import type React from "react";
import type { Metadata } from "next";

import { AuthGuard } from "@/components/guards/auth-guard";
import Layout from "@/components/layouts/layout";

export const metadata: Metadata = {
  title: "Data platform dashboard",
  description: "Data platform dashboard",
  generator: "tanvir mitul",
};

export default function RootLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <AuthGuard redirectTo="/auth/login">
      <Layout>{children}</Layout>
    </AuthGuard>
  );
}
