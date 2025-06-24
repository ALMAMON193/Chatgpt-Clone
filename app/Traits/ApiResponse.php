<?php

namespace App\Traits;
trait ApiResponse
{
    public function sendResponse($result, $message, $token = null, $code = 200): \Illuminate\Http\JsonResponse
    {
        $response = [
            'success' => true,
            'data'    => $result,
            'message' => $message,
        ];
        // If a token is provided, include it in the response
        if ($token) {
            $response['access_token'] = $token;
            $response['token_type'] = 'bearer';
        }
        return response()->json($response, $code);
    }
    public function sendError(string $error, array $errorMessages = [], int $code = 404): \Illuminate\Http\JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $error,
        ];
        if (!empty($errorMessages)) {
            $response['data'] = $errorMessages;
        }
        return response()->json($response, $code);
    }
}


