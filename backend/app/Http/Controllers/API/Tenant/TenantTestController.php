<?php

namespace App\Http\Controllers\API\Tenant;

use App\Http\Controllers\API\BaseAPIController;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * Tenant Test Controller
 * 
 * Simple controller to test tenant identification and context.
 */
class TenantTestController extends BaseAPIController
{
    /**
     * Test tenant context
     */
    public function testTenantContext(Request $request): JsonResponse
    {
        $tenant = tenant();
        
        return $this->sendResponse([
            'tenant_identified' => $tenant !== null,
            'tenant_id' => $tenant?->id,
            'tenant_slug' => $tenant?->slug,
            'tenant_name' => $tenant?->name,
            'request_tenant_slug' => $request->get('tenant_slug'),
            'route_tenant_param' => $request->route('tenant'),
            'current_path' => $request->path(),
            'full_url' => $request->fullUrl(),
        ], 'Tenant context information retrieved successfully.');
    }
}
