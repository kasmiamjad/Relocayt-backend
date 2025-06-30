<?php
declare(strict_types=1);

namespace App\Repositories\ServiceExtraRepository;

use App\Models\Language;
use App\Models\ServiceExtra;
use App\Repositories\CoreRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Schema;

class ServiceExtraRepository extends CoreRepository
{
    protected function getModelClass(): string
    {
        return ServiceExtra::class;
    }

    /**
     * @param array $filter
     * @return LengthAwarePaginator
     */
    public function paginate(array $filter = []): LengthAwarePaginator
    {
        

        $column = $filter['column'] ?? 'id';

        if ($column !== 'id') {
            $column = Schema::hasColumn('service_extras', $column) ? $column : 'id';
        }

        /** @var ServiceExtra $serviceExtra */
        $serviceExtra = $this->model();

        return $serviceExtra
            ->filter($filter)
            ->with([
                'service.translation' => fn($q) => $q
                    ->where('locale', $this->language)
                    ->select('id', 'locale', 'title', 'service_id'),
                'shop:id,logo_img',
                'shop.translation' => fn($q) => $q
                    ->where('locale', $this->language)
                    ->select('id', 'shop_id', 'locale', 'title'),
                'translation' => fn($q) => $q
                    ->where('locale', $this->language),
                'translations',
            ])
            ->orderBy($column, $filter['sort'] ?? 'desc')
            ->paginate($filter['perPage'] ?? 10);
    }

    /**
     * @param ServiceExtra $serviceExtra
     * @return ServiceExtra
     */
    public function show(ServiceExtra $serviceExtra):ServiceExtra
    {
        

        return $serviceExtra
            ->load([
                'service.translation' => fn($q) => $q
                    ->where('locale', $this->language)
                    ->select('id', 'locale', 'title', 'service_id'),
                'shop:id,logo_img',
                'shop.translation' => fn($q) => $q
                    ->where('locale', $this->language)
                    ->select('id', 'shop_id', 'locale', 'title'),
                'translation' => fn($q) => $q
                    ->where('locale', $this->language),
                'translations'
            ]);
    }
}
