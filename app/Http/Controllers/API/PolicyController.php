<?php


namespace App\Http\Controllers\API;
use App\Models\PostReport;
use App\Models\ReportReason;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Policy;

class PolicyController extends Controller
{
    public function index(Request $request){
        $request->validate([
        'type' => 'required|string|in:terms,privacy',
    ]);
        $user = auth()->user();

    $policy = Policy::where('type', $request->type)->first();
    if (!$policy) {
        return $this->sendError('Policy not found.', [], 404);
    }
    if($request->type == 'terms'){
    return $this->sendResponse('Terms & Conditions fetched Successfully ', $policy);
    }
    else if($request->type == 'privacy'){
    return $this->sendResponse('Privacy Conditions fetched Successfully ', $policy);

    }
}
}
