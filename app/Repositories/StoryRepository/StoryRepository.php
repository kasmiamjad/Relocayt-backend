<?php
declare(strict_types=1);

namespace App\Repositories\StoryRepository;

use App\Models\Shop;
use App\Models\Story;
use App\Models\Product;
use App\Repositories\CoreRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class StoryRepository extends CoreRepository
{
    protected function getModelClass(): string
    {
        return Story::class;
    }

    /**
     * @param array $data
     * @return LengthAwarePaginator
     */
    public function index(array $data = []): LengthAwarePaginator
    {
        /** @var Story $stories */
        $stories = $this->model();
        

        return $stories
            ->filter($data)
            ->with([
                'product'             => fn ($q) => $q->select(['id', 'uuid']),
                'product.translation' => fn ($q) => $q
                    ->select('id', 'product_id', 'locale', 'title')
                    ->where('locale', $this->language),
                'shop'                => fn ($q) => $q->select(['id', 'uuid', 'user_id', 'logo_img']),
                'shop.translation'    => fn ($q) => $q
                    ->select('id', 'shop_id', 'locale', 'title')
                    ->where('locale', $this->language),
            ])
            ->select([
                'id',
                'product_id',
                'shop_id',
                'active',
                'file_urls',
            ])
            ->paginate($data['perPage'] ?? 15);
    }

    public function list(array $data = []): array
    {
        $shopsStories = Story::with([
            'shop:id,uuid,logo_img,status',
            'shop.seller:id,firstname,lastname,img',
            'shop.translation' => fn ($q) => $q
                ->where('locale', $this->language)
                ->select('id', 'shop_id', 'locale', 'title'),
            'product' => fn($q) => $q->select([
                'id',
                'shop_id',
                'uuid',
                'active',
                'status',
            ])
                ->when(data_get($data, 'free'), function ($q) {
                    $q->whereHas('stock', fn($b) => $b->where('price', '=', 0));
                })
                ->where('active', 1)
                ->where('status', Product::PUBLISHED),
            'product.translation' => fn($query) => $query
                ->where('locale', $this->language)
                ->select('id', 'product_id', 'locale', 'title'),
        ])
            ->where('created_at', '>=', date('Y-m-d', strtotime('-1 day')))
            ->simplePaginate($data['perPage'] ?? 100);

        $shops = [];

        foreach ($shopsStories as $shopStories) {

            /** @var Story $shopStories */

            if (!isset($shops[$shopStories->shop_id])) {
                $shops[$shopStories->shop_id] = [];
            }

            $product = $shopStories->product;

            if ($product && (!$product->active || $product->status !== Product::PUBLISHED)) {
                continue;
            }

            if (!$shopStories->shop || $shopStories->shop->status !== Shop::APPROVED) {
                continue;
            }

            foreach ($shopStories->file_urls as $fileUrl) {

                $shopsStoriesTitle = $shopStories?->shop?->translation?->title;
                $productTitle      = $product?->translation?->title;
                $createdAt         = $shopStories?->created_at;
                $updatedAt         = $shopStories?->updated_at;

                $shops[$shopStories->shop_id][] = [
                    'shop_id'       => $shopStories->shop_id,
                    'shop_uuid'     => $shopStories?->shop?->uuid,
                    'shop_slug'     => $shopStories?->shop?->slug,
                    'logo_img'      => $shopStories?->shop?->logo_img,
                    'title'         => $shopsStoriesTitle,
                    'firstname'     => $shopStories->shop?->seller?->firstname,
                    'lastname'      => $shopStories->shop?->seller?->lastname,
                    'avatar'        => $shopStories->shop?->seller?->img,
                    'product_uuid'  => $product?->uuid,
                    'product_title' => $productTitle,
                    'url'           => $fileUrl,
                    'created_at'    => !empty($createdAt) ? $createdAt->format('Y-m-d H:i:s') . 'Z' : null,
                    'updated_at'    => !empty($updatedAt) ? $updatedAt->format('Y-m-d H:i:s') . 'Z' : null,
                ];

            }

        }

        $shops = collect($shops);

        return $shops?->count() > 0 ? array_values($shops->reject(fn($items) => empty($items))->toArray()) : [];
    }

    public function show(Story $story): Story
    {
        return $story->load([
            'product.translation' => fn($q) => $q
                ->where('locale', $this->language),
            'shop.translation'  => fn ($q) => $q
                ->select('id', 'shop_id', 'locale', 'title')
                ->where('locale', $this->language),
        ]);
    }
}
