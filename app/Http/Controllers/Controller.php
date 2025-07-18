<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    protected function sendResponse($message, $data = null, $status = 200)
    {
        return response()->json([
            'message' => $message,
            'data' => $data,
            'status_code' => $status
        ], $status);
    }
    protected function sendError($message, $errors = [], $status = 400)
    {
        return response()->json([
            'message' => $message,
            'errors' => $errors,
            'status_code' => $status,
            'data' => []
        ], $status);
    }
    

}
