<?php
namespace App\Http\Controllers\API\v1\Rest;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\EmailSettingService\EmailSendService;
use App\Helpers\ResponseError;
use Illuminate\Support\Facades\Log;

class ContactController extends Controller
{
    public function send(Request $request)
    {
        $service = new EmailSendService();

        $validated = $request->validate([
            'firstName' => 'required|string|max:100',
            'lastName'  => 'required|string|max:100',
            'email'     => 'required|email',
            'phone'     => 'required|string|max:20',
            'message'   => 'required|string|max:1000',
        ]);

        $subject = "New Contact Form Submission – {$validated['firstName']} {$validated['lastName']}";
        $html = "
            <h2>Contact Form Submission</h2>
            <p><strong>Name:</strong> {$validated['firstName']} {$validated['lastName']}</p>
            <p><strong>Email:</strong> {$validated['email']}</p>
            <p><strong>Phone:</strong> {$validated['phone']}</p>
            <p><strong>Message:</strong></p>
            <p>{$validated['message']}</p>
        ";

        $plain = "Name: {$validated['firstName']} {$validated['lastName']}\n"
            . "Email: {$validated['email']}\n"
            . "Phone: {$validated['phone']}\n\n"
            . "Message:\n{$validated['message']}";

        // ✅ 1. Send to admin
        $adminResult = $service->sendContactMessage($validated);


        if (!data_get($adminResult, 'status')) {
            return response()->json([
                'status' => false,
                'code'   => ResponseError::ERROR_504,
                'message'=> 'Failed to send contact email to admin',
                'debug'  => $adminResult,
            ], 500);
        }

        $service->sendAcknowledgementMessage($validated);

        return response()->json([
            'status' => true,
            'message'=> 'Message sent successfully! A confirmation email has been sent.',
        ]);
    }

}
