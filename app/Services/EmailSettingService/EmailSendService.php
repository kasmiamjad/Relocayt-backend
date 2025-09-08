<?php
declare(strict_types=1);

namespace App\Services\EmailSettingService;

use App\Helpers\ResponseError;
use App\Models\EmailSetting;
use App\Models\EmailSubscription;
use App\Models\EmailTemplate;
use App\Models\Gallery;
use App\Models\Order;
use App\Models\Settings;
use App\Models\Translation;
use App\Models\User;
use App\Services\CoreService;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use Exception;
use Illuminate\Support\Facades\Cache;
use Log;
use PHPMailer\PHPMailer\PHPMailer;
use Storage;
use Throwable;
use View;
use SendGrid;
use SendGrid\Mail\Mail;

class EmailSendService extends CoreService
{
    /**
     * @return string
     */
    
    protected function getModelClass(): string
    {
        return EmailSetting::class;
    }
    
    public function sendSubscriptions(EmailTemplate $emailTemplate): array
    {
        $mail = new PHPMailer(true);

        try {
            $emailSetting = $emailTemplate->emailSetting;

            $mail->CharSet = 'UTF-8';

            // Настройки SMTP
            /*$mail->isSMTP();
            $mail->SMTPAuth     = $emailSetting->smtp_auth;
            $mail->SMTPDebug    = $emailSetting->smtp_debug;*/
            $mail->isSMTP();
            $mail->SMTPAuth     = $emailSetting->smtp_auth;
            $mail->Host         = $emailSetting->host;
            $mail->Port         = $emailSetting->port;
            $mail->Username     = $emailSetting->from_to;// 'AKIASE53DGGRKWSRQQ5N';//
            $mail->Password     = $emailSetting->password; //'BOQWvY4kxD5co84AP5dpO2CBGkMEO72mbyHfIRF9NYV4';//
            $mail->SMTPSecure   = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->SMTPOptions  = $emailSetting->ssl ?: [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ];
            // От кого
            $mail->setFrom($emailSetting->from_to, $emailSetting->from_site);

            // Кому

            foreach (EmailSubscription::where('active', true)->get() as $subscribe) {

                /** @var EmailSubscription $subscribe */
                $email = data_get($subscribe->user, 'email');

                if (!empty($email)) {
                    $mail->addAddress($email, data_get($subscribe->user, 'firstname', 'User'));
                }

            }

            // Тема письма
            $mail->Subject = $emailTemplate->subject;

            // Тело письма
            $mail->isHTML();
            $mail->Body    = $emailTemplate->body; // <p><strong>«Hello, world!» </strong></p>
            $mail->AltBody = $emailTemplate->alt_body; // Hello, world!

            // Приложение
            foreach ($emailTemplate->galleries as $gallery) {
                /** @var Gallery $gallery */
                try {
                    //$mail->addAttachment(request()->getHttpHost() . '/storage/' . $gallery->path);
                } catch (Throwable) {
                    Log::error($mail->ErrorInfo);
                }
            }

            // $mail->send();

            return [
                'status' => true,
                'code' => ResponseError::NO_ERROR,
            ];

        } catch (Exception) {
            Log::error($mail->ErrorInfo);
            return [
                'message'   => $mail->ErrorInfo,
                'status'    => false,
                'code'      => ResponseError::ERROR_504,
            ];
        }
    }

    public function send($to, $subject, $body, $attachments = [])
    {
        try {
            $email = new Mail();
            $email->setFrom(config('mail.from.address'), config('mail.from.name'));
            $email->setSubject($subject);
            $email->addTo($to);
            $email->addContent("text/html", $body);

            // Optional: Add attachments
            foreach ($attachments as $filePath) {
                if (file_exists($filePath)) {
                    $email->addAttachment(
                        base64_encode(file_get_contents($filePath)),
                        mime_content_type($filePath),
                        basename($filePath),
                        "attachment"
                    );
                }
            }

            $sendgrid = new SendGrid(env('SENDGRID_API_KEY'));
            $response = $sendgrid->send($email);

            return [
                'status' => $response->statusCode(),
                'body'   => $response->body(),
                'headers'=> $response->headers()
            ];

        } catch (Exception $e) {
            return [
                'status' => 500,
                'error'  => $e->getMessage()
            ];
        }
    }

    public function sendVerify(User $user): array
    {
        $emailTemplate = EmailTemplate::where('type', EmailTemplate::TYPE_VERIFY)->first();

        $mail = $this->emailBaseAuth($emailTemplate?->emailSetting, $user);
        try {

            $verifyCode = $user->verify_token;

            $defaultHtml = "
            <h2 style=\"text-align:center;\">Confirm your email</h2>
            <p style=\"text-align:center;\">Your verification code is:</p>
            <div style=\"text-align:center;\">
                <span style=\"display:inline-block; padding:10px 20px; background:#38bdf8; color:#fff; font-size:18px; font-weight:bold; border-radius:6px;\">
                $verifyCode
                </span>
            </div>
            ";
            $defaultAlt = "Confirm your email\n\nYour verification code is: $verifyCode";

            $bodyTemplate = data_get($emailTemplate, 'body', $defaultHtml);
            $altTemplate  = data_get($emailTemplate, 'alt_body', $defaultAlt);

            $bodyWithCode = str_replace('$verify_code', $verifyCode, $bodyTemplate);
            $altWithCode  = str_replace('$verify_code', $verifyCode, $altTemplate);

            $mail->Subject  = $verifyCode . " - Your Email Verification Code";
            $mail->Body     = $this->wrapEmailLayout($bodyWithCode);
            $mail->AltBody  = $altWithCode;
            $mail->isHTML(true);
            $mail->send();

            return [
                'status' => true,
                'code' => ResponseError::NO_ERROR,
            ];
        } catch (Exception $e) {
            Log::error('ErrorInfo', [
                $mail->ErrorInfo
            ]);
            $this->error($e);
            return [
                'message'   => $mail->ErrorInfo,
                'status'    => false,
                'code'      => ResponseError::ERROR_504,
            ];
        }
    }

    public function wrapEmailLayout($innerHtml)
    {
            return <<<HTML
            <!DOCTYPE html>
            <html>
            <head>
            <meta charset="UTF-8">
            <title>Relocayt Email</title>
            </head>
            <body style="margin:0; padding:0; background-color:#f4faff; font-family:Arial, sans-serif;">
            <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4faff; padding: 40px 0;">
                <tr>
                <td align="center">
                    <!-- Outer Container -->
                    <table cellpadding="0" cellspacing="0" style="width:100%; max-width:600px; background-color:#ffffff; border-radius:8px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,0.05);">
                    
                    <!-- Logo -->
                    <tr>
                        <td align="center" style="padding: 40px 40px 60px;">
                        <img src="https://relocayt-images.s3.amazonaws.com/public/images/relocayt-light-text.png" alt="Relocayt Logo" width="150" style="display:block;">
                        </td>
                    </tr>

                    <!-- Dynamic Body -->
                    <tr>
                        <td style="padding: 0 40px 40px;">
                        $innerHtml
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td align="center" style="padding: 20px 40px; font-size:12px; color:#999999;">
                        If you didn’t sign up for Relocayt, you can safely ignore this email.
                        </td>
                    </tr>

                    </table>
                </td>
                </tr>
            </table>
            </body>
            </html>
            HTML;
    }

    public function sendEmailPasswordReset(User $user, string $resetCode): array
    {
        try {
            $emailTemplate = EmailTemplate::where('type', EmailTemplate::TYPE_RESET_PASSWORD)->first();

            // Default HTML body for password reset
            $defaultHtml = <<<HTML
            <h2 style="text-align:center; margin-top: 0;">Reset your password</h2>
            <p style="text-align:center; font-size:16px; color:#444; margin: 20px 0;">
                We received a request to reset your password. Use the code below to proceed:
            </p>
            <div style="text-align:center; margin: 30px 0;">
                <span style="display:inline-block; padding:12px 28px; background:#38bdf8; color:#ffffff; font-size:20px; font-weight:bold; border-radius:6px;">
                    {$resetCode}
                </span>
            </div>
            HTML;

            $defaultAlt = "Reset your password\n\nUse this code to reset your password: {$resetCode}";

            $bodyTemplate = data_get($emailTemplate, 'body', $defaultHtml);
            $altTemplate  = data_get($emailTemplate, 'alt_body', $defaultAlt);

            $bodyWithCode = str_replace('$verify_code', $resetCode, $bodyTemplate);
            $altWithCode  = str_replace('$verify_code', $resetCode, $altTemplate);

            // ✅ Create SendGrid Mail object
            $email = new \SendGrid\Mail\Mail();
            $email->setFrom("np-reply@relocayt.ca", "Relocayt");
            $email->setSubject("{$resetCode} - Reset Your Password");
            $email->addTo($user->email, $user->firstname ?? 'User');
            $email->addContent("text/plain", $altWithCode);
            $email->addContent("text/html", $this->wrapEmailLayout($bodyWithCode));

            // ✅ Send with API Key
            $sendgrid = new \SendGrid(env('SENDGRID_API_KEY'));
            $response = $sendgrid->send($email);

            if ($response->statusCode() >= 200 && $response->statusCode() < 300) {
                return [
                    'status' => true,
                    'code'   => ResponseError::NO_ERROR,
                ];
            }

            Log::error('SendGrid failed', ['response' => $response->body()]);
            return [
                'status'  => false,
                'code'    => ResponseError::ERROR_504,
                'message' => 'SendGrid error: ' . $response->body(),
            ];

        } catch (\Exception $e) {
            Log::error('SendGrid exception', ['error' => $e->getMessage()]);
            return [
                'status'  => false,
                'code'    => ResponseError::ERROR_504,
                'message' => $e->getMessage(),
            ];
        }
    }

    protected function sendWithSendGrid(string $toEmail, string $toName, string $subject, string $htmlContent, string $plainContent = ''): array
    {
        try {
            $email = new \SendGrid\Mail\Mail();
            $email->setFrom("no-reply@relocayt.ca", "Relocayt");
            $email->setSubject($subject);
            $email->addTo($toEmail, $toName ?: 'User');
            $email->addContent("text/plain", $plainContent ?: strip_tags($htmlContent));
            $email->addContent("text/html", $this->wrapEmailLayout($htmlContent));

            $sendgrid = new \SendGrid(env('SENDGRID_API_KEY'));
            $response = $sendgrid->send($email);

            if ($response->statusCode() >= 200 && $response->statusCode() < 300) {
                return [
                    'status' => true,
                    'code'   => ResponseError::NO_ERROR,
                ];
            }

            Log::error('SendGrid failed', [
                'statusCode' => $response->statusCode(),
                'body'       => $response->body(),
            ]);

            return [
                'status'  => false,
                'code'    => ResponseError::ERROR_504,
                'message' => 'SendGrid error: ' . $response->body(),
            ];

        } catch (\Exception $e) {
            Log::error('SendGrid exception', ['error' => $e->getMessage()]);
            return [
                'status'  => false,
                'code'    => ResponseError::ERROR_504,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function sendContactMessage(array $data): array
    {
        $subject = "New Contact Form Submission – {$data['firstName']} {$data['lastName']}";
        $html = "
            <h2>Contact Form Submission</h2>
            <p><strong>Name:</strong> {$data['firstName']} {$data['lastName']}</p>
            <p><strong>Email:</strong> {$data['email']}</p>
            <p><strong>Phone:</strong> {$data['phone']}</p>
            <p><strong>Message:</strong></p>
            <p>{$data['message']}</p>
        ";
        $plain = "Name: {$data['firstName']} {$data['lastName']}\n"
            . "Email: {$data['email']}\n"
            . "Phone: {$data['phone']}\n\n"
            . "Message:\n{$data['message']}";

        // ✅ only this class can call protected function
        return $this->sendWithSendGrid(
            "kasmi.amjad@gmail.com",
            "Relocayt Team",
            $subject,
            $html,
            $plain
        );
    }

    public function sendAcknowledgementMessage(array $data): array
    {
        $subject = "Thank you for contacting Relocayt";
        $html = "
            <h2>Hi {$data['firstName']},</h2>
            <p>Thank you for reaching out to <strong>Relocayt</strong>. We have received your message and our team will get back to you soon.</p>
            <p><strong>Your message:</strong></p>
            <blockquote style='border-left:3px solid #38bdf8; margin:10px 0; padding-left:10px; color:#555;'>{$data['message']}</blockquote>
            <p>Best regards,<br>Relocayt Team</p>
        ";
        $plain = "Hi {$data['firstName']},\n\n"
            . "Thank you for contacting Relocayt. We have received your message:\n\n"
            . "{$data['message']}\n\n"
            . "Our team will reply soon.\n\n"
            . "Best regards,\nRelocayt Team";

        return $this->sendWithSendGrid($data['email'], "{$data['firstName']} {$data['lastName']}", $subject, $html, $plain);
    }
    /**
     * @param Order $order
     * @return array
     */
    public function sendOrder(Order $order): array
    {
        Pdf::setOption(['dpi' => 150, 'defaultFont' => 'sans-serif']);

        $titleKey = "order.email.invoice.$order->status.title";
        $title    = Translation::where(['locale' => $this->language, 'key' => $titleKey])->first()?->value ?? $titleKey;
        $logo     = Settings::where('key', 'logo')->first()?->value;
        $fileName = null;

        $pdf = View::make(
            'order-email-invoice',
            [
                'order' => $order,
                'lang'  => $this->language,
                'title' => $title,
                'logo'  => $logo,
            ]
        )->render();

        try {
            $mail           = $this->emailBaseAuth(EmailSetting::first(), $order->user);
            $mail->Subject  = $title;
            $mail->Body     = $pdf;
            $mail->addCustomHeader('MIME-Version', '1.0');
            $mail->addCustomHeader('Content-type', 'text/html;charset=UTF-8');
            $mail->send();

            Storage::delete(storage_path("images/$fileName"));

            return [
                'status' => true,
                'code'   => ResponseError::NO_ERROR,
            ];
        } catch (Exception $e) {
            $this->error($e);
            return [
                'message' => $e->getMessage(), //$mail->ErrorInfo,
                'status'  => false,
                'code'    => ResponseError::ERROR_504,
            ];
        }
    }

    public function sendBookingInterestEmail(array $data): array
    {
        // ✅ Domain restriction
        $allowedOrigins = [
            'https://relocayt.ca',
            'https://admin.relocayt.ca',
            'http://localhost:3000',
            'http://127.0.0.1:3000',
        ];

        $origin = request()->header('Origin') ?? request()->header('Referer') ?? '';

        if (!in_array($origin, $allowedOrigins)) {
            \Log::warning("Blocked booking email from untrusted origin: $origin");
            return [
                'status' => false,
                'message' => 'Unauthorized domain',
                'code' => ResponseError::ERROR_403,
            ];
        }

        // ✅ Rate limit by IP
        $ip = request()->ip();
        $key = "email_limit:$ip";

        $count = cache()->increment($key);
        cache()->put($key, $count, now()->addMinutes(60)); // 1-hour expiration

        if ($count > 5) {
            \Log::warning("Too many booking emails from IP: $ip");
            return [
                'status' => false,
                'message' => 'Too many requests. Try again later.',
                'code' => ResponseError::ERROR_429,
            ];
        }
        try {
            $emailSetting = EmailSetting::find(3);

            if (!$emailSetting) {
                \Log::error('EmailSetting with ID 3 not found.');
                return [
                    'status' => false,
                    'message' => 'Email settings not found.',
                    'code' => ResponseError::ERROR_404,
                ];
            }

            \Log::info('Loaded EmailSetting ID 3:', $emailSetting->toArray());
            $mail = new PHPMailer(true);

            $mail->CharSet = 'UTF-8';
            $mail->isSMTP();
            $mail->SMTPAuth   = $emailSetting->smtp_auth;
            $mail->Host       = $emailSetting->host;
            $mail->Port       = $emailSetting->port;
            $mail->Username   = $emailSetting->from_to;
            $mail->Password   = $emailSetting->password;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->SMTPOptions = $emailSetting->ssl ?: [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true,
                ],
            ];

            $mail->setFrom("no-reply@relocayt.ca", $emailSetting->from_site);
            $mail->addAddress('kasmi.amjad@gmail.com', 'Booking Admin');

            $mail->Subject = 'New Booking Interest';

            $html = "
                <h2>New Booking Interest Submitted</h2>
                <p><strong>Service ID:</strong> {$data['serviceMasterId']}</p>
                <p><strong>Shop Slug:</strong> {$data['shopSlug']}</p>
                <p><strong>Total Price:</strong> $ {$data['totalPrice']}</p>
                <p><strong>Night Count:</strong> {$data['nightCount']}</p>
                <p><strong>Date From:</strong> {$data['dateRange']['from']}</p>
                <p><strong>Date To:</strong> {$data['dateRange']['to']}</p>
            ";

            $mail->Body = $this->wrapEmailLayout($html);
            $mail->AltBody = strip_tags($html);
            $mail->isHTML(true);
            $mail->send();

            return [
                'status' => true,
                'code' => ResponseError::NO_ERROR,
            ];
        } catch (Exception $e) {
            \Log::error('Booking Email Error', ['message' => $e->getMessage()]);
            return [
                'status' => false,
                'message' => $e->getMessage(),
                'code' => ResponseError::ERROR_504,
            ];
        }
    }


    public function emailBaseAuth(?EmailSetting $emailSetting, User $user): PHPMailer
    {

        if (empty($emailSetting)) {
            $emailSetting = EmailSetting::first();
        }
        //Log::debug('User email:', ['email' => $user->email]);
        $mail = new PHPMailer(true);
        $mail->isHTML();
        $mail->CharSet = 'UTF-8';
        /*$mail->isSMTP();
        $mail->SMTPAuth     = $emailSetting->smtp_auth;
        $mail->SMTPDebug    = $emailSetting->smtp_debug;*/
        $mail->isSMTP();
        $mail->SMTPAuth     = $emailSetting->smtp_auth;
        $mail->Host         = $emailSetting->host;
        $mail->Port         = $emailSetting->port;
        $mail->Username     = $emailSetting->from_to;
        $mail->Password     = $emailSetting->password;
        $mail->SMTPSecure   = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->SMTPOptions  = data_get($emailSetting, 'ssl.ssl.verify_peer') ? $emailSetting->ssl : [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ];
        
        // Log::debug('PHPMailer Settings:', [
        //     'SMTPAuth'    => $mail->SMTPAuth,
        //     'Host'        => $mail->Host,
        //     'Port'        => $mail->Port,
        //     'Username'    => $mail->Username,
        //     'Password'    => $mail->Password, // ⚠️ sensitive, remove after debugging
        //     'SMTPSecure'  => $mail->SMTPSecure,
        //     'From'        => $emailSetting->from_to,
        //     'From Name'   => $emailSetting->from_site,
        //     'CharSet'     => $mail->CharSet,
        //     'IsHTML'      => $emailSetting,
        //     'SSL Options' => $mail->SMTPOptions,
        // ]);

        try {

            $mail->setFrom("no-reply@relocayt.ca", $emailSetting->from_site);
            $mail->addAddress($user->email, $user->name_or_email);

        } catch (Throwable $e) {
            Log::error($mail->ErrorInfo);
            $this->error($e);
        }
        return $mail;
    }
    public function sendBookingConfirmationList(array $data, User $user): array
    {
        // Use your preferred email setting row; `first()` is safest
        $emailSetting = EmailSetting::find(3);
        $mail = $this->emailBaseAuth($emailSetting, $user);

        try {
            // Optional debug (set to 0 in prod)
            // $mail->SMTPDebug = 2;
            // $mail->Debugoutput = function($str, $level) { \Log::debug("SMTP [$level]: $str"); };

            // Build bookings HTML
            $bookingsHtml = '';
            foreach ($data['bookings'] as $b) {
                $extrasHtml = '';
                if (!empty($b['extras'])) {
                    $items = array_map(fn($t) => '<li>'.e($t).'</li>', $b['extras']);
                    $extrasHtml = '<p style="margin:12px 0 6px;"><strong>Extras:</strong></p><ul style="margin:8px 0 0 18px;">'
                                . implode('', $items) . '</ul>';
                }

                $currency = e($b['currency'] ?? '');
                $total    = number_format((float)($b['total_price'] ?? 0), 2);

                $bookingsHtml .= '
                    <table width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 28px; border:1px solid #eee; border-radius:8px;">
                    <tr>
                        <td style="padding:16px 20px;">
                        <h3 style="margin:0 0 8px;">Booking #'.e($b['booking_id']).'</h3>
                        <p style="margin:6px 0;"><strong>Service:</strong> '.e($b['service_title']).'</p>
                        '.(!empty($b['master_name']) ? '<p style="margin:6px 0;"><strong>Host:</strong> '.e($b['master_name']).'</p>' : '').'
                        <p style="margin:6px 0;"><strong>Address:</strong> '.e($b['shop_address']).'</p>
                        <p style="margin:6px 0;"><strong>Start:</strong> '.e($b['start_date']).'</p>
                        <p style="margin:6px 0;"><strong>End:</strong> '.e($b['end_date']).'</p>
                        '.$extrasHtml.'
                        <p style="margin:12px 0 0;"><strong>Total:</strong> '.$currency.' '.$total.'</p>
                        '.(!empty($b['payment_tag']) ? '<p style="margin:6px 0 0;"><strong>Payment:</strong> '.e(strtoupper($b['payment_tag'])).'</p>' : '').'
                        '.(!empty($b['status']) ? '<p style="margin:6px 0 0;"><strong>Status:</strong> '.e(ucfirst($b['status'])).'</p>' : '').'
                        </td>
                    </tr>
                    </table>
                ';
            }

            $userName = e($data['user_name'] ?? $user->name_or_email ?? 'Guest');

            $html = "
                <h2 style='margin:0 0 12px;'>Your Booking Request is Pending</h2>
                <p style='margin:0 0 18px;'>Dear {$userName},</p>
                <p style='margin:0 0 18px;'>Thank you for your booking. Below are your booking details:</p>
                {$bookingsHtml}
                <p style='margin:18px 0 0;'>We look forward to hosting you.</p>
            ";

            $firstId = $data['bookings'][0]['booking_id'] ?? '';
            $mail->Subject = 'Booking Request Received – Pending Host Confirmation #'.$firstId;
            $mail->Body    = $this->wrapEmailLayout($html);
            $mail->AltBody = strip_tags($html);
            $mail->isHTML(true);

            $ok = $mail->send();

            return [
                'status'  => (bool)$ok,
                'code'    => $ok ? ResponseError::NO_ERROR : ResponseError::ERROR_504,
                'message' => $ok ? 'sent' : $mail->ErrorInfo,
            ];
        } catch (\Exception $e) {
            \Log::error('Booking confirmation email error', ['message' => $e->getMessage()]);
            return [
                'status'  => false,
                'code'    => ResponseError::ERROR_504,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function sendVerificationSubmitted(User $user, array $data): array
    {
        $primary   = (int)($data['primaryCount']   ?? 0);
        $secondary = (int)($data['secondaryCount'] ?? 0);

        $userName = $user->name_or_email
            ?? trim(($user->firstname ?? '').' '.($user->lastname ?? ''))
            ?: 'User';

        $subject = 'Verification Documents Received — Pending Review';

        $html = "
            <h2 style='margin:0 0 12px;'>Verification Documents Received</h2>
            <p style='margin:0 0 18px;'>Dear {$userName},</p>
            <p style='margin:0 0 18px;'>Thanks for submitting your verification documents. Your request is <strong>pending review</strong>. We’ll notify you once it’s reviewed.</p>

            <div style='margin:18px 0; padding:14px 16px; background:#f8fafc; border:1px solid #e5e7eb; border-radius:8px;'>
                <p style='margin:0;'><strong>Submitted:</strong></p>
                <ul style='margin:8px 0 0 18px;'>
                    <li>Primary documents: {$primary}</li>
                    <li>Secondary documents: {$secondary}</li>
                </ul>
            </div>

            <p style='margin:18px 0 0;'>If we need anything else, we’ll reach out via email.</p>
        ";

        $plain = strip_tags($html);

        return $this->sendWithSendGrid(
            $user->email,
            $userName,
            $subject,
            $html,
            $plain
        );
    }



    public function sendListingCreated(User $user, array $data): array
    {
        try {
            $shop     = $data['shop'] ?? [];
            $property = $data['property'] ?? [];
            $service  = $data['service'] ?? [];
            $sm       = $data['service_master'] ?? [];

            $userName = e($user->name_or_email ?? trim(($user->firstname ?? '').' '.($user->lastname ?? '')) ?: 'Host');

            $isPending = true;
        
            $header = $isPending
                ? "<h2 style='margin:0 0 12px;'>Your listing was created — pending activation</h2>"
                : "<h2 style='margin:0 0 12px;'>Your listing is live</h2>";

            $propertyHtml = "
                <p><strong>Property:</strong> ".e($property['title'] ?? '')."</p>
                <p><strong>Slug:</strong> ".e($property['slug'] ?? '')."</p>
                <p><strong>Status:</strong> ".e($property['status'] ?? '')."</p>
                <p><strong>Location:</strong> ".e(($property['city'] ?? '').', '.($property['country'] ?? ''))."</p>
                <p><strong>Price per night:</strong> ".e(($property['currency'] ?? '')).' '.e((string)($property['price_per_night'] ?? ''))."</p>
                <p><strong>Min/Max nights:</strong> ".e((string)($property['min_nights'] ?? '')).' / '.e((string)($property['max_nights'] ?? ''))."</p>
                <p><strong>Check-in/out:</strong> ".e((string)($property['check_in_time'] ?? '')).' / '.e((string)($property['check_out_time'] ?? ''))."</p>
            ";


            $nextHtml = $isPending
                ? "
                    <div style='margin:18px 0; padding:14px 16px; background:#f8fafc; border:1px solid #e5e7eb; border-radius:8px;'>
                    <p><strong>What happens next?</strong></p>
                    <ul style='margin:0 0 0 18px;'>
                        <li>Our team reviews your listing for quality and policy compliance.</li>
                        <li>We’ll email you once your listing is activated.</li>
                        <li>You can update details anytime from your dashboard.</li>
                    </ul>
                    </div>
                "
                : '';

            $html = "
                {$header}
                <p>Dear {$userName},</p>
                <p>Your listing was created with the following details:</p>

                <div style='margin:18px 0; padding:14px 16px; border:1px solid #e5e7eb; border-radius:8px;'>
                    <h3>Property</h3>
                    {$propertyHtml}
                </div>

                {$nextHtml}

                <p style='margin:18px 0 0;'>Have questions? Just reply to this email.</p>
            ";

            $subject = $isPending
                ? 'Your listing was created — pending activation'
                : 'Your listing is live';

            /** @var Sendgrid_lib $sg */
            return $this->sendWithSendGrid(
                $user->email,
                $userName,
                $subject,
                $html,
                strip_tags($html)
            );


            return [
                'status'  => $response['status'] ?? false,
                'code'    => ($response['status'] ?? false) ? ResponseError::NO_ERROR : ResponseError::ERROR_504,
                'message' => $response['message'] ?? 'unknown',
            ];
        } catch (\Throwable $e) {
            \Log::error('Listing created email error', ['message' => $e->getMessage()]);
            return [
                'status'  => false,
                'code'    => ResponseError::ERROR_504,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function sendServiceCreated(User $user, \App\Models\Service $service): array
    {
        try {
            $subject = "Your PLS was created — pending activation";

            $html = "
                <h2 style='margin:0 0 12px;'>Your service was created — pending activation</h2>
                <p>Dear ".e($user->name ?? 'Host').",</p>
                <p>Your service <strong>".e($service->title)."</strong> has been created successfully with the following details:</p>

                <div style='margin:18px 0; padding:14px 16px; border:1px solid #e5e7eb; border-radius:8px;'>
                <h3>Service Details</h3>
                <p><strong>ID:</strong> {$service->id}</p>
                <p><strong>Category:</strong> ".e($service->service_type)."</p>
                <p><strong>Status:</strong> {$service->status}</p>
                <p><strong>Price:</strong> {$service->price}</p>
                <p><strong>Address:</strong> {$service->address}</p>
                <p><strong>Radius (km):</strong> {$service->radius_km}</p>
                </div>

                <div style='margin:18px 0; padding:14px 16px; background:#f8fafc; border:1px solid #e5e7eb; border-radius:8px;'>
                <p><strong>What happens next?</strong></p>
                <ul style='margin:0 0 0 18px;'>
                    <li>Our team reviews your service for quality and compliance.</li>
                    <li>We’ll notify you once it is activated.</li>
                    <li>You can edit details anytime from your dashboard.</li>
                </ul>
                </div>
            ";

            return $this->sendWithSendGrid(
                $user->email,
                $user->name ?? 'Host',
                $subject,
                $html,
                strip_tags($html)
            );

        } catch (\Throwable $e) {
            \Log::error('Service created email error', ['message' => $e->getMessage()]);
            return [
                'status'  => false,
                'code'    => ResponseError::ERROR_504,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function sendServiceStatusUpdated(User $user, \App\Models\Service $service, string $status): array
    {
        try {
            $subject = "Your service status has been updated";

            $html = "
                <h2 style='margin:0 0 12px;'>Your service status has been updated</h2>
                <p>Dear " . e($user->name ?? 'Host') . ",</p>
                <p>Your service <strong>" . e($service->title) . "</strong> has a new status update:</p>

                <div style='margin:18px 0; padding:14px 16px; border:1px solid #e5e7eb; border-radius:8px;'>
                    <h3>Service Details</h3>
                    <p><strong>ID:</strong> {$service->id}</p>
                    <p><strong>Category:</strong> " . e($service->service_type) . "</p>
                    <p><strong>Status:</strong> {$status}</p>
                    <p><strong>Price:</strong> {$service->price}</p>
                    <p><strong>Address:</strong> {$service->address}</p>
                    <p><strong>Radius (km):</strong> {$service->radius_km}</p>
                </div>

                <div style='margin:18px 0; padding:14px 16px; background:#f8fafc; border:1px solid #e5e7eb; border-radius:8px;'>
                    <p><strong>What does this mean?</strong></p>
                    <ul style='margin:0 0 0 18px;'>
                        <li>If status is <em>pending</em>, our team is reviewing your service.</li>
                        <li>If status is <em>active</em>, your service is now visible to customers.</li>
                        <li>If status is <em>inactive</em>, you can update details anytime from your dashboard.</li>
                    </ul>
                </div>
            ";

            return $this->sendWithSendGrid(
                $user->email,
                $user->name ?? 'Host',
                $subject,
                $html,
                strip_tags($html)
            );

        } catch (\Throwable $e) {
            \Log::error('Service status update email error', [
                'message'    => $e->getMessage(),
                'service_id' => $service->id ?? null,
            ]);

            return [
                'status'  => false,
                'code'    => ResponseError::ERROR_504,
                'message' => $e->getMessage(),
            ];
        }
    }



}
