<?php
declare(strict_types=1);

namespace App\Repositories\CategoryRepository;

use App\Models\Category;
use App\Models\Language;
use App\Repositories\CoreRepository;
use Illuminate\Pagination\LengthAwarePaginator;

class RestCategoryRepository extends CoreRepository
{
    protected function getModelClass(): string
    {
        return Category::class;
    }

    /**
     * Get Parent, only categories where parent_id == 0
     */
    public function parentCategories(array $filter = []): LengthAwarePaginator
    {
        /** @var Category $category */
        $category = $this->model();
        

        return $category
            ->withThreeChildren(['lang' => $this->language])
            ->filter($filter)
            ->whereHas('translation',
                fn($q) => $q
                    ->where('locale', $this->language)
                    ->select('id', 'locale', 'title', 'category_id'),
            )
            ->paginate($filter['perPage'] ?? 10);
    }

}
