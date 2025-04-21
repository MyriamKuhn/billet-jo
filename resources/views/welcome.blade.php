<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Welcome to the 2024 Olympics API</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            margin: 0;
            padding: 0;
            background-color: #121212;
            color: #f5f5f5;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            text-align: center;
            flex-direction: column;
        }
        .container {
            max-width: 600px;
            padding: 2rem;
        }
        img {
            max-width: 200px;
            filter: drop-shadow(0 0 10px rgba(0, 188, 212, 0.5));
        }
        h1 {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }
        p {
            font-size: 1.2rem;
            margin-bottom: 2rem;
        }
        a {
            display: inline-block;
            margin-top: 1rem;
            padding: 0.8rem 1.5rem;
            background-color: #00bcd4;
            color: #121212;
            text-decoration: none;
            border-radius: 8px;
            transition: background-color 0.3s;
        }
        a:hover {
            background-color: #00acc1;
        }
        footer {
            margin-top: 2rem;
            font-size: 1rem;
            color: #bbb;
        }
    </style>
</head>
<body>
    <div class="container">
        <img src="{{ asset('images/logo_api.png') }}" alt="Logo API JO 2024">
        <h1>Welcome to the 2024 Olympics API</h1>
        <p>This REST API allows you to manage tickets for the 2024 Olympic Games.</p>
        <a href="{{ url('/api/documentation') }}">View the documentation</a>
    </div>
    <footer>
        <p>&copy; 2024 Myriam Kühn. All rights reserved.</p>
    </footer>
</body>
</html>
