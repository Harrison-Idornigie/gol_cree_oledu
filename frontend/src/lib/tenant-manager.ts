"use client";

import { TenantInfo } from "@/types/tenant/user";

/**
 * Tenant Manager
 * 
 * Handles tenant context switching and management in the frontend
 */

interface TenantSwitchResult {
  success: boolean;
  error?: string;
  redirectUrl?: string;
}

export class TenantManager {
  private static readonly TENANT_STORAGE_KEY = 'available_tenants';
  private static readonly CURRENT_TENANT_KEY = 'current_tenant';

  /**
   * Store available tenants for the current user
   */
  static setAvailableTenants(tenants: TenantInfo[]): void {
    if (typeof window === 'undefined') return;
    
    try {
      localStorage.setItem(this.TENANT_STORAGE_KEY, JSON.stringify(tenants));
    } catch (error) {
      console.error('Failed to store available tenants:', error);
    }
  }

  /**
   * Get available tenants for the current user
   */
  static getAvailableTenants(): TenantInfo[] {
    if (typeof window === 'undefined') return [];
    
    try {
      const stored = localStorage.getItem(this.TENANT_STORAGE_KEY);
      return stored ? JSON.parse(stored) : [];
    } catch (error) {
      console.error('Failed to retrieve available tenants:', error);
      return [];
    }
  }

  /**
   * Set current tenant context
   */
  static setCurrentTenant(tenant: TenantInfo | null): void {
    if (typeof window === 'undefined') return;
    
    try {
      if (tenant) {
        localStorage.setItem(this.CURRENT_TENANT_KEY, JSON.stringify(tenant));
      } else {
        localStorage.removeItem(this.CURRENT_TENANT_KEY);
      }
    } catch (error) {
      console.error('Failed to set current tenant:', error);
    }
  }

  /**
   * Get current tenant context
   */
  static getCurrentTenant(): TenantInfo | null {
    if (typeof window === 'undefined') return null;
    
    try {
      const stored = localStorage.getItem(this.CURRENT_TENANT_KEY);
      return stored ? JSON.parse(stored) : null;
    } catch (error) {
      console.error('Failed to retrieve current tenant:', error);
      return null;
    }
  }

  /**
   * Switch to a different tenant context
   */
  static async switchTenant(targetTenant: TenantInfo, userRole: string): Promise<TenantSwitchResult> {
    try {
      // Validate that user has access to this tenant
      const availableTenants = this.getAvailableTenants();
      const hasAccess = availableTenants.some(tenant => tenant.slug === targetTenant.slug);
      
      if (!hasAccess) {
        return {
          success: false,
          error: 'You do not have access to this organization'
        };
      }

      // Update current tenant context
      this.setCurrentTenant(targetTenant);

      // Build redirect URL based on user role
      const redirectUrl = this.buildTenantUrl(targetTenant.slug, userRole);

      return {
        success: true,
        redirectUrl
      };
    } catch (error) {
      console.error('Failed to switch tenant:', error);
      return {
        success: false,
        error: 'Failed to switch organization context'
      };
    }
  }

  /**
   * Build tenant-specific URL
   */
  static buildTenantUrl(tenantSlug: string, role: string, path: string = ''): string {
    const basePath = `/${tenantSlug}/${role}`;
    return path ? `${basePath}${path.startsWith('/') ? path : `/${path}`}` : basePath;
  }

  /**
   * Extract tenant slug from current URL
   */
  static getCurrentTenantSlugFromUrl(): string | null {
    if (typeof window === 'undefined') return null;
    
    const pathSegments = window.location.pathname.replace(/^\//, '').split('/');
    if (pathSegments.length < 2) return null;
    
    const [tenantSlug, role] = pathSegments;
    const validRoles = ['admin', 'team', 'student'];
    
    return /^[a-z0-9-]+$/.test(tenantSlug) && validRoles.includes(role) ? tenantSlug : null;
  }

  /**
   * Check if current URL is in tenant context
   */
  static isInTenantContext(): boolean {
    return this.getCurrentTenantSlugFromUrl() !== null;
  }

  /**
   * Clear all tenant data (on logout)
   */
  static clearTenantData(): void {
    if (typeof window === 'undefined') return;
    
    try {
      localStorage.removeItem(this.TENANT_STORAGE_KEY);
      localStorage.removeItem(this.CURRENT_TENANT_KEY);
    } catch (error) {
      console.error('Failed to clear tenant data:', error);
    }
  }

  /**
   * Sync tenant context with URL
   */
  static syncWithUrl(): TenantInfo | null {
    const urlTenantSlug = this.getCurrentTenantSlugFromUrl();
    const currentTenant = this.getCurrentTenant();
    
    if (urlTenantSlug && (!currentTenant || currentTenant.slug !== urlTenantSlug)) {
      // URL has tenant context but stored tenant doesn't match
      const availableTenants = this.getAvailableTenants();
      const matchingTenant = availableTenants.find(t => t.slug === urlTenantSlug);
      
      if (matchingTenant) {
        this.setCurrentTenant(matchingTenant);
        return matchingTenant;
      }
    }
    
    return currentTenant;
  }
}

/**
 * React hook for tenant management
 */
export function useTenantManager() {
  const switchTenant = async (tenant: TenantInfo, userRole: string) => {
    const result = await TenantManager.switchTenant(tenant, userRole);
    
    if (result.success && result.redirectUrl) {
      window.location.href = result.redirectUrl;
    }
    
    return result;
  };

  const getCurrentTenant = () => TenantManager.getCurrentTenant();
  const getAvailableTenants = () => TenantManager.getAvailableTenants();
  const isInTenantContext = () => TenantManager.isInTenantContext();

  return {
    switchTenant,
    getCurrentTenant,
    getAvailableTenants,
    isInTenantContext,
    clearTenantData: TenantManager.clearTenantData,
    syncWithUrl: TenantManager.syncWithUrl
  };
}
