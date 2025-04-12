<?php

namespace App\Http\Controllers\API\Auth;

use App\Http\Controllers\API\BaseAPIController;
use Illuminate\Http\Request;

class UserController extends BaseAPIController
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
        
        return $this->sendResponse([
            'user' => $user
        ]);
    }
}
