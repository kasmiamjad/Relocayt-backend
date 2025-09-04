<?php
declare(strict_types=1);

namespace App\Http\Controllers\API\v1\Auth;
namespace App\Services\EmailSettingService;

use App\Helpers\ResponseError;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgetPasswordBeforeRequest;
use App\Http\Requests\Auth\ForgetPasswordRequest;
use App\Http\Requests\Auth\PhoneVerifyRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\ProvideLoginRequest;
use App\Http\Requests\Auth\ReSendVerifyRequest;
use App\Http\Requests\FilterParamsRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService\AuthByMobilePhone;
use App\Services\EmailSettingService\EmailSendService;
use App\Services\UserServices\UserService;
use App\Services\UserServices\UserWalletService;
use Illuminate\Http\JsonResponse;
use Kreait\Laravel\Firebase\Facades\Firebase;
use Laravel\Sanctum\PersonalAccessToken;
use Lcobucci\JWT\UnencryptedToken;
use Spatie\Permission\Models\Role;
use App\Traits\ApiResponse;
use App\Models\User;
use Str;
use Throwable;
use DB;
use App\Models\Shop;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

class LoginController extends Controller
{
    use ApiResponse;

    /**
     * @param LoginRequest $request
     * @return JsonResponse
     */
    public function login(LoginRequest $request): JsonResponse
    {
        if ($request->input('phone')) {
            return $this->loginByPhone($request);
        }

        if (!auth()->attempt($request->only(['email', 'password']))) {
            return $this->onErrorResponse([
                'code'    => ResponseError::ERROR_102,
                'message' => __('errors.' . ResponseError::ERROR_102, locale: $this->language)
            ]);
        }

        /** @var User $user */
        $user  = auth()->user();
        $token = $user->createToken('api_token')->plainTextToken;

        /** @var User $user */
        $user  = auth('sanctum')->user();

        return $this->successResponse('User successfully login', [
            'token'         => $token,
            'access_token'  => $token,
            'token_type'    => 'Bearer',
            'user'          => UserResource::make($user->load(['roles', 'wallet'])),
        ]);
    }

    /**
     * @param $request
     * @return JsonResponse
     */
    protected function loginByPhone($request): JsonResponse
    {
        if (!auth()->attempt($request->only('phone', 'password'))) {
            return $this->onErrorResponse([
                'code'    => ResponseError::ERROR_102,
                'message' => __('errors.' . ResponseError::ERROR_102, locale: $this->language)
            ]);
        }

        /** @var User $user */
        $user  = auth()->user();
        $token = $user->createToken('api_token')->plainTextToken;

        /** @var User $user */
        $user  = auth('sanctum')->user();

        return $this->successResponse('User successfully login', [
            'token'        => $token,
            'access_token' => $token,
            'token_type'   => 'Bearer',
            'user'         => UserResource::make($user->load(['roles'])),
        ]);
    }

    /**
     * Obtain the user information from Provider.
     *
     * @param $provider
     * @param ProvideLoginRequest $request
     * @return JsonResponse
     */
    public function handleProviderCallback($provider, ProvideLoginRequest $request): JsonResponse
    {

        try {
            $this->validateProvider($request->input('id'));
        } catch (Throwable $e) {
            $this->error($e);

            return $this->onErrorResponse([
                'code'    => ResponseError::ERROR_107,
                'message' => __('errors.' . ResponseError::ERROR_107, locale: $this->language)
            ]);
        }

        try {
            $result = DB::transaction(function () use ($request, $provider) {

                @[$firstname, $lastname] = explode(' ', (string)$request->input('name', ''));

                $defaultName      = Str::before($request->input('email'), '@');
                $defaultFirstName = Str::ucfirst(Str::replace('.', ' ', $defaultName));

                $user = User::updateOrCreate(
                    [
                        'email' => $request->input('email')
                    ],
                    [
                        'email'             => $request->input('email'),
                        'email_verified_at' => now(),
                        'referral'          => $request->input('referral'),
                        'active'            => true,
                        'firstname'         => !empty($firstname) ? $firstname : $defaultFirstName,
                        'lastname'          => $lastname,
                    ]
                );

                if ($request->input('avatar') && empty($user->img)) {
                    $user->update(['img' => $request->input('avatar')]);
                }

                $user->socialProviders()->updateOrCreate([
                    'provider'      => $provider,
                    'provider_id'   => $request->input('id'),
                ], [
                    'avatar' => $request->input('avatar')
                ]);

                if (!$user->hasAnyRole(Role::query()->pluck('name')->toArray())) {
                    $user->syncRoles('user');
                }

                (new UserService)->notificationSync($user);

                if (empty($user->wallet)) {
                    (new UserWalletService)->create($user);
                }

                $token = $user->createToken('api_token')->plainTextToken;
                // Ensure email subscription is active
                $user->emailSubscription()->updateOrCreate(
                    ['user_id' => $user->id],
                    ['active'  => true]
                );

                // Ensure Shop exists (same behavior as afterVerify flow)
                $hasShop = Shop::where('user_id', $user->id)->exists();

                if (! $hasShop) {
                    // Build unique slug from user's name/email
                    $base = trim(($user->firstname ? ($user->firstname.' '.$user->lastname) : $user->email));
                    $baseSlug = Str::slug($base) ?: 'user-'.$user->id;
                    $slug = $baseSlug;

                    $i = 1;
                    while (Shop::where('slug', $slug)->exists()) {
                        $slug = $baseSlug.'-'.(++$i);
                    }

                    /** @var \App\Models\Shop $shop */
                    $shop = Shop::create([
                        'user_id'       => $user->id,
                        'uuid'          => (string) Str::uuid(),
                        'slug'          => $slug,
                        'status'        => 'approved',
                        'type'          => 1,
                        'delivery_type' => 1,
                        'open'          => true,
                        'visibility'    => true,
                        'verify'        => true,
                        'min_amount'    => 0,
                    ]);

                    // Optional: create translation if your model supports it
                    if (method_exists($shop, 'translation')) {
                        $shop->translation()->create([
                            'locale'      => $this->language ?? 'en',
                            'title'       => ($user->firstname ?: 'My').' Shop',
                            'description' => null,
                            'address'     => null,
                        ]);
                    }

                    // Optional back‑reference if column exists
                    if (Schema::hasColumn('users', 'shop_id')) {
                        $user->forceFill(['shop_id' => $shop->id])->save();
                    }

                    // Give seller role unless user is admin
                    if ($shop->status === 'approved'
                        && method_exists($user, 'hasRole') && ! $user->hasRole('admin')
                        && method_exists($user, 'assignRole')) {
                        $user->assignRole('seller'); // keep existing roles
                        // or $user->syncRoles('seller'); // if you prefer to replace
                    }
                }

                return [
                    'token'         => $token,
                    'access_token'  => $token,
                    'token_type'    => 'Bearer',
                    'user'          => UserResource::make($user->load(['roles'])),
                ];
            });

            return $this->successResponse('User successfully login', [
                'token'         => data_get($result, 'token'),
                'access_token'  => data_get($result, 'access_token'),
                'token_type'    => 'Bearer',
                'user'          => data_get($result, 'user'),
            ]);
        } catch (Throwable $e) {
            $this->error($e);
            return $this->onErrorResponse([
                'code'    => ResponseError::ERROR_400,
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * @param FilterParamsRequest $request
     * @return JsonResponse
     */
    public function checkPhone(FilterParamsRequest $request): JsonResponse
    {
        $user = User::select('phone')
            ->where('phone', $request->input('phone'))
            ->exists();

        return $this->successResponse('Success', [
            'exist' => !empty($request->input('phone')) && $user,
        ]);
    }

    /**
     * @param FilterParamsRequest $request
     * @return JsonResponse
     */
    public function logout(FilterParamsRequest $request): JsonResponse
    {
        try {
            /** @var User $user */
            $user = auth('sanctum')->user();

            $firebaseToken  = collect($user->firebase_token)
                ->reject(fn($item) => (string)$item == (string)$request->input('firebase_token') || empty($item) || (string)$item == (string)$request->input('token'))
                ->toArray();

            $user->update([
                'firebase_token' => $firebaseToken
            ]);

            try {
                $token   = str_replace('Bearer ', '', request()->header('Authorization'));

                $current = PersonalAccessToken::findToken($token);
                $current->delete();

            } catch (Throwable $e) {
                $this->error($e);
            }

        } catch (Throwable $e) {
            $this->error($e);
        }

        return $this->successResponse('User successfully logout');
    }

    /**
     * @param $idToken
     * @return UnencryptedToken|bool
     */
    public function validateProvider($idToken): UnencryptedToken|bool
    {
        return !config('app.is_demo') ? Firebase::auth()->verifyIdToken($idToken) : true;
    }

    /**
     * @param ForgetPasswordRequest $request
     * @return JsonResponse
     */
    public function forgetPassword(ForgetPasswordRequest $request): JsonResponse
    {
        return (new AuthByMobilePhone)->authentication($request->validated());
    }

    /**
     * @param ReSendVerifyRequest $request
     * @return JsonResponse
     */
    public function forgetPasswordEmail(ReSendVerifyRequest $request): JsonResponse
    {
        $user = User::where('email', $request->input('email'))->first();

        if (!$user) {
            return $this->onErrorResponse([
                'code'    => ResponseError::ERROR_404,
                'message' => __('errors.' . ResponseError::ERROR_404, locale: $this->language),
            ]);
        }

        $token = mb_substr((string)time(), -6, 6);

        $result = (new EmailSendService)->sendEmailPasswordReset($user, $token);

        if (!data_get($result, 'status')) {
            return $this->onErrorResponse($result);
        }

        $user->update([
            'verify_token' => $token
        ]);

        return $this->successResponse('Verify code send');
    }

    /**
     * @param int $hash
     * @return JsonResponse
     */
    public function forgetPasswordVerifyEmail(int $hash): JsonResponse
    {
        $user = User::where('verify_token', $hash)->first();

        if (!$user) {
            return $this->onErrorResponse([
                'code'    => ResponseError::ERROR_404,
                'message' => __('errors.' . ResponseError::USER_NOT_FOUND, locale: $this->language)
            ]);
        }

        if (!$user->hasAnyRole(Role::query()->pluck('name')->toArray())) {
            $user->syncRoles('user');
        }

        $token = $user->createToken('api_token')->plainTextToken;

        $user->update([
            'active'       => true,
            'verify_token' => null
        ]);

        return $this->successResponse('User successfully login', [
            'token'         => $token,
            'access_token'  => $token,
            'token_type'    => 'Bearer',
            'user'          => UserResource::make($user->load(['roles'])),
        ]);
    }

    /**
     * @param ForgetPasswordBeforeRequest $request
     * @return JsonResponse
     */
    public function forgetPasswordBefore(ForgetPasswordBeforeRequest $request): JsonResponse
    {
        try {
            $this->validateProvider($request->input('id'));
        } catch (Throwable $e) {
            $this->error($e);
            return $this->onErrorResponse([
                'code'    => ResponseError::ERROR_107,
                'message' => __('errors.' . ResponseError::ERROR_107, locale: $this->language)
            ]);
        }

        return (new AuthByMobilePhone)->forgetPasswordBefore($request->validated());
    }

    /**
     * @param PhoneVerifyRequest $request
     * @return JsonResponse
     */
    public function forgetPasswordVerify(PhoneVerifyRequest $request): JsonResponse
    {
        try {
            $this->validateProvider($request->input('id'));
        } catch (Throwable $e) {
            $this->error($e);
            return $this->onErrorResponse([
                'code'    => ResponseError::ERROR_107,
                'message' => __('errors.' . ResponseError::ERROR_107, locale: $this->language)
            ]);
        }

        return (new AuthByMobilePhone)->forgetPasswordVerify($request->all());
    }

}
