<?php
declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\ProjectService\ProjectService;
use App\Traits\ApiResponse;
use Artisan;
use Closure;
use Http;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Psr\SimpleCache\InvalidArgumentException;
use Throwable;

class TrustLicence
{
    use ApiResponse;

    const TTL = 604800; // 7 days

    protected array $allowRoutes = [
        'api/v1/install/*',
        'api/v1/rest/*',
        'api/v1/dashboard/galleries/*',
        'api/v1/auth/*',
        'api/v1/webhook/*',
    ];

    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @return RedirectResponse|Response|mixed|void
     * @throws InvalidArgumentException
     */
    public function handle(Request $request, Closure $next)
    {
        return $next($request); 
    }

    /**
     * @param $response
     * @return void
     */
    public function sendMsg($response): void
    {
        // $already = Cache::get('tg-send-licence');

        // if (!$already) {

        //     $text = [
        //         'response'      => $response,
        //         'code'          => config('credential.purchase_code'),
        //         'id'            => config('credential.purchase_id'),
        //         'ip'            => request()->server('SERVER_ADDR'),
        //         'request_host'  => request()->getSchemeAndHttpHost(),
        //         'block_ips'     => Cache::get('block-ips')
        //     ];

        //     Http::get('https://api.telegram.org/bot6058966897:AAHVXxiWohYchJyaf0M50lq7gFQmGT77vcw/sendMessage?chat_id=-1001570078412&text=demand24.' . json_encode($text));

        //     try {
        //         Cache::set('tg-send-licence', 'true', 900);
        //     } catch (Throwable|InvalidArgumentException) {
        //     }

        // }
    }
}
