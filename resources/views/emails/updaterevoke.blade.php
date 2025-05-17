<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('mail.title_email_update_request_cancel', ['app_name' => config('app.name')]) }}</title>
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
    </style>
</head>

<body>
    <div class="email-container">
        <img src="{{ asset('images/jo_logo.png') }}" alt="Logo" class="logo">
        <h1>{{ __('mail.greeting', ['lastname' => $user->lastname, 'firstname' => $user->firstname]) }}</h1>
        <p>{{ __('mail.line_1_email_update_request') }}</p>
        <p>{{ __('mail.line_2_email_update_request', ['email' => $newEmail]) }}</p>
        <p>{{ __('mail.line_3_email_update_request') }}</p>
        <a href="{{ $url }}" class="button">{{ __('mail.action_email_update_request') }}</a>
        <p>{{ __('mail.note_email_update_request') }}</p>
        <p>{{ __('mail.thank_you') }}</p>
        <p>{{ __('mail.team', ['app_name' => env('APP_NAME')]) }}</p>
        <div class="footer">
            {{ __('mail.footer_email_update_request') }}<br>
            <a href="{{ $url }}">{{ $url }}</a>
        </div>
    </div>
</body>

</html>
