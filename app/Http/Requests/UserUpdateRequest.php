<?php
declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UserUpdateRequest extends BaseRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'email'             => [
                'email',
                Rule::unique('users', 'email')->ignore(request()->route('user'), 'uuid')
            ],
            'phone'             => [
                'numeric',
                Rule::unique('users', 'phone')->ignore(request()->route('user'), 'uuid')
            ],
            'shop_id'                           => 'array',
            'shop_id.*'                         => 'integer|exists:shops,id',
            'delete_shop_id'                    => 'array',
            'delete_shop_id.*'                  => 'integer|exists:shops,id',
            'role'                              => 'string|exists:roles,name',
            'address'                          => 'string',
            'lastname'                          => 'string',
            'birthday'                          => 'date_format:Y-m-d',
            'firebase_token'                    => 'string',
            'firstname'                         => 'required|string|min:2|max:100',
            'gender'                            => ['string', Rule::in('male','female')],
            'active'                            => ['numeric', Rule::in(1,0)],
            'subscribe'                         => 'boolean',
            'password'                          => 'min:6|confirmed',
            'images'                            => 'array',
            'images.*'                          => 'string',
            'notifications'                     => 'array',
            'notifications.*.notification_id'   => ['int', Rule::exists('notifications', 'id')],
            'notifications.*.active'            => 'boolean',
            'currency_id'                       => 'integer|exists:currencies,id',
            'lang'                              => 'string|min:2',
            'title'                             => 'array',
            'title.*'                           => 'string|min:2|max:191',
            'description'                       => 'array',
            'description.*'                     => 'string|min:3',
            
        ];
    }
}
