<?php
declare(strict_types=1);

namespace App\Http\Middleware;

use App\Helpers\ResponseError;
use App\Traits\ApiResponse;
use Closure;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class CheckParentSeller
{
    use ApiResponse;

    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure(Request): (Response|RedirectResponse) $next
     * @return JsonResponse
     * @throws Exception
     */
    public function handle(Request $request, Closure $next): JsonResponse
    {
        

        if (!auth('sanctum')->check()) {
            return $this->onErrorResponse(['code' => ResponseError::ERROR_100]);
        }

        return $next($request);
    }
}
