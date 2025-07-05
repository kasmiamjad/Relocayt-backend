<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\EmailSettingService\EmailSendService;

class BookingEmailController extends Controller
{
    public function __construct(protected EmailSendService $emailService) {}

    public function send(Request $request)
    {
        return response()->json(['message' => 'Controller reached.']);
    }

}
