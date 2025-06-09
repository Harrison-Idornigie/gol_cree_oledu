<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class BaseAPIController extends Controller
{
    /**
     * Send a success response.
     */
    protected function sendResponse($data, string $message = '', int $code = HttpResponse::HTTP_OK): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => $message,
        ], $code);
    }

    /**
     * Send an error response.
     */
    protected function sendError(string $message, array $errors = [], int $code = HttpResponse::HTTP_BAD_REQUEST): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $code);
    }

    /**
     * Send a pagination response.
     * @param \Illuminate\Pagination\LengthAwarePaginator $data
     */
    protected function sendPaginatedResponse($data, string $message = '', int $code = HttpResponse::HTTP_OK): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $data->items(),
            'pagination' => [
                'total' => $data->total(),
                'per_page' => $data->perPage(),
                'current_page' => $data->currentPage(),
                'last_page' => $data->lastPage(),
                'from' => $data->firstItem(),
                'to' => $data->lastItem(),
            ],
            'message' => $message,
        ], $code);
    }

    /**
     * Send a created response.
     */
    protected function sendCreatedResponse($data, string $message = ''): JsonResponse
    {
        return $this->sendResponse($data, $message, HttpResponse::HTTP_CREATED);
    }

    /**
     * Send a no content response.
     */
    protected function sendNoContentResponse(): JsonResponse
    {
        return response()->json(null, HttpResponse::HTTP_NO_CONTENT);
    }

    /**
     * Send a not found response.
     */
    protected function sendNotFoundResponse(string $message = 'Resource not found'): JsonResponse
    {
        return $this->sendError($message, [], HttpResponse::HTTP_NOT_FOUND);
    }

    /**
     * Send an unauthorized response.
     */
    protected function sendUnauthorizedResponse(string $message = 'Unauthorized'): JsonResponse
    {
        return $this->sendError($message, [], HttpResponse::HTTP_UNAUTHORIZED);
    }

    /**
     * Send a forbidden response.
     */
    protected function sendForbiddenResponse(string $message = 'Forbidden'): JsonResponse
    {
        return $this->sendError($message, [], HttpResponse::HTTP_FORBIDDEN);
    }
}