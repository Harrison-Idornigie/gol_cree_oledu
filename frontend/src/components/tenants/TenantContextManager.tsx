'use client';

import { useEffect } from 'react';
import { usePathname } from 'next/navigation';
import { extractTenantContext } from '@/app/contexts/tenant-context';
import { setTenantSlugCookie, clearTenantSlugCookie } from '@/app/_actions/auth-actions';

/**
 * TenantContextManager
 * 
 * Client-side component that manages tenant context cookies
 * based on the current URL path. This ensures server actions
 * have access to the current tenant slug.
 */
export default function TenantContextManager() {
  const pathname = usePathname();

  useEffect(() => {
    const manageTenantContext = async () => {
      try {
        const context = extractTenantContext(pathname);
        
        if (context.isValidPath && context.tenant?.slug) {
          // Set tenant slug cookie for server actions
          await setTenantSlugCookie(context.tenant.slug);
          console.log('[TenantContextManager] Set tenant slug cookie:', context.tenant.slug);
        } else {
          // Clear tenant slug cookie if not on a valid tenant path
          await clearTenantSlugCookie();
          console.log('[TenantContextManager] Cleared tenant slug cookie');
        }
      } catch (error) {
        console.error('[TenantContextManager] Error managing tenant context:', error);
      }
    };

    manageTenantContext();
  }, [pathname]);

  // This component doesn't render anything
  return null;
}
