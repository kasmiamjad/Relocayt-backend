<?php
declare(strict_types=1);

namespace App\Http\Controllers\API\v1\Dashboard\Seller;

use App\Helpers\ResponseError;
use App\Http\Requests\Shop\StoreRequest;
use App\Http\Resources\ShopResource;
use App\Models\Language;
use App\Models\Shop;
use App\Models\User;
use App\Repositories\ShopRepository\ShopRepository;
use App\Services\ShopServices\ShopActivityService;
use App\Services\ShopServices\ShopService;
use DB;
use Exception;
use Illuminate\Http\JsonResponse;
use Throwable;
use Illuminate\Support\Facades\Log;

class ShopController extends SellerBaseController
{

    public function __construct(private ShopRepository $shopRepository, private ShopService $shopService)
    {
        parent::__construct();
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param StoreRequest $request
     * @return JsonResponse
     */
    public function shopCreate(StoreRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = auth('sanctum')->user();

        $result = $this->shopService->create(
            $request->merge([
                'user_id' => $user?->id,
                'auth_token' => request()->bearerToken(), // <- key fix here
            ])->all()
        );

        if (!data_get($result, 'status')) {
            return $this->onErrorResponse($result);
        }

        $user?->invitations()->delete();

        return $this->successResponse(
            __('errors.' . ResponseError::RECORD_WAS_SUCCESSFULLY_CREATED, locale: $this->language),
            ShopResource::make(data_get($result, 'data'))
        );
    }


    /**
     * Display the specified resource.
     *
     * @return JsonResponse
     */
    public function shopShow(): JsonResponse
    {
        Log::info('📌 Entered Shop Show');
        Log::info('🧪 Authenticated user:', ['user' => auth('sanctum')->user()]);
        Log::info('🧪 Seller relation:', ['seller' => auth('sanctum')->user()?->seller]);
        Log::info('🧪 Shop relation:', ['shop' => $this->shop]);

        if (!$this->shop?->uuid) {
        Log::warning('⚠️ $this->shop is missing or has no uuid');

        return $this->onErrorResponse([
            'code' => ResponseError::ERROR_204,
            'http' => 401,
            'message' => '$this->shop is null or uuid missing'
        ]);
    }

        $shop = $this->shopRepository->shopDetails($this->shop->uuid);

        if (empty($shop)) {
            return $this->onErrorResponse(['code' => ResponseError::ERROR_404]);
        }

        /** @var Shop $shop */
        try {
            DB::table('shop_subscriptions')
                ->where('shop_id', $shop->id)
                ->whereDate('expired_at', '<', now())
                ->delete();
        } catch (Throwable) {}

        $shop = $shop->load([
            'translations',
            'seller.wallet',
            'subscription' => fn ($q) => $q->where('expired_at', '>=', now())->where('active', true),
            'subscription.subscription',
            'tags.translation' => fn ($q) => $q->where('locale', $this->language),
        ]);

        return $this->successResponse(
            __('errors.' . ResponseError::NO_ERROR, locale: $this->language),
            ShopResource::make($shop)
        );
    }

    /**
     * Update the specified resource in storage.
     *
     * @param StoreRequest $request
     * @return JsonResponse
     */
    public function shopUpdate(StoreRequest $request): JsonResponse
    {
        $result = $this->shopService->update($this->shop->uuid, $request->all());

        if (!data_get($result, 'status')) {
            return $this->onErrorResponse($result);
        }

        return $this->successResponse(
            __('errors.' . ResponseError::RECORD_WAS_SUCCESSFULLY_UPDATED, locale: $this->language),
            ShopResource::make(data_get($result, 'data'))
        );
    }

    /**
     * @return JsonResponse
     * @throws Exception
     */
    public function setWorkingStatus(): JsonResponse
    {
        (new ShopActivityService)->changeOpenStatus($this->shop->uuid);

        return $this->successResponse(
            __('errors.' . ResponseError::RECORD_WAS_SUCCESSFULLY_UPDATED, locale: $this->language),
            ShopResource::make($this->shop)
        );
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return void
     */
    public function destroy(int $id): void
    {
        //
    }
    public function shopDraftSave(Request $request)
    {
        $user = auth()->user();

        $validated = $request->validate([
            'step' => 'required|integer|min:0|max:4',
            'data' => 'required|array',
        ]);

        // Save or update the draft (assuming one draft per user)
        $draft = \App\Models\ShopDraft::updateOrCreate(
            ['user_id' => $user->id],
            ['data' => $validated['data'], 'step' => $validated['step']]
        );

        return response()->json(['message' => 'Draft saved', 'draft' => $draft]);
    }

    public function shopDraftShow(Request $request)
    {
        $user = auth()->user();

        $draft = \App\Models\ShopDraft::where('user_id', $user->id)->first();

        if (!$draft) {
            return response()->json(['message' => 'No draft found'], 404);
        }

        return response()->json([
            'step' => $draft->step,
            'data' => $draft->data,
        ]);
    }


}
