<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('mail.tickets_generated_subject') }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', Arial, sans-serif;
            background-color: #f4f4f4;
            padding: 30px;
        }

        .email-container {
            background-color: #0c1f2b;
            color: #f9e8c4;
            max-width: 600px;
            margin: 40px auto;
            padding: 40px 20px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        .logo {
            width: 100px;
            margin: 0 auto 30px auto;
        }

        h1 {
            font-size: 28px;
            margin-bottom: 25px;
            color: #f9e8c4;
        }

        p {
            font-size: 16px;
            margin-bottom: 20px;
            line-height: 1.6;
        }

        .button {
            background-color: #70bbbb;
            color: #0c1f2b;
            padding: 14px 28px;
            text-decoration: none;
            font-weight: bold;
            border-radius: 5px;
            display: inline-block;
            margin: 30px 0;
            transition: background-color 0.3s ease;
        }

        .button:hover {
            background-color: #5aa7a7;
        }

        .footer {
            margin-top: 30px;
            font-size: 12px;
            color: #888;
        }

        .footer a {
            color: #70bbbb;
            text-decoration: none;
            word-wrap: break-word;
        }

        .ticket-list {
            text-align: left;
            margin: 20px auto;
            max-width: 400px;
        }

        .ticket-item {
            background-color: #1a2e3f;
            border-radius: 5px;
            padding: 10px;
            margin-bottom: 10px;
        }

        .ticket-item p {
            margin: 4px 0;
        }

        .ticket-item code {
            background: #f9e8c4;
            color: #0c1f2b;
            padding: 2px 4px;
            border-radius: 3px;
        }
    </style>
</head>

<body>
    <div class="email-container">
        <img src="{{ asset('images/jo_logo.png') }}" alt="Logo" class="logo">

        <h1>{{ __('mail.greeting', ['firstname' => $user->firstname, 'lastname' => $user->lastname]) }}</h1>
        <p>{{ __('mail.tickets_line1') }}</p>

        <div class="ticket-list">
            @foreach($tickets as $ticket)
                <div class="ticket-item">
                    <p><strong>{{ __('mail.event') }}:</strong> {{ $ticket->product_snapshot['product_name'] }}</p>
                    <p><strong>{{ __('mail.ticket_code') }}:</strong> <code>{{ $ticket->token }}</code></p>
                </div>
            @endforeach
        </div>

        <a href="{{ $clientUrl }}" class="button">{{ __('mail.view_tickets') }}</a>

        <p>{{ __('mail.tickets_note') }}</p>
        <p>{{ __('mail.thank_you') }}</p>
        <p>{{ __('mail.team', ['app_name' => config('app.name')]) }}</p>

        <div class="footer">
            {{ __('mail.footer_ticket') }}<br>
            <a href="{{ $clientUrl }}">{{ $clientUrl }}</a>
        </div>
    </div>
</body>

</html>
