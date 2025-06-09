<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Welcome to the 2024 Olympics API</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Bachelor Project: ticket administration API for Olympic Games 2024 including payments, ticket generation and statistics.">
    <meta name="author"      content="Myriam Kühn">
    <meta name="keywords"    content="bachelor, API project, JO 2024, tickets, payment, sport">
    <link rel="icon" href="{{ asset('images/favicon32x32.png') }}" sizes="32x32" type="image/png">
    <link rel="icon" href="{{ asset('images/favicon16x16.png') }}" sizes="16x16" type="image/png">
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
            font-size: 2rem;
            margin-bottom: 1rem;
        }
        p {
            font-size: 1rem;
            margin-bottom: 2rem;
        }
        .btn {
            display: inline-block;
            color: #00bcd4;
            text-decoration: none;
            padding: 0.5rem 1rem;
            margin: 0.5rem 0;
            border: 1px solid #00bcd4;
            border-radius: 4px;
            transition: background 0.2s, color 0.2s;
        }
        .btn:hover {
            background: #00bcd4;
            color: #121212;
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

        <a href="{{ url('/api/documentation') }}" class="btn" target="_blank" rel="noopener noreferrer">Swagger Documentation</a>
        <a href="{{ url('/docs/backend') }}" class="btn">Backend Docs</a>
        <a href="{{ url('/docs/frontend') }}" class="btn">Frontend Docs</a>
        <a href="{{ url('/docs/architecture') }}" class="btn">Architecture Docs</a>
        <a href="https://github.com/MyriamKuhn/billet-jo/blob/main/README.md" class="btn" target="_blank" rel="noopener noreferrer">README BACKEND</a>
        <a href="https://github.com/MyriamKuhn/billetterie-jo/blob/main/README.md" class="btn" target="_blank" rel="noopener noreferrer">README FRONTEND</a>
    </div>
    <footer>
        <p>&copy; 2024 Myriam Kühn. All rights reserved.</p>
    </footer>
</body>
</html>
