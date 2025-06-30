<?php
declare(strict_types=1);

namespace App\Http\Controllers\API\v1\Dashboard\Seller;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Models\User;
use App\Traits\ApiResponse;

abstract class SellerBaseController extends Controller
{
    use ApiResponse;

    protected Shop|null $shop;

    public function __construct()
    {
        parent::__construct();
        Log::info('✅ SellerBaseController __construct');
        Log::info('🧑 Authenticated user:', [auth('sanctum')->user()]);
        Log::info('🏪 Related shop:', [auth('sanctum')->user()?->shop]);

        $this->middleware('check.shop')
            ->except('shopCreate', 'shopShow', 'shopUpdate');

        /** @var User $user */
        $user = auth('sanctum')->user();
        
        $this->shop = $user?->shop ?? $user?->moderatorShop;
    }

}
