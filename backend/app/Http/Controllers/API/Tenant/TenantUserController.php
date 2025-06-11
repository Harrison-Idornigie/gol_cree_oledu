<?php

namespace App\Http\Controllers\API\Tenant;

use App\Helpers\Tenants\TenantHelper;
use App\Http\Controllers\API\BaseAPIController;
use Illuminate\Http\Request;

class TenantUserController extends BaseAPIController
{
    /**
     * Get the authenticated user
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function me(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return $this->sendError('Unauthenticated', [], 401);
        }

        // Prepare user data
        $userData = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'email_verified_at' => $user->email_verified_at,
        ];

        // Add tenant context if available
        $userData = TenantHelper::addTenantContextToUser($userData);

        return $this->sendResponse([
            'user' => $userData
        ]);
    }
}
