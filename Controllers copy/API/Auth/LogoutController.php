<?php

namespace App\Http\Controllers\API\Auth;

use App\Http\Controllers\API\BaseAPIController;
use Illuminate\Http\Request;

class LogoutController extends BaseAPIController
{
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return $this->sendResponse([], 'Successfully logged out');
    }
}
