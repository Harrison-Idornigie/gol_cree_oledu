"use client";

import { AuthProvider } from "./auth-provider";
import { TenantProvider } from "./tenant-provider";
import { Toaster } from "@/components/ui/toaster";

export function RootProvider({ children }: { children: React.ReactNode }) {
  return (
    <AuthProvider>
      <TenantProvider>
        {children}
        <Toaster />
      </TenantProvider>
    </AuthProvider>
  );
}
