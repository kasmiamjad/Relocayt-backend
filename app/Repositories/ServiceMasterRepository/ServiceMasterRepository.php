<?php
declare(strict_types=1);

namespace App\Repositories\ServiceMasterRepository;

use Schema;
use App\Models\ServiceMaster;
use App\Repositories\CoreRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ServiceMasterRepository extends CoreRepository
{
    protected function getModelClass(): string
    {
        return ServiceMaster::class;
    }

    public function paginate(array $filter): LengthAwarePaginator
    {
        $column = $filter['column'] ?? 'id';

        if ($column !== 'id') {
            $column = Schema::hasColumn('service_masters', $column) ? $column : 'id';
        }

        return $this->model()
            ->with([
                'master:id,firstname,lastname',
                'service:id,slug,category_id',
                'service.translation' => fn($q) => $q
                    ->select('id', 'service_id', 'locale', 'title')
                    ->where('locale', $this->language),
            ])
            ->filter($filter)
            ->orderBy($column, $filter['sort'] ?? 'desc')
            ->paginate($filter['perPage'] ?? 10);
    }

    public function show(ServiceMaster $model): ServiceMaster
    {
        return $model
            ->load([
                'service:id,slug,category_id',
                'service.translation' => fn($q) => $q
                    ->select('id', 'service_id', 'locale', 'title')
                    ->where('locale', $this->language),
                'shop:id,logo_img',
                'shop.translation' => fn($q) => $q
                    ->select('id', 'shop_id', 'locale', 'title')
                    ->where('locale', $this->language),
                'master:id,firstname,lastname,img,r_avg,b_count,b_sum',
                'galleries',
                'extras.translation' => fn($q) => $q
                    ->select('id', 'service_extra_id', 'locale', 'title')
                    ->where('locale', $this->language),
                'pricing.translation' => fn($q) => $q
                    ->select('id', 'price_id', 'locale', 'title')
                    ->where('locale', $this->language),
            ]);
    }

}
