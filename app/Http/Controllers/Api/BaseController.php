<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller as LaravelController;

class BaseController extends LaravelController
{
    /**
     * Success response
     */
    protected function success($data = null, string $message = 'Success', int $statusCode = 200): JsonResponse
    {
        return ResponseHelper::success($data, $message, $statusCode);
    }

    /**
     * Error response
     */
    protected function error(string $message = 'Error', int $statusCode = 400, $errors = null): JsonResponse
    {
        return ResponseHelper::error($message, $statusCode, $errors);
    }

    /**
     * Unauthorized response
     */
    protected function unauthorized(string $message = 'Unauthorized'): JsonResponse
    {
        return ResponseHelper::unauthorized($message);
    }

    /**
     * Forbidden response
     */
    protected function forbidden(string $message = 'Forbidden'): JsonResponse
    {
        return ResponseHelper::forbidden($message);
    }

    /**
     * Not found response
     */
    protected function notFound(string $message = 'Resource not found'): JsonResponse
    {
        return ResponseHelper::notFound($message);
    }

    /**
     * Validation error response
     */
    protected function validationError($errors, string $message = 'Validation failed'): JsonResponse
    {
        return ResponseHelper::validationError($errors, $message);
    }
}
