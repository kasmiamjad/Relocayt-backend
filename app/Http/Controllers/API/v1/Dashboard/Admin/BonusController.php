<?php
declare(strict_types=1);

namespace App\Http\Controllers\API\v1\Dashboard\Admin;

use App\Http\Requests\FilterParamsRequest;
use App\Http\Resources\Bonus\BonusResource;
use App\Models\Bonus;
use App\Models\Language;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BonusController extends AdminBaseController
{
    /**
     * Display a listing of the resource.
     *
     * @param FilterParamsRequest $request
     * @return AnonymousResourceCollection
     */
    public function index(FilterParamsRequest $request): AnonymousResourceCollection
    {
        

        $bonuses = Bonus::filter($request->all())
            ->with([
                'stock.product.translation' => fn($query) => $query->where('locale', $this->language),
                'bonusStock.product.translation' => fn($query) => $query->where('locale', $this->language),
                'shop:id,logo_img',
                'shop.translation' => fn($q) => $q->select('id', 'shop_id', 'locale', 'title')
                    ->where('locale', $this->language)
            ])
            ->paginate($request->input('perPage', 10));

        return BonusResource::collection($bonuses);
    }

}
