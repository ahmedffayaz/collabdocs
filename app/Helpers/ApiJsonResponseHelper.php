<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Response;

class ApiJsonResponseHelper
{
    public static function apiJsonResponse($responseData = [], $code = 200, $message = '', $status = 'true')
    {
        return Response::json([
            'message' => $message,
            'status' => (string) $status,
            'data' => $responseData,
        ], $code);
    }

    public static function successResponse($responseData = [], $message = '')
    {
        return self::apiJsonResponse($responseData, config('finwiz.status_codes.success'), $message, 'true');
    }

    public static function errorResponse($message = '')
    {
        return self::apiJsonResponse([], config('finwiz.status_codes.normal_error'), $message, 'false');
    }

    public static function apiValidationFailResponse($validator)
    {
        return self::apiJsonResponse(
            $validator->errors()->toArray(),
            config('finwiz.status_codes.form_validation'),
            'Validation Error',
            'false',
        );
    }
}
