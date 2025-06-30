<?php
declare(strict_types=1);

namespace App\Services\ReviewService;

use App\Helpers\ResponseError;
use App\Models\Review;
use App\Services\CoreService;

class ReviewService extends CoreService
{
    protected function getModelClass(): string
    {
        return Review::class;
    }

    public function update(int $id, array $data): array
    {
        $review = Review::find($id);

        if (!$review) {
            return [
                'status'  => false,
                'code'    => ResponseError::ERROR_400,
                'message' => __('errors.' . ResponseError::ERROR_400)
            ];
        }

        $review->update([
            'answer' => $data['answer']
        ]);

        return [
            'status'  => true,
            'code'    => ResponseError::NO_ERROR,
            'message' => __('errors.' . ResponseError::NO_ERROR),
            'data'    => $review
        ];
    }
}
