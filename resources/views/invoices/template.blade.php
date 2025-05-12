<!doctype html>
<html lang="{{ app()->getLocale() }}">

<head>
    <meta charset="utf-8">
    <title>Invoice #{{ $payment->uuid }}</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap');

        /* Screen styles */
        body {
            font-family: 'Poppins', Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 30px;
            color: #0c1f2b;
        }
        .container {
            max-width: 700px;
            margin: 0 auto;
            background: #0c1f2b;
            color: #f9e8c4;
            padding: 30px;
            border-radius: 10px;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        .logo {
            max-height: 60px;
            margin-bottom: 10px;
        }
        h1 {
            margin: 0;
            font-size: 28px;
            color: #70bbbb;
        }
        .date {
            font-size: 14px;
            margin-top: 5px;
        }
        .customer {
            background: #112832;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .items table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .items th,
        .items td {
            border: 1px solid #233240;
            padding: 10px;
            text-align: left;
            font-size: 14px;
        }
        .items th {
            background: #233240;
            color: #70bbbb;
        }
        .totals {
            width: 100%;
            margin-top: 10px;
            font-size: 14px;
        }
        .totals td {
            padding: 10px;
        }
        .right {
            text-align: right;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            font-size: 12px;
            color: #888;
        }

        /* Print styles */
        @media print {
            body, .container, .customer, .items th, .items td, .totals td {
                background: none !important;
                color: #000 !important;
                border-color: #000 !important;
            }
            body {
                background: #fff;
                padding: 0;
            }
            .container {
                margin: 0;
                padding: 15px;
                border: 1px solid #000;
                border-radius: 0;
            }
            .customer {
                border: 1px solid #000;
                padding: 10px;
                margin-bottom: 15px;
            }
            .items th, .items td {
                border: 1px solid #000;
                padding: 8px;
            }
            .header .logo {
                display: none;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <img src="{{ public_path('images/jo_logo.png') }}" alt="Logo" class="logo">
            <h1>Invoice #{{ $payment->uuid }}</h1>
            <div class="date">Date: {{ $payment->created_at->format('F j, Y') }}</div>
        </div>

        <div class="customer">
            <p><strong>Customer:</strong> {{ $payment->user->firstname }} {{ $payment->user->lastname }}</p>
            <p><strong>Email:</strong> {{ $payment->user->email }}</p>
        </div>

        <div class="items">
            <h2>Cart Contents</h2>
            <table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Qty</th>
                        <th class="right">Unit Price</th>
                        <th class="right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($payment->cart_snapshot['items'] as $line)
                        <tr>
                            <td>{{ $line['product_name'] }}</td>
                            <td>{{ $line['quantity'] }}</td>
                            <td class="right">{{ number_format($line['discounted_price'] ?? $line['unit_price'], 2, '.', ' ') }} €</td>
                            <td class="right">{{ number_format(($line['discounted_price'] ?? $line['unit_price']) * $line['quantity'], 2, '.', ' ') }} €</td>
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
                    <td class="right">{{ number_format($payment->amount - $payment->refunded_amount, 2, '.', ' ') }} €</td>
                </tr>
            @endif

            <tr>
                <td><strong>Total Paid:</strong></td>
                <td class="right">{{ number_format($payment->amount - ($payment->refunded_amount ?? 0), 2, '.', ' ') }} €</td>
            </tr>
        </table>

        <div class="footer">
            Thank you for your business – {{ config('app.name') }}<br>
            <small>This invoice was generated automatically and is valid without signature.</small>
        </div>
    </div>
</body>

</html>
