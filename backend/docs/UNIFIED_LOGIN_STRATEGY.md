# Unified Login Strategy

## Overview

Implement a single, intelligent login page that handles all user types while maintaining our dual-controller backend architecture.

## Authentication Flow Design

### 1. Login Page Strategy: Single Unified Page

**URL**: `/login` (central route, no tenant context)

**User Experience Flow**:
```
User visits /login
    ↓
Enter email/password
    ↓
System determines user type and context
    ↓
Route to appropriate authentication endpoint
    ↓
Redirect to appropriate dashboard
```

### 2. User Type Detection Strategy

#### Option A: Email-Based Pre-Detection (Recommended)
```typescript
// Frontend: Check user context before authentication
async function detectUserContext(email: string) {
  const response = await api.post('/api/auth/detect-user-context', { email });
  return response.data; // { userType: 'central' | 'tenant', tenants: [...] }
}
```

#### Option B: Attempt Central First, Then Tenant
```typescript
// Try central authentication first, fallback to tenant
async function authenticateUser(email: string, password: string, tenantSlug?: string) {
  try {
    // Try central authentication first
    return await centralLogin(email, password);
  } catch (centralError) {
    // If central fails and we have tenant context, try tenant login
    if (tenantSlug) {
      return await tenantLogin(email, password, tenantSlug);
    }
    throw centralError;
  }
}
```

### 3. Context Detection from URL

When user accesses tenant-specific URLs before login:

```
User visits: /school-district-1/admin/dashboard
    ↓
Middleware redirects to: /login?redirect=/school-district-1/admin/dashboard
    ↓
Login page extracts tenant context from redirect URL
    ↓
Use tenant-specific authentication
    ↓
Redirect back to original URL
```

### 4. Multi-Tenant User Handling

For users belonging to multiple tenants:

```typescript
interface LoginResponse {
  token: string;
  user: User;
  authContext: 'central' | 'tenant';
  tenantSlug?: string;
  availableTenants?: Tenant[];
  redirect: string;
}

// If user has multiple tenants and no specific context
if (response.availableTenants?.length > 1 && !tenantSlug) {
  // Show tenant selection modal
  showTenantSelection(response.availableTenants);
} else {
  // Direct redirect
  window.location.href = response.redirect;
}
```

## Implementation Plan

### Phase 1: Backend User Context Detection

Create new endpoint for user context detection:

```php
// New controller method
public function detectUserContext(Request $request)
{
    $email = $request->email;
    
    // Check if user exists in central database
    $centralUser = CentralUser::where('email', $email)->first();
    if ($centralUser) {
        return response()->json([
            'userType' => 'central',
            'tenants' => []
        ]);
    }
    
    // Check tenant associations
    $userTenants = $this->userTenantService->getUserTenants($email);
    
    return response()->json([
        'userType' => 'tenant',
        'tenants' => $userTenants->map(function($tenantData) {
            return [
                'slug' => $tenantData['tenant']->slug,
                'name' => $tenantData['tenant']->name,
                'role' => $tenantData['user']->role
            ];
        })
    ]);
}
```

### Phase 2: Frontend Unified Login Component

```typescript
// Unified login logic
export async function handleLogin(email: string, password: string, redirectUrl?: string) {
  // Extract tenant context from redirect URL if present
  const tenantContext = extractTenantFromRedirectUrl(redirectUrl);
  
  // Detect user context
  const userContext = await detectUserContext(email);
  
  // Determine authentication strategy
  if (userContext.userType === 'central') {
    return await centralLogin(email, password);
  } else {
    // Handle tenant authentication
    if (tenantContext) {
      // Direct tenant login
      return await tenantLogin(email, password, tenantContext.tenantSlug);
    } else if (userContext.tenants.length === 1) {
      // Single tenant - direct login
      return await tenantLogin(email, password, userContext.tenants[0].slug);
    } else {
      // Multiple tenants - need selection
      return { needsTenantSelection: true, tenants: userContext.tenants };
    }
  }
}
```

### Phase 3: Smart Redirect Logic

```typescript
function determineRedirectUrl(loginResponse: LoginResponse, originalUrl?: string): string {
  // If user was trying to access a specific URL, redirect there
  if (originalUrl && isValidRedirectUrl(originalUrl)) {
    return originalUrl;
  }
  
  // Use the redirect provided by the backend
  return loginResponse.redirect;
}
```

## URL Patterns and Routing

### Central Users (Super Admins)
- Login: `/login` → Central authentication → `/super/dashboard`
- Direct access: `/super/*` → Redirect to `/login?redirect=/super/*`

### Tenant Users
- Login: `/login` → Tenant selection (if multiple) → `/{tenant}/role/dashboard`
- Direct access: `/{tenant}/role/*` → Redirect to `/login?redirect=/{tenant}/role/*`

### Multi-Tenant Users
- Login: `/login` → Tenant selection modal → Choose tenant → `/{tenant}/role/dashboard`
- Bookmark: `/{tenant}/role/*` → Direct authentication for that tenant

## Security Considerations

1. **Rate Limiting**: Apply per-email rate limiting across both authentication endpoints
2. **Context Validation**: Ensure users can only access tenants they belong to
3. **Redirect Validation**: Validate redirect URLs to prevent open redirects
4. **Session Management**: Proper token management for different contexts

## Benefits of This Approach

1. **Single Entry Point**: Users always know where to login
2. **Context Preservation**: Maintains user's intended destination
3. **Flexible Backend**: Keeps existing dual-controller architecture
4. **Scalable**: Easy to add new user types or authentication methods
5. **User-Friendly**: Handles complex scenarios transparently
