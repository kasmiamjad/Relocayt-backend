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


    public function sendEmailPasswordReset_old(User $user, $str): array
    {
        $emailTemplate = EmailTemplate::where('type', EmailTemplate::TYPE_VERIFY)->first();

        $mail = $this->emailBaseAuth($emailTemplate?->emailSetting, $user);

        try {

            $mail->Subject  = data_get($emailTemplate, 'subject', 'Reset password');

            $default        = 'Please enter code for reset your password: $verify_code';
            $body           = data_get($emailTemplate, 'body', $default);
            $altBody        = data_get($emailTemplate, 'alt_body', $default);

            $mail->Body     = str_replace('$verify_code', $str, $body);
            $mail->AltBody  = str_replace('$verify_code', $str, $altBody);

            if (!empty(data_get($emailTemplate, 'galleries'))) {
                foreach ($emailTemplate->galleries as $gallery) {
                    /** @var Gallery $gallery */
                    try {
                        $mail->addAttachment(request()->getHttpHost() . '/storage/' . $gallery->path);
                    } catch (Throwable) {
                        Log::error($mail->ErrorInfo);
                    }
                }
            }

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
        // pick the setting you actually want; `first()` is safest
        $emailSetting = EmailSetting::find(3);
        $mail = $this->emailBaseAuth($emailSetting, $user);

        try {
            // Optional debug (set to 0 in prod)
            // $mail->SMTPDebug = 2;
            // $mail->Debugoutput = function($str, $level) { \Log::debug("SMTP[$level]: $str"); };

            $primary   = (int)($data['primaryCount']   ?? 0);
            $secondary = (int)($data['secondaryCount'] ?? 0);

            $userName = e($user->name_or_email ?? trim(($user->firstname ?? '').' '.($user->lastname ?? '')) ?: 'User');

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

            $mail->Subject = 'Verification Documents Received — Pending Review';
            $mail->Body    = $this->wrapEmailLayout($html);
            $mail->AltBody = strip_tags($html);
            $mail->isHTML(true);

            $ok = $mail->send();
            Log::error('Verification email success', ['message' => $ok]);
            return [
                'status'  => (bool)$ok,
                'code'    => $ok ? ResponseError::NO_ERROR : ResponseError::ERROR_504,
                'message' => $ok ? 'sent' : $mail->ErrorInfo,
            ];
        } catch (\Throwable $e) {
            \Log::error('Verification email error', ['message' => $e->getMessage()]);
            return [
                'status'  => false,
                'code'    => ResponseError::ERROR_504,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function sendListingCreated(User $user, array $data): array
    {
        // Pick your configured row; `first()` is safer unless you use a specific ID.
        $emailSetting = EmailSetting::find(3);
        $mail = $this->emailBaseAuth($emailSetting, $user);

        try {
            // Optional debug while testing:
            // $mail->SMTPDebug = 2;
            // $mail->Debugoutput = function($str, $level) { \Log::debug("SMTP[$level] $str"); };

            $shop     = $data['shop'] ?? [];
            $property = $data['property'] ?? [];
            $service  = $data['service'] ?? [];
            $sm       = $data['service_master'] ?? [];

            $userName = e($user->name_or_email ?? trim(($user->firstname ?? '').' '.($user->lastname ?? '')) ?: 'Host');

            // If property is inactive by default, reflect that tone
            $isPending = strtolower((string)($property['status'] ?? 'inactive')) !== 'active';

            $header = $isPending
                ? "<h2 style='margin:0 0 12px;'>Your listing was created — pending activation</h2>"
                : "<h2 style='margin:0 0 12px;'>Your listing is live</h2>";

            $shopHtml = "
                <p style='margin:6px 0;'><strong>Shop:</strong> ".e($shop['title'] ?? '')."</p>
                <p style='margin:6px 0;'><strong>Slug:</strong> ".e($shop['slug'] ?? '')."</p>
                <p style='margin:6px 0;'><strong>Address:</strong> ".e($shop['address'] ?? '')."</p>
            ";

            $propertyHtml = "
                <p style='margin:6px 0;'><strong>Property:</strong> ".e($property['title'] ?? '')."</p>
                <p style='margin:6px 0;'><strong>Slug:</strong> ".e($property['slug'] ?? '')."</p>
                <p style='margin:6px 0;'><strong>Status:</strong> ".e($property['status'] ?? '')."</p>
                <p style='margin:6px 0;'><strong>Location:</strong> ".e(($property['city'] ?? '').', '.($property['country'] ?? ''))."</p>
                <p style='margin:6px 0;'><strong>Price per night:</strong> ".e(($property['currency'] ?? '')).' '.e((string)($property['price_per_night'] ?? ''))."</p>
                <p style='margin:6px 0;'><strong>Min/Max nights:</strong> ".e((string)($property['min_nights'] ?? '')).' / '.e((string)($property['max_nights'] ?? ''))."</p>
                <p style='margin:6px 0;'><strong>Check-in/out:</strong> ".e((string)($property['check_in_time'] ?? '')).' / '.e((string)($property['check_out_time'] ?? ''))."</p>
            ";

            $serviceHtml = "
                <p style='margin:6px 0;'><strong>Service ID:</strong> ".e((string)($service['id'] ?? ''))."</p>
                <p style='margin:6px 0;'><strong>Status/Type:</strong> ".e((string)($service['status'] ?? ''))." / ".e((string)($service['type'] ?? ''))."</p>
                <p style='margin:6px 0;'><strong>Base price:</strong> ".e((string)($service['price'] ?? ''))."</p>
            ";

            $smHtml = "
                <p style='margin:6px 0;'><strong>Service Master ID:</strong> ".e((string)($sm['id'] ?? ''))."</p>
                <p style='margin:6px 0;'><strong>Price / Interval / Pause:</strong> "
                .e((string)($sm['price'] ?? ''))." / ".e((string)($sm['interval'] ?? ''))." / ".e((string)($sm['pause'] ?? ''))."</p>
                <p style='margin:6px 0;'><strong>Type:</strong> ".e((string)($sm['type'] ?? ''))."</p>
            ";

            $nextHtml = $isPending
                ? "
                    <div style='margin:18px 0; padding:14px 16px; background:#f8fafc; border:1px solid #e5e7eb; border-radius:8px;'>
                    <p style='margin:0 0 8px;'><strong>What happens next?</strong></p>
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
                <p style='margin:0 0 18px;'>Dear {$userName},</p>
                <p style='margin:0 18px 18px 0;'>Your listing was created with the following details:</p>

                <div style='margin:18px 0; padding:14px 16px; border:1px solid #e5e7eb; border-radius:8px;'>
                <h3 style='margin:0 0 8px;'>Shop</h3>
                {$shopHtml}
                </div>

                <div style='margin:18px 0; padding:14px 16px; border:1px solid #e5e7eb; border-radius:8px;'>
                <h3 style='margin:0 0 8px;'>Property</h3>
                {$propertyHtml}
                </div>

                <div style='margin:18px 0; padding:14px 16px; border:1px solid #e5e7eb; border-radius:8px;'>
                <h3 style='margin:0 0 8px;'>Service</h3>
                {$serviceHtml}
                </div>

                <div style='margin:18px 0; padding:14px 16px; border:1px solid #e5e7eb; border-radius:8px;'>
                <h3 style='margin:0 0 8px;'>Service Master</h3>
                {$smHtml}
                </div>

                {$nextHtml}

                <p style='margin:18px 0 0;'>Have questions? Just reply to this email.</p>
            ";

            $subject = $isPending
                ? 'Your listing was created — pending activation'
                : 'Your listing is live';

            $mail->Subject = $subject;
            $mail->Body    = $this->wrapEmailLayout($html);
            $mail->AltBody = strip_tags($html);
            $mail->isHTML(true);

            // Optional: notify internal team
            // $mail->addBCC('hosts@relocayt.ca', 'Host Ops');

            $ok = $mail->send();
            Log::error('Accomodation email success', ['message' => $ok]);
            return [
                'status'  => (bool)$ok,
                'code'    => $ok ? ResponseError::NO_ERROR : ResponseError::ERROR_504,
                'message' => $ok ? 'sent' : $mail->ErrorInfo,
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

}
