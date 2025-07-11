<?php
declare(strict_types=1);

namespace App\Http;

use App\Http\Middleware\Authenticate;
use App\Http\Middleware\BlockIpMiddleware;
use App\Http\Middleware\CheckParentSeller;
use App\Http\Middleware\CheckSellerShop;
use App\Http\Middleware\EncryptCookies;
use App\Http\Middleware\PreventRequestsDuringMaintenance;
use App\Http\Middleware\RedirectIfAuthenticated;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\SanctumCheck;
use App\Http\Middleware\TrimStrings;
use App\Http\Middleware\TrustLicence;
use App\Http\Middleware\TrustProxies;
use Fruitcake\Cors\HandleCors;
use Illuminate\Auth\Middleware\AuthenticateWithBasicAuth;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Foundation\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Foundation\Http\Middleware\ValidatePostSize;
use Illuminate\Http\Middleware\SetCacheHeaders;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Middleware\ValidateSignature;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class Kernel extends HttpKernel
{
    /**
     * The application's global HTTP middleware stack.
     *
     * These middleware are run during every request to your application.
     *
     * @var array<int, class-string|string>
     */
    protected $middleware = [
        // \App\Http\Middleware\TrustHosts::class,
        TrustProxies::class,
        HandleCors::class,
        PreventRequestsDuringMaintenance::class,
        ValidatePostSize::class,
        TrimStrings::class,
        ConvertEmptyStringsToNull::class,
    ];

    /**
     * The application's route middleware groups.
     *
     * @var array<string, array<int, class-string|string>>
     */
    protected $middlewareGroups = [
        'web' => [
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            // \Illuminate\Session\Middleware\AuthenticateSession::class,
            ShareErrorsFromSession::class,
//            VerifyCsrfToken::class,
            SubstituteBindings::class,
        ],

        'api' => [
//             EnsureFrontendRequestsAreStateful::class,
            // 'throttle:5000,1',
            // SubstituteBindings::class,
            // TrustLicence::class,
        ],
        'sessions' => [
            StartSession::class,
        ]
    ];

    /**
     * The application's route middleware.
     *
     * These middleware may be assigned to groups or used individually.
     *
     * @var array<string, class-string|string>
     */
    protected $routeMiddleware = [
        'block.ip'              => BlockIpMiddleware::class,
        'auth'                  => Authenticate::class,
        'auth.basic'            => AuthenticateWithBasicAuth::class,
        'cache.headers'         => SetCacheHeaders::class,
        'can'                   => Authorize::class,
        'guest'                 => RedirectIfAuthenticated::class,
        'password.confirm'      => RequirePassword::class,
        'signed'                => ValidateSignature::class,
        'throttle'              => ThrottleRequests::class,
        'verified'              => EnsureEmailIsVerified::class,
        'sanctum.check'         => SanctumCheck::class,
        'role'                  => RoleMiddleware::class,
        'check.shop'            => CheckSellerShop::class,
        'check.parentSeller'    => CheckParentSeller::class,
    ];
}
