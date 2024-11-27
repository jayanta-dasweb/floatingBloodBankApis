<?php
if (!function_exists('apiResponse')) {
    /**
     * Format API response with automatic status code assignment.
     *
     * @param mixed $result Data to include in the response result
     * @param string $message Response message
     * @param int|null $code Optional HTTP status code (default auto-assigned)
     * @param array|null $errors Array of error objects (optional)
     * @param string|null $errorType Type of error (e.g., 'validation', 'server')
     * @return array Formatted API response
     */
    function apiResponse($result = [], string $message = 'OK', ?int $code = null, array $errors = null, string $errorType = null): array
    {
        // Automatically assign code based on the presence of errors and result
        if (is_null($code)) {
            $code = $errors ? 422 : ($result ? 200 : 404);
        }

        $response = [
            'meta' => [
                'code' => $code,
                'success' => $code >= 200 && $code < 300,
                'message' => $message,
            ],
            'result' => $result,
        ];

        // If errors are present, include them in the meta and nullify the result
        if ($errors) {
            $response['meta']['errors'] = array_map(function ($error) use ($errorType) {
                return [
                    'error_type' => $errorType,
                    'error_message' => $error,
                ];
            }, $errors);

            $response['result'] = null; // Result should be null when there are errors
        }

        return $response;
    }
}