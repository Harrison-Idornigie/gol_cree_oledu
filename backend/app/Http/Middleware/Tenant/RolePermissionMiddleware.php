<?php

namespace App\Http\Middleware\Tenant;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Role Permission Middleware
 * 
 * Enhanced middleware that supports both role-based and permission-based checking.
 * while adding support for role-based authorization and advanced logic.
 * 
 * Usage:
 * - Route::middleware(['auth:tenant', 'verified', 'role_permission:team']) // Permission check
 * - Route::middleware(['role_permission:role:content-manager']) // Role check
 * - Route::middleware(['role_permission:words.create,words.edit']) // Multiple permissions (OR)
 * - Route::middleware(['role_permission:words.create&words.edit']) // Multiple permissions (AND)
 * - Route::middleware(['role_permission:role:manager|permission:admin']) // Mixed role/permission logic
 */
class RolePermissionMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  $requirements
     * @param  string  $operator (and|or) - defaults to 'or'
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next, string $requirements, string $operator = 'or'): Response
    {
        $user = $request->user('tenant');

        if (!$user) {
            return $this->unauthorizedResponse('User must be authenticated to access this resource');
        }

        // Parse the requirements string into structured format
        $parsedRequirements = $this->parseRequirements($requirements);
        
        // Check if user meets the requirements
        $hasAccess = $this->checkUserAccess($user, $parsedRequirements, $operator);

        if (!$hasAccess) {
            // Log authorization failure for security monitoring
            Log::warning('Authorization failed', [
                'user_id' => $user->id,
                'email' => $user->email,
                'membership' => $user->membership,
                'requirements' => $requirements,
                'operator' => $operator,
                'route' => $request->route()?->getName(),
                'url' => $request->url(),
                'user_roles' => $user->getRoleSlugs(),
                'timestamp' => now()->toISOString()
            ]);

            return $this->forbiddenResponse($parsedRequirements, $operator, $user);
        }

        // Log successful authorization for audit trail
        Log::info('Authorization successful', [
            'user_id' => $user->id,
            'email' => $user->email,
            'requirements' => $requirements,
            'route' => $request->route()?->getName()
        ]);

        return $next($request);
    }

    /**
     * Parse requirements string into structured format supporting roles and permissions
     */
    private function parseRequirements(string $requirements): array
    {
        $parsed = [];
        
        // First, split by pipes (|) for OR groups
        $orGroups = explode('|', $requirements);
        
        foreach ($orGroups as $group) {
            $groupParsed = [];
            
            // Split by ampersand (&) for AND requirements within the group
            $andRequirements = explode('&', $group);
            
            foreach ($andRequirements as $requirement) {
                $requirement = trim($requirement);
                
                // Check if it's a role requirement (role:slug-name)
                if (str_starts_with($requirement, 'role:')) {
                    $roleSlug = substr($requirement, 5);
                    $groupParsed[] = [
                        'type' => 'role',
                        'value' => $roleSlug
                    ];
                }
                // Check if it's a permission requirement (permission:slug or just slug)
                elseif (str_starts_with($requirement, 'permission:')) {
                    $permissionSlug = substr($requirement, 11);
                    $groupParsed[] = [
                        'type' => 'permission',
                        'value' => $permissionSlug
                    ];
                }
                // Default to permission if no prefix
                else {
                    // Handle comma-separated permissions (legacy OR logic)
                    $permissions = array_map('trim', explode(',', $requirement));
                    foreach ($permissions as $permission) {
                        $groupParsed[] = [
                            'type' => 'permission',
                            'value' => $permission
                        ];
                    }
                }
            }
            
            $parsed[] = $groupParsed;
        }
        
        return $parsed;
    }

    /**
     * Check if user has access based on parsed requirements
     */
    private function checkUserAccess($user, array $parsedRequirements, string $operator): bool
    {
        // Auto-detect operator from requirement structure if not explicitly set
        if ($operator === 'or' && count($parsedRequirements) === 1 && count($parsedRequirements[0]) > 1) {
            // Check if original string had & operator
            $originalRequirement = request()->route()?->getAction('middleware');
            if (is_array($originalRequirement)) {
                foreach ($originalRequirement as $middleware) {
                    if (is_string($middleware) && strpos($middleware, '&') !== false) {
                        $operator = 'and';
                        break;
                    }
                }
            }
        }

        // Use caching for performance on repeated permission checks
        $cacheKey = "user_access_{$user->id}_" . md5(serialize($parsedRequirements) . $operator);
        
        return Cache::remember($cacheKey, 300, function () use ($user, $parsedRequirements, $operator) {
            return $this->evaluateRequirements($user, $parsedRequirements, $operator);
        });
    }

    /**
     * Evaluate requirements against user's roles and permissions
     */
    private function evaluateRequirements($user, array $parsedRequirements, string $operator): bool
    {
        if ($operator === 'and') {
            // User must satisfy ALL requirement groups
            foreach ($parsedRequirements as $group) {
                if (!$this->evaluateGroup($user, $group, 'and')) {
                    return false;
                }
            }
            return true;
        } else {
            // User must satisfy ANY requirement group (default OR logic)
            foreach ($parsedRequirements as $group) {
                if ($this->evaluateGroup($user, $group, 'or')) {
                    return true;
                }
            }
            return false;
        }
    }

    /**
     * Evaluate a single requirement group
     */
    private function evaluateGroup($user, array $group, string $groupOperator): bool
    {
        if (empty($group)) {
            return false;
        }

        if ($groupOperator === 'and') {
            // User must satisfy ALL requirements in the group
            foreach ($group as $requirement) {
                if (!$this->checkSingleRequirement($user, $requirement)) {
                    return false;
                }
            }
            return true;
        } else {
            // User must satisfy ANY requirement in the group
            foreach ($group as $requirement) {
                if ($this->checkSingleRequirement($user, $requirement)) {
                    return true;
                }
            }
            return false;
        }
    }

    /**
     * Check a single requirement (role or permission)
     */
    private function checkSingleRequirement($user, array $requirement): bool
    {
        switch ($requirement['type']) {
            case 'role':
                return $user->hasRole($requirement['value']);
            
            case 'permission':
                return $user->hasPermission($requirement['value']);
            
            default:
                return false;
        }
    }

    /**
     * Build descriptive error message for authorization failures
     */
    private function buildErrorMessage(array $parsedRequirements, string $operator): string
    {
        $descriptions = [];
        
        foreach ($parsedRequirements as $group) {
            $groupDescriptions = [];
            foreach ($group as $requirement) {
                if ($requirement['type'] === 'role') {
                    $groupDescriptions[] = "role '{$requirement['value']}'";
                } else {
                    $groupDescriptions[] = "permission '{$requirement['value']}'";
                }
            }
            $descriptions[] = implode(' and ', $groupDescriptions);
        }
        
        if (count($descriptions) === 1) {
            return "User requires {$descriptions[0]} to access this resource.";
        }
        
        if ($operator === 'and') {
            return "User requires all of the following: " . implode(', and ', $descriptions) . ".";
        } else {
            return "User requires at least one of the following: " . implode(', or ', $descriptions) . ".";
        }
    }

    /**
     * Return unauthorized response
     */
    private function unauthorizedResponse(string $message): Response
    {
        return response()->json([
            'success' => false,
            'message' => 'Unauthenticated',
            'error' => $message
        ], 401);
    }

    /**
     * Return forbidden response with detailed information
     */
    private function forbiddenResponse(array $parsedRequirements, string $operator, $user): Response
    {
        return response()->json([
            'success' => false,
            'message' => 'Insufficient permissions',
            'error' => $this->buildErrorMessage($parsedRequirements, $operator),
            'required_access' => $this->formatRequirementsForResponse($parsedRequirements),
            'user_membership' => $user->membership,
            'user_roles' => $user->getRoleSlugs(),
            'user_permissions' => $this->getUserBasicPermissions($user)
        ], 403);
    }

    /**
     * Format requirements for API response
     */
    private function formatRequirementsForResponse(array $parsedRequirements): array
    {
        $formatted = [];
        
        foreach ($parsedRequirements as $group) {
            $groupFormatted = [];
            foreach ($group as $requirement) {
                $groupFormatted[] = [
                    'type' => $requirement['type'],
                    'value' => $requirement['value']
                ];
            }
            $formatted[] = $groupFormatted;
        }
        
        return $formatted;
    }

    /**
     * Get basic user permissions for debugging (without exposing sensitive info)
     */
    private function getUserBasicPermissions($user): array
    {
        // Return a limited set of permissions for security
        return array_slice($user->getAllPermissions(), 0, 10);
    }
}