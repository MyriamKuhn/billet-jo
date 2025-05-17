<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Architecture Documentation – 2024 Olympics API</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description"
        content="Architecture overview for the 2024 Olympics API: MCD, MLD, MPD, system diagram, and data flows.">
    <meta name="author" content="Myriam Kühn">
    <meta name="keywords" content="API architecture, MCD, MLD, MPD, plantUML, Olympic tickets">
    <link rel="icon" href="{{ asset('images/favicon32x32.png') }}" sizes="32x32" type="image/png">
    <link rel="icon" href="{{ asset('images/favicon16x16.png') }}" sizes="16x16" type="image/png">
    <style>
        body {
            margin: 0;
            padding: 0;
            background: #121212;
            color: #f5f5f5;
            font-family: 'Segoe UI', sans-serif;
            line-height: 1.6;
        }

        .container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 0 1rem;
        }

        h1,
        h2 {
            color: #00bcd4;
            margin-top: 2rem;
        }

        img {
            width: 100%;
            max-width: 600px;
            margin-bottom: 1rem;
            cursor: zoom-in;
        }

        a.back,
        a.toc-link,
        a.bottom-back,
        a.to-top {
            display: inline-block;
            color: #00bcd4;
            text-decoration: none;
            padding: 0.5rem 1rem;
            margin: 0.5rem 0;
            border: 1px solid #00bcd4;
            border-radius: 4px;
            transition: background 0.2s, color 0.2s;
        }

        a.back:hover,
        a.toc-link:hover,
        a.bottom-back:hover,
        a.to-top:hover {
            background: #00bcd4;
            color: #121212;
        }

        .toc-list {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin: 1rem 0;
        }

        a.to-top {
            position: fixed;
            bottom: 1.5rem;
            right: 1.5rem;
        }
    </style>
</head>

<body>
    <div class="container" id="top">
        <a href="{{ url('/') }}" class="back">← Back to Home</a>

        <h1>Architecture Overview</h1>
        <p>This page gathers all architecture deliverables for the 2024 Olympics API:</p>

        <div class="toc-list">
            <a href="#mcd" class="toc-link">MCD (Conceptual)</a>
            <a href="#mld" class="toc-link">MLD (Logical)</a>
            <a href="#mpd" class="toc-link">MPD (Physical)</a>
            <a href="#system" class="toc-link">System Diagram</a>
            <a href="#flow1" class="toc-link">Payment Flow</a>
            <a href="#flow2" class="toc-link">Webhook Flow</a>
        </div>

        <h2 id="mcd">1. Conceptual Data Model (MCD)</h2>
        <p>Entity–Relationship diagram:</p>
        <a href="{{ asset('images/jo-mcd.png') }}" target="_blank" rel="noopener">
            <img src="{{ asset('images/jo-mcd.png') }}" alt="MCD Diagram">
        </a>

        <h2 id="mld">2. Logical Data Model (MLD)</h2>
        <p>Relational schema with PK/FK:</p>
        <a href="{{ asset('images/jo-mld.png') }}" target="_blank" rel="noopener">
            <img src="{{ asset('images/jo-mld.png') }}" alt="MLD Diagram">
        </a>

        <h2 id="mpd">3. Physical Data Model (MPD)</h2>
        <p>SQL DDL snippets (MySQL InnoDB):</p>
        <a href="{{ asset('images/jo-mpd.png') }}" target="_blank" rel="noopener">
            <img src="{{ asset('images/jo-mpd.png') }}" alt="MPD Diagram">
        </a>

        <h2 id="system">4. System Architecture Diagram</h2>
        <p>Components, data stores, external APIs:</p>
        <a href="{{ asset('images/architecture_logicielle.png') }}" target="_blank" rel="noopener">
            <img src="{{ asset('images/architecture_logicielle.png') }}" alt="System Architecture Diagram">
        </a>

        <h2 id="flow1">5. Data Flow: Initiating a Payment</h2>
        <p>Sequence for checkout:</p>
        <a href="{{ asset('images/flux_paiement.png') }}" target="_blank" rel="noopener">
            <img src="{{ asset('images/flux_paiement.png') }}" alt="Payment Flow">
        </a>

        <h2 id="flow2">6. Data Flow: Webhook & Invoice/Ticket Generation</h2>
        <p>Sequence after Stripe webhook:</p>
        <a href="{{ asset('images/webhook_stripe_flux.png') }}" target="_blank" rel="noopener">
            <img src="{{ asset('images/webhook_stripe_flux.png') }}" alt="Webhook Flow">
        </a>

        <div style="margin:2rem 0;">
            <a href="{{ url('/') }}" class="bottom-back">← Back to Home</a>
        </div>
    </div>

    <a href="#top" class="to-top">↑ Top</a>
</body>

</html>
