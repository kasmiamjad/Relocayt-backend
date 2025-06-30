<?php
declare(strict_types=1);

namespace App\Http\Requests\Review;

use App\Http\Requests\BaseRequest;

class AnswerReviewRequest extends BaseRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'answer'  => 'required|string|max:255',
            'shop_id' => 'int|exists:shops,id',
        ];
    }
}
