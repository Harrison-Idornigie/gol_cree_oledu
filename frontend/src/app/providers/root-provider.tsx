"use client";

import { AuthProvider } from "./auth-provider";
import { Toaster } from "@/components/ui/toaster";
import TenantContextManager from "@/components/tenants/TenantContextManager";

export function RootProvider({ children }: { children: React.ReactNode }) {
  return (
    <AuthProvider>
      <TenantContextManager />
      {children}
      <Toaster />
    </AuthProvider>
  );
}
