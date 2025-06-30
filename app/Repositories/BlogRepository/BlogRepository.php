<?php
declare(strict_types=1);

namespace App\Repositories\BlogRepository;

use App\Helpers\Utility;
use App\Models\Blog;
use App\Repositories\CoreRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Schema;

class BlogRepository extends CoreRepository
{
    protected function getModelClass(): string
    {
        return Blog::class;
    }

    /**
     * Get brands with pagination
     * @param array $filter
     * @return LengthAwarePaginator
     */
    public function blogsPaginate(array $filter = []): LengthAwarePaginator
    {
        $column = data_get($filter, 'column','id');

        if ($column !== 'id') {
            $column = Schema::hasColumn('blogs', $column) ? $column : 'id';
        }

        return $this->model()
            ->whereHas('translation', fn($q) => $q->select('id', 'locale', 'blog_id', 'title', 'short_desc')
                ->where('locale', $this->language)
            )
            ->with([
                'translation' => fn($q) => $q
                    ->select('id', 'locale', 'blog_id', 'title', 'short_desc')
                    ->where('locale', $this->language)
            ])
            ->when(data_get($filter, 'type'), function ($q, $type) {
                $q->where('type', data_get(Blog::TYPES, $type));
            })
            ->when(data_get($filter, 'active'), function ($q, $active) {
                $q->where('active', $active);
            })
            ->when(data_get($filter, 'published_at'), function ($q) {
                $q->whereNotNull('published_at');
            })
            ->orderBy($column, data_get($filter,'sort','desc'))
            ->paginate($filter['perPage'] ?? 10);
    }

    /**
     * Get brands with pagination
     */
    public function blogByUUID(string $uuid): Model|null
    {
        return $this->model()
            ->whereHas('translation', fn ($q) => $q->where('locale', $this->language))
            ->with([
                'translation' => fn($q) => $q->where('locale', $this->language)
            ])
            ->firstWhere('uuid', $uuid);
    }

    /**
     * Get brands with pagination
     */
    public function blogByID(int|string $id): Model|null
    {
        return $this->model()
            ->whereHas('translation', fn($q) => $q->where('locale', $this->language))
            ->with([
                'translation' => fn($q) => $q->where('locale', $this->language)
            ])
            ->find($id);
    }

    /**
     * @param int $id
     * @return array
     */
    public function reviewsGroupByRating(int $id): array
    {
        return Utility::reviewsGroupRating([
            'reviewable_type' => Blog::class,
            'reviewable_id'   => $id,
        ]);
    }
}
