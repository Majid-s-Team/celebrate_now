<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Pagination\LengthAwarePaginator;


class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    protected function sendResponse($message, $data = [], $status = 200)
    {
        if ($data instanceof LengthAwarePaginator) {
            return response()->json([
                'message' => $message,
                'data' => $data->items(), 
                'pagination' => [
                    'current_page' => $data->currentPage(),
                    'last_page' => $data->lastPage(),
                    'per_page' => $data->perPage(),
                    'total' => $data->total(),
                    'has_more_pages' => $data->hasMorePages(),
                ],
                'status_code' => $status
            ], $status);
        }

        return response()->json([
            'message' => $message,
            'data' => $data,
            'status_code' => $status
        ], $status);
    }
    // protected function sendResponse($message, $data = [], $status = 200)
    // {
    //     return response()->json([
    //         'message' => $message,
    //         'data' => $data,
    //         'status_code' => $status
    //     ], $status);
    // }
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
