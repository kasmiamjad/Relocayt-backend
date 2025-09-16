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
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Receipt #{{ $model->id }}</title>
    <style>
        @page { margin: 10mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #333; margin: 0; }
        h2, h3 { margin: 0; }
        .subtext { color: #666; font-size: 11px; }

        table { width: 100%; border-collapse: collapse; }
        td, th { padding: 6px; vertical-align: top; }
        .header td { border-bottom: 1px solid #ddd; }
        .card { border: 1px solid #ddd; border-radius: 4px; padding: 8px; margin-top: 10px; }
        .card-title { font-weight: bold; margin-bottom: 6px; }

        .price-table td { padding: 4px 6px; }
        .price-table td:last-child { text-align: right; }
        .total-row td { font-weight: bold; border-top: 1px solid #000; }

        .footer { font-size: 10px; color: #777; text-align: center; padding-top: 10px; margin-top: 20px; border-top: 1px solid #ddd; }
        img.logo { max-height: 30px; max-width: 100px; }
    </style>
</head>
<body>

    <!-- Header -->
    <table class="header">
        <tr>
            <td>
                <h2>Your receipt from {{ $model->shop?->translation?->title ?? 'Booking' }}</h2>
                <div class="subtext">Receipt ID: BK-{{ $model->id }} • {{ now()->format('F d, Y') }}</div>
            </td>
            <td style="text-align: right;">
                <img src="{{ $logo }}" class="logo" alt="logo">
            </td>
        </tr>
    </table>

    <!-- Booking Info -->
    <table class="card" style="margin-top:15px;">
        <tr>
            <td class="card-title">{{ $services[0]['title'] }}</td>
        </tr>
        <tr>
            <td class="subtext">
                {{ $model->start_date?->format('D, M d, Y') }}
                → {{ $model->end_date?->format('D, M d, Y') }}
            </td>
        </tr>
        <tr><td>Traveler: {{ $userName }}</td></tr>
        <tr><td>Master: {{ $services[0]['master'] }}</td></tr>
        <tr><td>Status: {{ $services[0]['status'] }}</td></tr>
    </table>

    <!-- Price Breakdown -->
    <table class="card price-table" style="margin-top:15px;">
        <tr><td colspan="2" class="card-title">Price breakdown</td></tr>
        <tr>
            <td>Service fee</td>
            <td>{{ $position === 'before' ? $symbol : '' }}{{ number_format($model->price,2) }}{{ $position === 'after' ? $symbol : '' }}</td>
        </tr>
        <tr>
            <td>Extras</td>
            <td>{{ $position === 'before' ? $symbol : '' }}{{ number_format($model->rate_extra_price,2) }}{{ $position === 'after' ? $symbol : '' }}</td>
        </tr>
        <tr>
            <td>Discount</td>
            <td>-{{ $position === 'before' ? $symbol : '' }}{{ number_format($model->rate_discount,2) }}{{ $position === 'after' ? $symbol : '' }}</td>
        </tr>
        <tr>
            <td>Coupon</td>
            <td>-{{ $position === 'before' ? $symbol : '' }}{{ number_format($model->rate_coupon_price,2) }}{{ $position === 'after' ? $symbol : '' }}</td>
        </tr>
        <tr class="total-row">
            <td>Total</td>
            <td>{{ $position === 'before' ? $symbol : '' }}{{ number_format($model->rate_total_price,2) }}{{ $position === 'after' ? $symbol : '' }}</td>
        </tr>
    </table>

    <!-- Payment Info -->
    <table class="card payment-table" style="margin-top:15px;">
        <tr><td colspan="2" class="card-title">Payment</td></tr>
        <tr>
            <td>{{ $paymentMethod }}</td>
            <td>{{ $position === 'before' ? $symbol : '' }}{{ number_format($model->transaction?->price ?? 0, 2) }}{{ $position === 'after' ? $symbol : '' }}</td>
        </tr>
        <tr class="total-row">
            <td>Amount paid</td>
            <td>{{ $position === 'before' ? $symbol : '' }}{{ number_format($model->transaction?->price ?? 0, 2) }}{{ $position === 'after' ? $symbol : '' }}</td>
        </tr>
    </table>

    <!-- Footer -->
    <div class="footer">
        Thank you for booking with {{ $model->shop?->translation?->title ?? 'our service' }}.<br>
        For support, contact us at {{ $model->shop?->email ?? 'support@relocayt.com' }}.
    </div>

</body>
</html>

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

