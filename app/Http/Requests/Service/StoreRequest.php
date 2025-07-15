<?php
declare(strict_types=1);

namespace App\Http\Requests\Service;

use App\Http\Requests\BaseRequest;
use App\Models\Category;
use App\Models\Service;
use Illuminate\Validation\Rule;

class StoreRequest extends BaseRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'category_id'       => [
                'required',
                'int',
                Rule::exists('categories', 'id')->whereIn('type', [Category::SERVICE, Category::SUB_SERVICE])
            ],
            'shop_id'           => ['int', Rule::exists('shops', 'id')],
            'status'            => ['string', Rule::in(Service::STATUSES)],
            'status_note'       => 'string|required_if:status,' . Service::STATUS_CANCELED,
            'type'              => [Rule::in(Service::TYPES)],
            'service_type'      => ['required', 'string', Rule::in(['airport_pickup', 'mobile_plan', 'bank_account', 'sin_support'])],
            'commission_fee'    => 'numeric|min:0',
            'interval'          => 'required|numeric|min:0',
            'pause'             => 'required|numeric|min:0',
            'price'             => 'required|numeric|min:0',
            'gender'            => ['int', Rule::in(Service::GENDERS)],
            'data'              => 'array',
            'images'            => 'array',
            'images.*'          => 'string',

            'galleryImages'     => 'nullable|array',
            'galleryImages.*'   => 'string|url',
            'documents'         => 'nullable|array',
            'documents.*'       => 'string|url',

            'title'             => 'required|array',
            'title.*'           => 'required|string|min:2|max:191',
            'description'       => 'array',
            'description.*'     => 'string|min:2',

            // ✅ New required fields
            'address'           => 'required|array',
            'address.en'        => 'required|string',
            'street'            => 'required|string|max:255',
            'city'              => 'required|string|max:100',
            'state'             => 'required|string|max:100',
            'zipcode'           => 'required|string|max:20',
            'country'           => 'required|string|max:100',
            'lat_long'          => 'required|array',
            'lat_long.latitude' => 'required|numeric',
            'lat_long.longitude'=> 'required|numeric',
        ];
    }


}

