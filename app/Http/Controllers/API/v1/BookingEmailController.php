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
        $data = $request->validate([
            'serviceMasterId' => 'required|integer',
            'shopSlug' => 'nullable|string',
            'totalPrice' => 'required|numeric',
            'nightCount' => 'required|integer',
            'dateRange.from' => 'required|date',
            'dateRange.to' => 'required|date',
        ]);

        $result = $this->emailService->sendBookingInterestEmail($data);

        if (!$result['status']) {
            return response()->json(['message' => $result['message']], 500);
        }

        return response()->json(['message' => 'Booking email sent successfully.']);
    }
}
