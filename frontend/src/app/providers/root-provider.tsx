"use client";

import { AuthProvider } from "./auth-provider";
import { Toaster } from "@/components/ui/toaster";

export function RootProvider({ children }: { children: React.ReactNode }) {
  return (
    <AuthProvider>
      {children}
      <Toaster />
    </AuthProvider>
  );
}
