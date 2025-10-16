<?php

namespace App\Http\Controllers\WEB;

use App\Http\Controllers\Controller;
use App\Models\Policy;

class PolicyWebController extends Controller
{
    public function show($type)
    {
        $policy = Policy::where('type', $type)->first();

        if (!$policy) {
            abort(404, 'Policy not found.');
        }

        return view('policies.index', [
            'policy' => $policy,
            'title' => ucfirst($type) . ' Policy',
        ]);
    }
}
