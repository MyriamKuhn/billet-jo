<!doctype html>
<html>

<head>
    <meta charset="utf-8">
    <title>Invoice {{ $payment->uuid }}</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
        }

        .logo {
            max-height: 60px;
            margin-bottom: 10px;
        }

        .items table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .items th,
        .items td {
            border: 1px solid #ccc;
            padding: 8px;
            text-align: left;
        }

        .totals {
            width: 100%;
            margin-top: 10px;
        }

        .totals td {
            padding: 8px;
        }

        .right {
            text-align: right;
        }
    </style>
</head>

<body>
    <div class="header">
        <img src="{{ public_path('images/jo_logo.png') }}" alt="Logo" class="logo">
        <h1>Invoice #{{ $payment->uuid }}</h1>
        <p>Date: {{ $payment->created_at->format('Y-m-d') }}</p>
    </div>

    <div class="customer">
        <strong>Customer:</strong> {{ $payment->user->firstname }} {{ $payment->user->lastname }}<br>
        <strong>Email:</strong> {{ $payment->user->email }}
    </div>

    <div class="items">
        <h2>Cart Contents</h2>
        <table>
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Qty</th>
                    <th>Unit Price</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($payment->cart_snapshot as $line)
                    <tr>
                        <td>{{ $line['product_name'] }}</td>
                        <td>{{ $line['quantity'] }}</td>
                        <td class="right">
                            {{ number_format($line['discounted_price'] ?? $line['unit_price'], 2, '.', ' ') }} €</td>
                        <td class="right">
                            {{ number_format(($line['discounted_price'] ?? $line['unit_price']) * $line['quantity'], 2, '.', ' ') }}
                            €</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <table class="totals">
        <tr>
            <td><strong>Subtotal:</strong></td>
            <td class="right">{{ number_format($payment->amount, 2, '.', ' ') }} €</td>
        </tr>

        @if ($payment->refunded_amount > 0)
            <tr>
                <td><strong>Refunded:</strong></td>
                <td class="right">- {{ number_format($payment->refunded_amount, 2, '.', ' ') }} €</td>
            </tr>
            <tr>
                <td><strong>Balance Due:</strong></td>
                <td class="right">{{ number_format($payment->amount - $payment->refunded_amount, 2, '.', ' ') }} €
                </td>
            </tr>
        @endif

        <tr>
            <td><strong>Total Paid:</strong></td>
            <td class="right">{{ number_format($payment->amount - ($payment->refunded_amount ?? 0), 2, '.', ' ') }} €
            </td>
        </tr>
    </table>
</body>

</html>
