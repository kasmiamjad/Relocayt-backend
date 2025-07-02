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
                    $mail->addAttachment(request()->getHttpHost() . '/storage/' . $gallery->path);
                } catch (Throwable) {
                    Log::error($mail->ErrorInfo);
                }
            }

            $mail->send();

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

    public function sendVerify(User $user): array
    {
        $emailTemplate = EmailTemplate::where('type', EmailTemplate::TYPE_VERIFY)->first();

        $mail = $this->emailBaseAuth($emailTemplate?->emailSetting, $user);
        try {

            $htmlTemplate = <<<HTML
                        <!DOCTYPE html>
                        <html>
                        <head>
                        <meta charset="UTF-8">
                        <title>Confirm Your Email</title>
                        </head>
                        <body style="margin:0; padding:0; background-color:#f4faff; font-family:Arial, sans-serif;">
                        <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4faff; padding: 40px 0;">
                            <tr>
                            <td align="center">
                                <table width="100%" max-width="600" cellpadding="0" cellspacing="0" style="background-color:#ffffff; border-radius:8px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,0.05); max-width:600px;">
                                <tr>
                                    <td align="center" style="padding: 40px 20px 10px;">
                                    <img src="https://relocayt-images.s3.us-east-1.amazonaws.com/public/images/relocayt-logo.svg" alt="Relocayt Logo" width="150" style="display:block;">
                                    </td>
                                </tr>
                                <tr>
                                    <td align="center" style="padding: 20px 30px 10px;">
                                    <h2 style="margin: 0; color: #000000;">Confirm your email</h2>
                                    </td>
                                </tr>
                                <tr>
                                    <td align="center" style="padding: 0 30px 30px;">
                                    <p style="color:#444444; font-size:16px; line-height:24px; margin:0;">
                                        Thanks for signing up with Relocayt. Please confirm your email address to activate your account.
                                    </p>
                                    </td>
                                </tr>
                                <tr>
                                    <td align="center" style="padding: 10px 30px 40px;">
                                    <a href="{{ACTIVATION_LINK}}" style="display:inline-block; padding: 12px 24px; background-color:#38bdf8; color:#ffffff; text-decoration:none; font-weight:bold; border-radius:6px;">
                                        Activate Account
                                    </a>
                                    </td>
                                </tr>
                                <tr>
                                    <td align="center" style="padding: 20px 30px; font-size:12px; color:#999999;">
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

            $activationLink = url('auth/activate/' . $user->verify_token);

            $mail->Subject = "Confirm Your Email";
            $mail->isHTML(true); // Important to render HTML

            $default        = 'Please enter code for verify your email: verify code new ';
            $mail->Body = str_replace('{{ACTIVATION_LINK}}', $activationLink, $htmlTemplate);
            $mail->AltBody = "Activate your account by clicking this link: " . $activationLink;

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

    public function sendEmailPasswordReset(User $user, $str): array
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

            $mail->setFrom("info@relocayt.ca", $emailSetting->from_site);
            $mail->addAddress($user->email, $user->name_or_email);

        } catch (Throwable $e) {
            Log::error($mail->ErrorInfo);
            $this->error($e);
        }
        return $mail;
    }
}
