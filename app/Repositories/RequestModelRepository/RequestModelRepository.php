<?php
declare(strict_types=1);

namespace App\Repositories\RequestModelRepository;

use App\Models\Category;
use App\Models\Language;
use App\Models\Product;
use App\Models\RequestModel;
use App\Repositories\CoreRepository;
use Schema;

class RequestModelRepository extends CoreRepository
{
    protected function getModelClass(): string
    {
        return RequestModel::class;
    }

    /**
     * Get brands with pagination
     * @param array $filter
     * @return mixed
     */
    public function index(array $filter = []): mixed
    {
        $column = data_get($filter,'column','id');

        if ($column !== 'id') {
            $column = Schema::hasColumn('request_models', $column) ? $column : 'id';
        }

        return $this->model()
            ->filter($filter)
            ->with($this->getWithByType(data_get($filter, 'type', 'category')))
            ->orderBy($column, data_get($filter,'sort','desc'))
            ->paginate($filter['perPage'] ?? 10);
    }

    /**
     * @param RequestModel $requestModel
     * @return RequestModel
     */
    public function show(RequestModel $requestModel): RequestModel
    {
        return $requestModel->loadMissing($this->getOneWithByType($requestModel));
    }

    /**
     * @param RequestModel $requestModel
     * @return array|string[]
     */
    private function getOneWithByType(RequestModel $requestModel): array
    {
        

        $with = [
            'model',
            'createdBy',
        ];

        if ($requestModel->model_type === Product::class) {
            $with = [
                'model' => fn($q) => $q->with([
                    'galleries' => fn($q) => $q->select('id', 'type', 'loadable_id', 'path', 'title', 'preview'),
                    'properties' => fn($query) => $query->where(function ($q) {
                        $q->where('locale', $this->language);
                    }),
                    'stocks.stockExtras.group.translation' => fn($query) => $query->where(function ($q) {
                        $q->where('locale', $this->language);
                    }),
                    'stocks.addons.addon' => fn($q) => $q->where('active', true)
                        ->where('addon', true)
                        ->where('status', Product::PUBLISHED),
                    'stocks.addons.addon.stock',
                    'stocks.addons.addon.translation' => fn($query) => $query->where(function ($q) {
                        $q->where('locale', $this->language);
                    }),
                    'discounts' => fn($q) => $q->where('start', '<=', today())->where('end', '>=', today())
                        ->where('active', 1),
                    'shop.translation' => fn($query) => $query->where(function ($q) {
                        $q->where('locale', $this->language);
                    }),
                    'category' => fn($q) => $q->select('id', 'uuid'),
                    'category.translation' => fn($query) => $query->where(function ($q) {
                        $q->where('locale', $this->language);
                    })
                        ->select('id', 'category_id', 'locale', 'title'),
                    'brand' => fn($q) => $q->select('id', 'uuid', 'title'),
                    'unit.translation' => fn($query) => $query->where(function ($q) {
                        $q->where('locale', $this->language);
                    }),
                    'reviews.galleries',
                    'reviews.user',
                    'translation' => fn($query) => $query->where(function ($q) {
                        $q->where('locale', $this->language);
                    }),
                    'tags.translation' => fn($q) => $q->select('id', 'category_id', 'locale', 'title')
                        ->where('locale', $this->language),
                ]),
                'createdBy',
            ];
        } else if ($requestModel->model_type === Category::class) {
            $with = [
                'model.translation' => fn($q) => $q
                    ->where('locale', $this->language),
                'createdBy',
            ];
        }

        return $with;
    }

    /**
     * @param string|null $type
     * @return array|string[]
     */
    private function getWithByType(?string $type = null): array
    {
        

        $with = [
            'model',
            'createdBy',
        ];

        if (in_array($type, ['category', 'product'])) {
            $with = [
                'model.translation' => fn($q) => $q
                    ->where('locale', $this->language),
                'createdBy',
            ];
        }

        return $with;
    }
}
