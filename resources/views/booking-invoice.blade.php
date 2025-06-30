<!doctype html>
<html lang="en">
<?php
/** @var App\Models\Booking $model */
/** @var string $lang */

/** @var string $logo */

use App\Helpers\ResponseError;
use App\Models\Booking;
use App\Models\Transaction;
use App\Models\Translation;

$keys = array_merge([
    'online',
    'offline_in',
    'offline_out',
    'offline_out',
], Transaction::STATUSES, Booking::STATUSES);

$paymentMethod = $model?->transaction?->paymentSystem?->tag;
$status = $model?->transaction?->status;

if (!empty($paymentMethod)) {
    $keys[] = $paymentMethod;
}

if (!empty($status)) {
    $keys[] = $status;
}

$translations = Translation::where('locale', $lang)
    ->whereIn('key', array_values($keys))
    ->pluck('value', 'key')
    ->toArray();

$paymentMethod = $translations[$paymentMethod] ?? $paymentMethod;

$userName = $model?->user?->full_name;
$userPhone = $model?->user?->phone;

$address = data_get($model?->data, 'address', '');
$position = $model?->currency?->position;
$symbol = $model?->currency?->symbol;

$title = $model->serviceMaster?->service?->translation?->title;

$genders = [
    1 => ResponseError::MALE,
    2 => ResponseError::FEMALE,
    3 => ResponseError::ALL_GENDER,
];

$gender = data_get($genders, $model->gender, 'all.gender');
$type = $children->type ?? $model->type;

$services = [
    [
        'date_from'     => $model->start_date?->format('g:i A'),
        'date_to'       => $model->end_date?->format('g:i A'),
        'status'        => $translations[$model->status] ?? $model->status,
        'type'          => $translations[$type] ?? $type,
        'master'        => $model->master?->full_name,
        'title'         => $title,
        'gender'        => __("errors.$gender", locale: $lang),
        'discount'      => $model->rate_discount,
        'gift_cart'     => $model->rate_gift_cart_price,
        'membership'    => !!$model->user_member_ship_id,
        'service_fee'   => $model->rate_service_fee,
        'extra_price'   => $model->rate_extra_price,
        'coupon_price'  => $model->rate_coupon_price,
        'total_price'   => $model->rate_total_price,
    ]
];

foreach ($model?->children ?? [] as $children) {

    $title = $children->serviceMaster?->service?->translation?->title;

    $gender = data_get($genders, $children->gender, 'all.gender');
    $type = $children->type ?? $model->type;

    $services[] = [
        'date_from'     => $children->start_date?->format('g:i A'),
        'date_to'       => $children->end_date?->format('g:i A'),
        'status'        => $translations[$children->status] ?? $children->status,
        'type'          => $translations[$type] ?? $type,
        'master'        => $children->master?->full_name,
        'title'         => $title,
        'gender'        => __("errors.$gender", locale: $lang),
        'discount'      => $children->rate_discount,
        'gift_cart'     => $children->rate_gift_cart_price,
        'membership'    => !!$children->user_member_ship_id,
        'service_fee'   => $children->rate_service_fee,
        'extra_price'   => $children->rate_extra_price,
        'coupon_price'  => $children->rate_coupon_price,
        'total_price'   => $children->rate_total_price,
    ];
}

//?>
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0, shrink-to-fit=no"
    >
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>{{ __('errors.' . ResponseError::BOOKING, locale: $lang) }}</title>
    <link
            rel="stylesheet"
            href="https://cdn.jsdelivr.net/npm/bootstrap@4.0.0/dist/css/bootstrap.min.css"
            integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm"
            crossorigin="anonymous"
    >
    <style>
        html {
            -webkit-box-sizing: border-box;
            box-sizing: border-box;
        }

        .logo {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            overflow: hidden;
        }

        .subtitle {
            margin-top: 50px;
        }

        .space {
            margin-top: 300px;
        }
    </style>
</head>
<body>
<div class="container d-flex justify-content-between">
    <div class="float-left">
        <img class="logo" src="{{$logo}}" alt="logo"/>
    </div>
    <div class="float-right">
        <h1 class="title">{{ __('errors.' . ResponseError::INVOICE, locale: $lang) }} #{{ $model?->id }}</h1>
        <h2 class="title gray">{{ $model->start_date?->format('Y-m-d') }} - {{ $model->end_date?->format('Y-m-d') }}</h2>
    </div>
</div>
<div class="container d-flex justify-content-between" style="margin-top: 100px">
    <div class="float-left" style="margin-right: 50px">
        <h3 class="subtitle">{{ __('errors.' . ResponseError::CLIENT, locale: $lang) }}</h3>
        <div class="address__info">
            <div class="address__info--item">{!! $userName !!}</div>
            <div class="address__info--item">{!! $address !!}</div>
            <div class="address__info--item">
                {!! !empty($userPhone) ? '+' . str_replace('+', '', $userPhone) : '' !!}
            </div>
        </div>
    </div>
</div>
<div class="space"></div>
<table class="table table-striped mt-4 table-bordered"> {{-- style="page-break-after: always;" --}}
    <thead>
    <tr>
        <th scope="col">{{ __('errors.' . ResponseError::SERVICE_NAME, locale: $lang) }}</th>
        <th scope="col">{{ __('errors.' . ResponseError::DATE_FROM, locale: $lang) }}</th>
        <th scope="col">{{ __('errors.' . ResponseError::DATE_TO, locale: $lang) }}</th>
        <th scope="col">{{ __('errors.' . ResponseError::STATUS, locale: $lang) }}</th>
        <th scope="col">{{ __('errors.' . ResponseError::PLACE, locale: $lang) }}</th>
        <th scope="col">{{ __('errors.' . ResponseError::MASTER, locale: $lang) }}</th>
        <th scope="col">{{ __('errors.' . ResponseError::PAYMENT_TYPE, locale: $lang) }}</th>
        <th scope="col">{{ __('errors.' . ResponseError::TRANSACTION_STATUS, locale: $lang) }}</th>
    </tr>
    </thead>
    <tbody>
    @foreach($services as $service)
        <tr>
            <th scope="row">{{ $service['title'] }}</th>
            <td>{{ $service['date_from'] }}</td>
            <td>{{ $service['date_to'] }}</td>
            <td>{{ $service['status'] }}</td>
            <td>{{ $service['type'] }}</td>
            <td>{{ $service['master'] }}</td>
            <td>{{ $translations[$paymentMethod] ?? $paymentMethod }}</td>
            <td>{{ $translations[$status] ?? $status }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
<div class="space"></div>
<table class="table table-striped mt-4 table-bordered"> {{-- style="page-break-after: always;" --}}
    <thead>
    <tr>
        <th scope="col">{{ __('errors.' . ResponseError::DISCOUNT, locale: $lang) }}</th>
        <th scope="col">{{ __('errors.' . ResponseError::SERVICE_FEE, locale: $lang) }}</th>
        <th scope="col">{{ __('errors.' . ResponseError::EXTRA_PRICE, locale: $lang) }}</th>
        <th scope="col">{{ __('errors.' . ResponseError::COUPON, locale: $lang) }}</th>
        <th scope="col">{{ __('errors.' . ResponseError::TOTAL_PRICE, locale: $lang) }}</th>
    </tr>
    </thead>
    <tbody>
    @foreach($services as $service)
        <tr>
            <td>
                {{ $position === 'before' ? $symbol : '' }}
                {{ number_format($service['discount'] ?? 0, 2)  }}
                {{ $position === 'after' ? $symbol : '' }}
            </td>
            <td>
                {{ $position === 'before' ? $symbol : '' }}
                {{ number_format($service['service_fee'] ?? 0, 2)  }}
                {{ $position === 'after' ? $symbol : '' }}
            </td>
            <td>
                {{ $position === 'before' ? $symbol : '' }}
                {{ number_format($service['extra_price'] ?? 0, 2)  }}
                {{ $position === 'after' ? $symbol : '' }}
            </td>
            <td>
                {{ $position === 'before' ? $symbol : '' }}
                {{ number_format($service['coupon_price'] ?? 0, 2)  }}
                {{ $position === 'after' ? $symbol : '' }}
            </td>
            <td>
                {{ $position === 'before' ? $symbol : '' }}
                {{ number_format($service['total_price'] ?? 0, 2)  }}
                {{ $position === 'after' ? $symbol : '' }}
            </td>
        </tr>
    @endforeach
    </tbody>
</table>
<table class="table table-striped mt-4 table-bordered">
    <thead>
    <tr>
        <th scope="col">{{ __('errors.' . ResponseError::SHOP, locale: $lang) }}</th>
    </tr>
    </thead>
    <tbody>
    <tr>
        <th scope="row">{{$model?->shop?->translation?->title}}</th>
    </tr>
    </tbody>
</table>
<script
        src="https://code.jquery.com/jquery-3.2.1.slim.min.js"
        integrity="sha384-KJ3o2DKtIkvYIK3UENzmM7KCkRr/rE9/Qpg6aAZGJwFDMVNA/GpGFF93hXpG5KkN"
        crossorigin="anonymous">
</script>

<script
        src="https://cdn.jsdelivr.net/npm/popper.js@1.12.9/dist/umd/popper.min.js"
        integrity="sha384-ApNbgh9B+Y1QKtv3Rn7W3mgPxhU9K/ScQsAP7hUibX39j7fakFPskvXusvfa0b4Q"
        crossorigin="anonymous">
</script>

<script
        src="https://cdn.jsdelivr.net/npm/bootstrap@4.0.0/dist/js/bootstrap.min.js"
        integrity="sha384-JZR6Spejh4U02d8jOt6vLEHfe/JQGiRRSQQxSfFWpi1MquVdAyjUar5+76PVCmYl"
        crossorigin="anonymous">
</script>

</body>
</html>
