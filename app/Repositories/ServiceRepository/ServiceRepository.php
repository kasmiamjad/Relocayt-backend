<?php
declare(strict_types=1);

namespace App\Repositories\ServiceRepository;

use App\Models\Language;
use App\Models\Service;
use App\Repositories\CoreRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Schema;
use Illuminate\Support\Facades\Log;

class ServiceRepository extends CoreRepository
{
    protected function getModelClass(): string
    {
        return Service::class;
    }

    public function getShowWith(): array
    {
        $admin = [];

        if (!request()->is('api/v1/dashboard/user/*') && !request()->is('api/v1/rest/*') ) {
            $admin = [
                'translations',
                'serviceExtras.translations',
                'serviceFaqs.translations',
            ];
        }

        return array_merge([
            'category:id',
            'category.translation' => fn($q) => $q
                ->select('id', 'category_id', 'locale', 'title')
                ->where('locale', $this->language),
            'shop:id,logo_img',
            'shop.translation' => fn($q) => $q
                ->select('id', 'shop_id', 'locale', 'title')
                ->where('locale', $this->language),
            'translation' => fn($q) => $q
                ->where('locale', $this->language),
            'galleries',
            'serviceExtras' => fn($q) => $q->when(request()->is('api/v1/rest/*'), fn($q) => $q->where('active', true)),
            'serviceExtras.translation' => fn($q) => $q
                ->select('id', 'service_extra_id', 'locale', 'title')
                ->where('locale', $this->language),
            'serviceFaqs' => fn($q) => $q->when(request()->is('api/v1/rest/*'), fn($q) => $q->where('active', true)),
            'serviceFaqs.translation' => fn($q) => $q
                ->where('locale', $this->language),
        ], $admin);
    }

    public function paginate(array $filter): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        Log::info('🧪 Starting paginate()', [
            'filter' => $filter,
            'language' => $this->language
        ]);

        try {
            return $this->model()
                 ->select([
                    'id', 'slug',
                    'category_id', 'shop_id', 'master_id', // 👈 added master_id
                    'status', 'status_note',
                    'img', 'price', 'commission_fee', 'interval', 'pause',
                    'type', 'discount', 'data', 'gender', 'service_type',
                    'title', 'description',
                    'latitude', 'longitude',               // lat/lng already here
                    'radius_km',                           // 👈 added radius_km
                    'address', 'street', 'city', 'state', 'zipcode', 'country',
                    'gallery', 'documents',
                    'created_at', 'updated_at',
                ])
                ->when(isset($filter['shop_id']), fn($q) =>
                    $q->where('shop_id', $filter['shop_id'])
                )
                ->when(isset($filter['serviceId']), fn($q) =>
                    $q->where('id', $filter['serviceId'])
                )
                ->when(isset($filter['type']) && $filter['type'] === 'online', fn($q) =>
                    $q->where('type', 'online')
                )
                ->with([
                    'translation' => fn($q) => $q->where('locale', $this->language),
                    'category.translation' => fn($q) => $q->where('locale', $this->language),
                    'shop.seller:id,firstname,email,verification_status',
                ])
                ->orderBy('id', 'desc')
                ->paginate($filter['perPage'] ?? 10);

        } catch (\Throwable $e) {
            Log::error('🔥 paginate() error: ' . $e->getMessage(), [
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'trace' => $e->getTraceAsString()
            ]);
            abort(500, 'Paginate crash: ' . $e->getMessage());
        }
    }
    
    public function show(Service $model): Service
    {
        return $model->loadMissing($this->getShowWith());
    }

    public function showById(int $id): ?Service
    {
        Log::info('🧪 showById', [
            'filter' => $id,
        ]);
        return $this->model()
            ->withMax('serviceMaster', 'discount')
            ->with($this->getShowWith())->find($id);
    }

}
