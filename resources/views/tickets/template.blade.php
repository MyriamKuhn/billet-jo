<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Ticket – {{ $item->product_snapshot['product_name'] }}</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap');

        /* Styles écran */
        body {
            font-family: 'Poppins', Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0; padding: 0;
        }
        .container {
            max-width: 700px;
            margin: 40px auto;
            background: #0c1f2b;
            color: #f9e8c4;
            padding: 30px;
            border-radius: 10px;
        }
        .header { display: flex; justify-content: space-between; align-items: center; }
        .logo { max-height: 60px; }
        .event-title { font-size: 26px; font-weight: 700; }
        .section {
            background: #112832;
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
        }
        .section h3 { color: #70bbbb; margin-bottom: 15px; font-weight: 600; }
        .section p { margin: 6px 0; font-size: 14px; }
        .qr-code { text-align: center; margin-top: 30px; }
        .qr-code img { width: 180px; height: 180px; }
        .footer { text-align: center; margin-top: 30px; font-size: 12px; color: #888; }

        /* Styles impression */
        @media print {
            body, .container, .section { background: none !important; color: #000 !important; }
            .container { margin: 0; padding: 15px; border: 1px solid #000; border-radius: 0; }
            .section { padding: 10px; border: 1px solid #000; margin-top: 15px; }
            .qr-code img { width: 150px; height: 150px; }
            .logo { display: none; }
            .footer { color: #000 !important; }
        }
    </style>
</head>
<body>
    <div class="container">
        {{-- Header --}}
        <div class="header">
            <img src="{{ public_path('images/jo_logo.png') }}" alt="Logo" class="logo">
            <div class="event-title">{{ $item->product_snapshot['product_name'] }}</div>
        </div>

        {{-- Attendee Information --}}
        <div class="section">
            <h3>Attendee Information</h3>
            <p><strong>Name:</strong> {{ $user->firstname }} {{ $user->lastname }}</p>
            <p><strong>Email:</strong> {{ $user->email }}</p>
        </div>

        {{-- Event Details --}}
        @php $details = $item->product->product_details; @endphp
        <div class="section">
            <h3>Event Details</h3>
            <p><strong>Category:</strong> {{ $details['category'] }}</p>
            <p><strong>Date:</strong> {{ \Carbon\Carbon::parse($details['date'])->format('F j, Y') }}</p>
            <p><strong>Time:</strong> {{ $details['time'] }}</p>
            <p><strong>Location:</strong> {{ $details['location'] }}</p>
            <p><strong>Description:</strong> {{ $details['description'] }}</p>
            <p><strong>Seats:</strong> {{ $item->product_snapshot['quantity'] }}</p>
        </div>

        {{-- QR Code --}}
        <div class="qr-code">
            <p>Please present this QR code at the entrance</p>
            <img src="{{ $qrDataUri }}" alt="QR Code">
            <p><small>Ticket code: {{ $token }}</small></p>
        </div>

        {{-- Footer --}}
        <div class="footer">
            Thank you for your purchase – {{ config('app.name') }}<br>
            <small>This ticket is valid only for the event above.</small>
        </div>
    </div>
</body>
</html>
