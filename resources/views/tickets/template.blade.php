<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <title>{{ __('ticket.ticket_title', ['product_name' => $item->product_snapshot['product_name']]) }}</title>
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
        .logo { max-height: 100px; }
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
            <img src="{{ asset('images/jo_logo.png') }}" alt="Logo" class="logo">
            <div class="event-title">{{ $item->product_snapshot['product_name'] }}</div>
        </div>

        {{-- Attendee Information --}}
        <div class="section">
            <h3>{{ __('ticket.attendee_info') }}</h3>
            <p><strong>{{ __('ticket.attendee_name') }}</strong> {{ $user->firstname }} {{ $user->lastname }}</p>
            <p><strong>{{ __('ticket.attendee_email') }}</strong> {{ $user->email }}</p>
        </div>

        {{-- Event Details --}}
        @php
            $snap = $item->product_snapshot;
            $details = $item->product->product_details;

            use Carbon\Carbon;
            // Fusionne date et time pour avoir un seul DateTime
            $dateTime = Carbon::parse($details['date'].' '.$details['time'])
                            ->locale(app()->getLocale());

            $timePattern = __('ticket.time_format');
        @endphp
        <div class="section">
            <h3>{{ __('ticket.event_details') }}</h3>
            <p><strong>{{ __('ticket.event_category') }}</strong> {{ $snap['ticket_type'] }}</p>
            <p><strong>{{ __('ticket.event_date') }}</strong> {{ $dateTime->isoFormat('LL') }}</p>
            <p><strong>{{ __('ticket.event_time') }}</strong> {{ $dateTime->isoFormat($timePattern) }}</p>
            <p><strong>{{ __('ticket.event_location') }}</strong> {{ $details['location'] }}</p>
            <p><strong>{{ __('ticket.event_places') }}</strong> {{ $snap['ticket_places'] }}</p>
        </div>

        {{-- QR Code --}}
        <div class="qr-code">
            <p>{{ __('ticket.qr_code') }}</p>
            <img src="{{ $qrDataUri }}" alt="QR Code">
            <p><small>{{ __('ticket.ticket_code', ['token' => $token]) }}</small></p>
        </div>

        {{-- Footer --}}
        <div class="footer">
            {{ __('ticket.thank_you', ['app_name' => config('app.name')]) }}<br>
            <small>{{ __('ticket.validity') }}</small>
        </div>
    </div>
</body>
</html>
