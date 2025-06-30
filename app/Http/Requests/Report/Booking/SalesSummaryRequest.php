<?php

namespace App\Http\Requests\Report\Booking;

use App\Http\Requests\BaseRequest;
use App\Models\Service;
use Illuminate\Validation\Rule;

class SalesSummaryRequest extends BaseRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'date_from'   => 'required|date_format:Y-m-d',
            'date_to'     => 'required|date_format:Y-m-d',
            'type'        => 'required|string|in:service,category,shop,master,user,gender,type',
            'shop_id'     => 'int|exists:shops,id',
            'master_id'   => 'int|exists:users,id',
            'service_id'  => 'int|exists:services,id',
            'user_id'     => 'int|exists:users,id',
            'category_id' => 'int|exists:categories,id',
            'gender'      => Rule::in(Service::GENDERS),
            'column'      => 'in:gift_cart_price,total_price,coupon_price,extra_time_price,discount,commission_fee,service_fee,extra_price,tips',
            'sort'        => 'in:asc,desc',
        ];
    }
}
