<?php
declare(strict_types=1);

namespace App\Http\Controllers\API\v1\Auth;

use App\Events\Mails\SendEmailVerification;
use App\Helpers\ResponseError;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AfterVerifyRequest;
use App\Http\Requests\Auth\PhoneVerifyRequest;
use App\Http\Requests\Auth\ReSendVerifyRequest;
use App\Http\Resources\UserResource;
use App\Models\Notification;
use App\Models\NotificationUser;
use App\Models\PushNotification;
use App\Models\User;
use App\Services\AuthService\AuthByMobilePhone;
use App\Services\UserServices\UserService;
use App\Services\UserServices\UserWalletService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Kreait\Laravel\Firebase\Facades\Firebase;
use App\Models\Shop;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Throwable;

class VerifyAuthController extends Controller
{
    use ApiResponse, \App\Traits\Notification;

    public function verifyPhone(PhoneVerifyRequest $request): JsonResponse
    {
        try {
            if (!config('app.is_demo') && $request->input('type') === 'firebase') {
                Firebase::auth()->verifyIdToken($request->input('id'));
            }
        } catch (Throwable $e) {
            $this->error($e);
            return $this->onErrorResponse([
                'code'    => ResponseError::ERROR_107,
                'message' => $e->getMessage()
            ]);
        }

        return (new AuthByMobilePhone)->confirmOPTCode($request->all());
    }

    public function resendVerify(ReSendVerifyRequest $request): JsonResponse
    {
        $user = User::where('email', $request->input('email'))
            ->whereNotNull('verify_token')
            ->whereNull('email_verified_at')
            ->first();

        if (!$user) {
            return $this->onErrorResponse(['code' => ResponseError::ERROR_404]);
        }

        event((new SendEmailVerification($user)));

        return $this->successResponse(__('errors.' . ResponseError::NO_ERROR, locale: $this->language));
    }

    public function verifyEmail(?string $verifyToken): JsonResponse
    {
        $user = User::where('verify_token', $verifyToken)
            ->whereNull('email_verified_at')
            ->first();

        if (empty($user)) {
            return $this->onErrorResponse(['code' => ResponseError::ERROR_404]);
        }

        try {
            $user->update([
                'email_verified_at' => now(),
            ]);

            $token = $user->createToken('api_token')->plainTextToken;

            return $this->successResponse(__('errors.' . ResponseError::NO_ERROR, locale: $this->language), [
                'token'         => $token,
                'access_token'  => $token,
                'token_type'    => 'Bearer',
                'email'         => $user->email
            ]);
        } catch (Throwable) {
            return $this->onErrorResponse(['code' => ResponseError::ERROR_501]);
        }
    }

    public function afterVerifyEmail(AfterVerifyRequest $request): JsonResponse
    {
        $user = User::where('email', $request->input('email'))->first();

        if (empty($user)) {
            return $this->onErrorResponse(['code' => ResponseError::ERROR_404]);
        }

        DB::beginTransaction();

        try {
            // Update user basics
            $user->update([
                'firstname' => $request->input('firstname', $user->email),
                'lastname'  => $request->input('lastname', $user->lastname),
                'referral'  => $request->input('referral', $user->referral),
                'gender'    => $request->input('gender','male'),
                'password'  => bcrypt($request->input('password', 'password')),
            ]);

            // Referral notification (unchanged)
            $referral = User::where('my_referral', $request->input('referral', $user->referral))->first();

            if (!empty($referral) && !empty($referral->firebase_token)) {
                /** @var NotificationUser $notification */
                $notification = $referral->notifications?->where('type', Notification::PUSH)?->first();

                if ($notification?->notification?->active) {
                    $this->sendNotification(
                        $referral,
                        is_array($referral->firebase_token) ? $referral->firebase_token : [$referral->firebase_token],
                        'Congratulations!',
                        "By your referral registered new user. $user->name_or_email",
                        [
                            'id'   => $referral->id,
                            'type' => PushNotification::NEW_USER_BY_REFERRAL
                        ],
                        [$referral->id],
                    );
                }
            }

            (new UserService)->notificationSync($user);

            $user->emailSubscription()->updateOrCreate(
                ['user_id' => $user->id],
                ['active'  => true]
            );

            if (empty($user->wallet?->uuid)) {
                $user = (new UserWalletService)->create($user);
            }

            // === Create a Shop for this user, if not already present ===
            $hasShop = Shop::where('user_id', $user->id)->exists();

            if (!$hasShop) {
                // Build a unique slug
                $base = trim(($user->firstname ? ($user->firstname.' '.$user->lastname) : $user->email));
                $baseSlug = Str::slug($base) ?: 'user-'.$user->id;
                $slug = $baseSlug;

                $i = 1;
                while (Shop::where('slug', $slug)->exists()) {
                    $slug = $baseSlug.'-'.(++$i);
                }

                /** @var Shop $shop */
                $shop = Shop::create([
                    'user_id'       => $user->id,
                    'uuid'          => (string) Str::uuid(),
                    'slug'          => $slug,
                    'status'        => 'approved',
                    'type'          => 1,      // adjust if your enum/string differs
                    'delivery_type' => 1,
                    'open'          => true,
                    'visibility'    => true,
                    'verify'        => true,
                    'min_amount'    => 0,
                ]);

                // Optional: create a basic translation if your app uses it
                if (method_exists($shop, 'translation')) {
                    $shop->translation()->create([
                        'locale'      => $this->language ?? 'en',
                        'title'       => ($user->firstname ?: 'My').' Shop',
                        'description' => null,
                        'address'     => null,
                    ]);
                }

                // Optional: store on users.shop_id if the column exists
                if (Schema::hasColumn('users', 'shop_id')) {
                    $user->forceFill(['shop_id' => $shop->id])->save();
                }

                if ($shop->status === 'approved' && method_exists($user, 'hasRole') && ! $user->hasRole('admin')) {
                    if (method_exists($user, 'assignRole')) {
                        // Use assignRole to keep existing roles (recommended)
                        $user->assignRole('seller');

                        // If you want to REPLACE roles instead, use:
                        // $user->syncRoles('seller');
                    }
                }
            }

            DB::commit();

        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);
            return $this->onErrorResponse([
                'code'    => ResponseError::ERROR_501,
                'message' => 'Registration failed',
            ]);
        }

        $token = $user->createToken('api_token')->plainTextToken;

        return $this->successResponse(__('errors.' . ResponseError::NO_ERROR, locale: $this->language), [
            'token'         => $token,
            'access_token'  => $token,
            'token_type'    => 'Bearer',
            'user'          => UserResource::make($user->fresh()),
        ]);
    }


}
