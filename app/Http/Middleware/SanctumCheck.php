<?php
declare(strict_types=1);

namespace App\Http\Middleware;

use App\Helpers\ResponseError;
use App\Traits\ApiResponse;
use Closure;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;

class SanctumCheck
{
    use ApiResponse;

    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure(Request): (\Illuminate\Http\Response|RedirectResponse) $next
     * @return mixed
     * @throws Exception
     */
    public function handle(Request $request, Closure $next): mixed
    {

         //Log::info('🚦 sanctum.check middleware fired');

        if (!auth('sanctum')->check()) {
            Log::warning('❌ Sanctum user not authenticated');
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        if (auth('sanctum')->check()) {
            return $next($request);
        }

        return $this->errorResponse(
            ResponseError::ERROR_100,
            __('errors.' . ResponseError::ERROR_100, locale: request('lang', 'en')),
            Response::HTTP_UNAUTHORIZED
        );
    }
}
