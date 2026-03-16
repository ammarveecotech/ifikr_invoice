<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;

class ApiResponse extends JsonResponse
{
    public function __construct($data = [], $message = '', $success = true, $statusCode = 200)
    {
        $response = [
            'success' => $success,
            'message' => $message,
            'data' => $data,
        ];

        parent::__construct($response, $statusCode);
    }

    /**
     * Create a success response.
     */
    public static function success($data = [], $message = 'Success', $statusCode = 200): self
    {
        return new self($data, $message, true, $statusCode);
    }

    /**
     * Create an error response.
     */
    public static function error($message = 'Error', $data = [], $statusCode = 400): self
    {
        return new self($data, $message, false, $statusCode);
    }

    /**
     * Create a not found response.
     */
    public static function notFound($message = 'Resource not found', $data = []): self
    {
        return new self($data, $message, false, 404);
    }

    /**
     * Create an unauthorized response.
     */
    public static function unauthorized($message = 'Unauthorized', $data = []): self
    {
        return new self($data, $message, false, 401);
    }

    /**
     * Create a validation error response.
     */
    public static function validationError($message = 'Validation failed', $data = []): self
    {
        return new self($data, $message, false, 422);
    }

    /**
     * Create a server error response.
     */
    public static function serverError($message = 'Internal server error', $data = []): self
    {
        return new self($data, $message, false, 500);
    }
}
