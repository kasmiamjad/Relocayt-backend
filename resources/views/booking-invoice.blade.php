<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice #{{ $model->id }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #333; margin: 30px; }
        h1, h2, h3 { margin: 0; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .header img { max-height: 40px; }
        .subtext { color: #666; font-size: 11px; margin-top: 4px; }

        .grid { display: flex; gap: 20px; }
        .box { border: 1px solid #ddd; padding: 15px; border-radius: 6px; flex: 1; }

        .price-table, .payment-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .price-table td, .payment-table td { padding: 6px 0; font-size: 12px; }
        .price-table td:last-child, .payment-table td:last-child { text-align: right; }
        .total-row td { font-weight: bold; border-top: 1px solid #000; padding-top: 8px; }

        .footer { font-size: 10px; color: #777; margin-top: 40px; border-top: 1px solid #ddd; padding-top: 10px; }
    </style>
</head>
<body>

    <!-- Header -->
    <div class="header">
        <div>
            <h2>Your receipt from {{ $model->shop?->translation?->title ?? 'Booking' }}</h2>
            <div class="subtext">Receipt ID: BK-{{ $model->id }} • {{ now()->format('F d, Y') }}</div>
        </div>
        <div>
            <img src="{{ $logo }}" alt="logo">
        </div>
    </div>

    <!-- Main Grid -->
    <div class="grid">

        <!-- Booking Info -->
        <div class="box">
            <h3>{{ $services[0]['title'] }}</h3>
            <div class="subtext">{{ $model->start_date?->format('D, M d, Y') }} → {{ $model->end_date?->format('D, M d, Y') }}</div>
            <p>{{ $services[0]['type'] }} • {{ $services[0]['gender'] }}</p>
            <p>Traveler: {{ $model->user?->full_name }}</p>
            <p>Master: {{ $services[0]['master'] }}</p>
            <p>Status: {{ $services[0]['status'] }}</p>
        </div>

        <!-- Price Breakdown -->
        <div class="box">
            <h3>Price breakdown</h3>
            <table class="price-table">
                <tr>
                    <td>Service fee</td>
                    <td>{{ $position === 'before' ? $symbol : '' }}{{ number_format($model->rate_service_fee,2) }}{{ $position === 'after' ? $symbol : '' }}</td>
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
        </div>

        <!-- Payment Info -->
        <div class="box">
            <h3>Payment</h3>
            <table class="payment-table">
                <tr>
                    <td>{{ $paymentMethod }}</td>
                    <td>{{ $position === 'before' ? $symbol : '' }}{{ number_format($model->transaction?->price ?? 0, 2) }}{{ $position === 'after' ? $symbol : '' }}</td>
                </tr>
                <tr class="total-row">
                    <td>Amount paid</td>
                    <td>{{ $position === 'before' ? $symbol : '' }}{{ number_format($model->transaction?->price ?? 0, 2) }}{{ $position === 'after' ? $symbol : '' }}</td>
                </tr>
            </table>
        </div>

    </div>

    <!-- Footer -->
    <div class="footer">
        Thank you for booking with {{ $model->shop?->translation?->title ?? 'our service' }}.<br>
        For support, contact us at {{ $model->shop?->email ?? 'support@example.com' }}.
    </div>

</body>
</html>
