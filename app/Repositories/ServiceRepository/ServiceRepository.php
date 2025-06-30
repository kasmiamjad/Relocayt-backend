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
                    // ->filter($filter) // comment out to isolate cause
                    ->with([
                        'translation' => fn($q) => $q->where('locale', $this->language),
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
        return $this->model()
            ->withMax('serviceMaster', 'discount')
            ->with($this->getShowWith())->find($id);
    }

}
