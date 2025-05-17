<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Backend API Documentation</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description"
        content="Backend API endpoints overview for front-end developers: products, cart, auth, payments, invoices, tickets, admin.">
    <meta name="author" content="Your Name">
    <meta name="keywords" content="API documentation, backend, invoices, tickets, payments">
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

        h1 {
            color: #00bcd4;
            margin-top: 2.5rem;
            text-align: center;
        }

        h2,
        h3 {
            color: #00bcd4;
            margin-top: 2rem;
        }
        h4 {
            color: #00bcd4;
            margin-top: 2rem;
        }

        h2::before {
            content: attr(data-icon) " ";
        }

        .section {
            background: #1e1e1e;
            padding: 1rem 1.5rem;
            margin-bottom: 2rem;
            border-radius: 4px;
            border-left: 4px solid #00bcd4;
        }

        .note {
            background: #282828;
            padding: 0.5rem;
            border-radius: 4px;
            margin: 1rem 0;
            font-style: italic;
        }

        pre {
            background: #282828;
            padding: 1rem;
            overflow-x: auto;
            border-radius: 4px;
            margin-bottom: 1rem;
        }

        code {
            font-family: monospace;
            color: #c8e6c9;
            background: #1e1e1e;
            padding: 0.2rem 0.4rem;
            border-radius: 4px;
        }

        .toc-list {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin: 1rem 0;
        }

        .toc-link {
            color: #00bcd4;
            text-decoration: none;
            padding: 0.3rem 0.6rem;
            border: 1px solid #00bcd4;
            border-radius: 4px;
            transition: background 0.2s, color 0.2s;
        }

        .toc-link:hover {
            background: #00bcd4;
            color: #121212;
        }

        a.back,
        a.bottom-back,
        a.to-top {
            display: inline-block;
            color: #00bcd4;
            text-decoration: none;
            padding: 0.5rem 1rem;
            margin: 1rem 0;
            border: 1px solid #00bcd4;
            border-radius: 4px;
            transition: background 0.2s, color 0.2s;
        }

        a.back:hover,
        a.bottom-back:hover,
        a.to-top:hover {
            background: #00bcd4;
            color: #121212;
        }

        a.to-top {
            position: fixed;
            bottom: 1.5rem;
            right: 1.5rem;
        }

        ul {
            margin: 0.5rem 0 1rem 1.5rem;
        }

        p {
            margin: 0.5rem 0;
        }
    </style>
</head>

<body>
    <div class="container" id="top">
        <a href="{{ url('/') }}" class="back">← Back to Home</a>

        <h1>Backend API Documentation</h1>
        <p>This page gathers all backend API endpoints and front-end responsibilities.</p>
        <p>For more details, please refer to the <a href="https://api-jo2024.mkcodecreations.dev/api/documentation">Swagger documentation</a>.</p>

        <div class="toc-list">
            <a href="#internationalization" class="toc-link">🌐 Internationalization</a>
            <a href="#products" class="toc-link">📦 Products</a>
            <a href="#cart" class="toc-link">🛒 Shopping Cart</a>
            <a href="#auth" class="toc-link">🔐 Authentication & Users</a>
            <a href="#orders" class="toc-link">💳 Orders & Payments</a>
            <a href="#invoices" class="toc-link">🧾 Invoices & Tickets</a>
            <a href="#admin" class="toc-link">🛠️ Administration</a>
            <a href="#logout" class="toc-link">🔌 Logout</a>
        </div>

        <div class="section" id="internationalization">
            <h2 data-icon="🌐">1. Internationalization</h2>
            <p>All endpoints support the <code>Accept-Language</code> header for translations.</p>
            <p>The supported locales are English (en), German (de), French (fr).</p>
            <pre><code>Accept-Language: de</code></pre>
        </div>

        <div class="section" id="products">
            <h2 data-icon="📦">2. Products</h2>
            <h3>2.1 List in-stock products</h3>
            <p><code>GET /api/products</code><br> (filters & pagination in query)</p>
            <pre><code>fetch('/api/products?page=1&per_page=20&category=concert', { headers })
  .then(res =&gt; res.json());</code></pre>

            <h3>2.2 Get product details</h3>
            <p><code>GET /api/products/{id}</code></p>
        </div>

        <div class="section" id="cart">
            <h2 data-icon="🛒">3. Shopping Cart</h2>
            <h3>3.1 Guest cart</h3>
            <h4>3.1.1 View cart</h4>
            <code>GET /api/cart</code><br>
            <ul>
                <li><strong>Response contains:</strong> <pre><code>meta.guest_cart_id (UUID)</code></pre></li>
                <li><strong>Storage:</strong> Redis (TTL 120 min, reset on update). The server generates guest_cart_id on first call and then requires clients to include header:</li>
                <li><strong>Requires header:</strong> <code>X-Guest-Cart-ID: {guest_cart_id}</code></li>
            </ul>
            <h4>3.1.2 Update item</h4>
            <code>PATCH /api/cart/item/{product_id}</code>
            <ul>
                <li><strong>Requires header:</strong> <pre><code>X-Guest-Cart-ID: {guest_cart_id}</code></pre></li>
                <li><strong>Request body:</strong> <pre><code>{ "quantity":0|n }</code></pre> Always indicate the entire quantity</li>
            </ul>
            <h3>3.2 User cart</h3>
            <p>This cart is stored in database. After login, guest cart merges into user’s cart.</p>
            <p>All endpoints require <pre><code>Authorization: Bearer {token}</code></pre></p>
            <ul>
                <li><strong>View cart</strong>: <code>GET /api/cart</code></li>
                <li><strong>Update item</strong>: <code>PATCH /api/cart/item/{product_id}</code> Always indicate the entire quantity</li>
                <li><strong>Empty cart</strong>: <code>DELETE /api/cart/items</code></li>
            </ul>
            <p class="note">
                ⚠️ After any update (guest or user), always re-fetch the cart with <code>GET /api/cart</code>.
            </p>
        </div>

        <div class="section" id="auth">
            <h2 data-icon="🔐">4. Authentication & Users</h2>

            <h3>4.1 Registration</h3>
            <p><code>POST /api/auth/register</code>
                <ul>
                    <li><strong>Requires:</strong> Google ReCaptcha token and accept_terms</li>
                    <li><strong>Password rules:</strong> ≥15 chars, lower, upper, digit, special.</li>
                    <li><strong>Response:</strong> verification email sent (link valid 1h).</li>
                </ul>
            </p>

            <h4>4.1.1 Email verification</h4>
            <code>GET /api/auth/email/{id}/{hash}</code><br>
            Redirects to front URLs: <pre><code>/verification-result/success</code><br><code>/verification-result/invalid</code><br><code>/verification-result/already-verified</code><br><code>/verification-result/error</code></pre></p>
            <ul>
                <li><strong>If verification link fails:</strong> prompt user to login. Upon login, a new verification email is sent automatically in their locale.</li>
                <li><strong>Manual resend:</strong><code>POST /api/auth/email/resend</code> (Auth required via Bearer token).</li>
            </ul>
            <p class="note">⚠️ By problems with verification, please contact administration with your registered email.</p>

            <h3>4.2 Login & Two-Factor Authentication</h3>
            <code>POST /api/auth/login</code>
            <ul>
                <li>If 2FA enabled and no code: <pre><code>HTTP 400 { "message": "Two-factor authentication code is required", "code": "twofa_required" }</code></pre></li>
                <li>Resend same request with <code>twofa_code</code> to complete login</li>
                <li>On success: <pre><code>HTTP 200 { "message": "Logged in successfully", "token": "...", "user": { … }, "twofa_enabled": true|false }</code></pre></li>
            </ul>
            After login, guest cart merges into user’s cart.

            <h3>4.3 Enable/Disable 2FA</h3>
            <ul>
                <li><strong>Enable:</strong> <code>POST /api/auth/2fa/enable</code> (Auth) → { "qr_code_url", "secret" } (display QR + key)</li>
                <li><strong>Disable:</strong> <code>POST /api/auth/2fa/disable</code> (Auth) { "code" } is required</li>
            </ul>
            <p class="note">⚠️ 2FA is optional but recommended. For help disabling it, please contact administration with your registered email.</p>

            <h3>4.4 Password reset</h3>
            <ul>
                <li><strong>Request:</strong> <code>POST /api/auth/password/forgot</code> { email } sends reset front URL<pre><code>/password-reset?token=...&email=john@example.com&locale=fr</code></pre></li>
                <li><strong>Perform:</strong> <code>POST /api/auth/password/reset</code></li>
            </ul>

            <h3>4.5 Change password</h3>
            <code>POST /api/auth/password</code>

            <h3>4.6 Profile & Email change</h3>
            <ul>
                <li><strong>Get profile:</strong> <code>GET /api/users/me</code> (Auth) → includes twofa_enabled</li>
                <li><strong>Update profile:</strong> <code>PATCH /api/users/me</code> { first_name, last_name }</li>
                <li><strong>Change Email:</strong> <code>PATCH /api/auth/email</code> triggers change emails needs to be verified:</li>
                <ul>
                    <li>
                        <strong>New email:</strong> <code>GET /api/auth/email/change/verify?token=...&old_email=...</code><br>Link valid for 1h<br>
                        Redirects to front URLs: <pre><code>/verification-result/success</code><br><code>/verification-result/invalid</code><br><code>/verification-result/already-verified</code><br><code>/verification-result/error</code></pre></p>
                    </li>
                    <li>
                        <strong>Old email:</strong> <code>GET /api/auth/email/cancel/{token}/{old_email}</code><br>Is used to revoke the email change, link valid 48h<br>
                        Redirects to front URLs: <pre><code>/verification-result/success</code><br><code>/verification-result/invalid</code><br><code>/verification-result/already-verified</code><br><code>/verification-result/error</code></pre></p>
                    </li>
                </ul>
            </ul>
        </div>

        <div class="section" id="orders">
            <h2 data-icon="💳">5. Orders & Payments</h2>
            <h3>5.1 Initiate payment</h3>
            <code>POST /api/payments</code><br>(Auth) returns → { client_secret }<br>
            <p class="note">⚠️ This endpoint is called when the user clicks on the "Pay" button.</p>
            <h4>5.1.1 Front integration</h4>
                <p>After receiving <code>client_secret</code>, the front should:</p>
                <ul>
                    <li>Load Stripe.js</li>
                    <li>Initialize and confirm payment:</li>
                </ul>
                <pre><code>const stripe = await loadStripe('pk_test_...');
const elements = stripe.elements();
const card = elements.create('card');
card.mount('#card-element');
const { error, paymentIntent } = await stripe.confirmCardPayment(clientSecret, { payment_method: { card, billing_details: { name, email } } });</code></pre>
                </li>
            </ul>


            <h3>5.2 Stripe webhook</h3>
            <code>POST /api/payments/webhook</code>
            <p>Verify signature and handle events (invoices, tickets, email with tickets, stock updates).</p>

            <h3>5.3 Status & Clear cart</h3>
            <code>GET /api/payments/{uuid}</code>
            <p>Poll for <code>status</code> until it’s <code>paid</code>.</p>
            <p class="note">⚠️ Once paid, front-end must call <code>DELETE /api/cart/items</code> to clear the cart.</p>

            <h3>5.4 Refund (admin)</h3>
            <code>POST /api/payments/{uuid}/refund</code>
            <p>{ "amount": 25.00 } regenerates the invoice PDF.</p>
            <p class="note">⚠️ After refund the admin should update the status of the tickets too, it's not automatically.</p>
        </div>

        <div class="section" id="invoices">
            <h2 data-icon="🧾">6. Invoices & Tickets</h2>
            <h3>6.1 User invoices</h3>
            <ul>
                <li><strong>List:</strong> <code>GET /api/invoices/user</code> (supports filters & pagination)</li>
                <li><strong>Download Invoice:</strong> <code>GET /api/invoices/{filename}</code> (PDF)</li>
            </ul>

            <h3>6.2 Admin invoice download</h3>
            <p><code>GET /api/invoices/admin/{filename}</code></p>

            <h3>6.3 User tickets</h3>
            <ul>
                <li><strong>List:</strong> <code>GET /api/tickets/user</code> (supports filters & pagination)</li>
                <li><strong>Download Ticket:</strong> <code>GET /api/tickets/{filename}</code> (PDF)</li>
                <li><strong>Download QR:</strong> <code>GET /api/tickets/qr/{filename}</code> (PNG)</li>
            </ul>

            <h3>6.4 Admin tickets</h3>
            <ul>
                <li><strong>List:</strong> <code>GET /api/tickets</code> (supports filters & pagination)</li>
                <li><strong>Download Ticket:</strong> <code>GET /api/tickets/admin/{filename}</code> (PDF)</li>
                <li><strong>Download QR:</strong> <code>GET /api/tickets/admin/qr/{filename}</code> (QR)</li>
                <li><strong>Update status:</strong> <code>PUT /api/tickets/admin/{id}/status</code> { status }</li>
                <li><strong>Create free tickets:</strong> <code>POST /api/tickets</code> { user_id, quantity, locale }</li>
            </ul>
            <p class="note">⚠️ Only tickets marked “issued” are valid for scanning.</p>
            <p class="note">⚠️ For free tickets, the front-end should call <code>POST /api/tickets</code> with the user_id, locale and quantity. The backend will generate the invoices and the tickets and send them to the user via email.</p>
        </div>

        <div class="section" id="admin">
            <h2 data-icon="🛠️">7. Administration</h2>
            <h3>7.1 Products management</h3>
            <ul>
                <li><strong>List:</strong> <code>GET /api/products/all</code> (supports filters & pagination)</li>
                <li><strong>One product:</strong> <code>GET /api/products/{id}</code></li>
                <li><strong>Create product:</strong> <code>POST /api/products</code> { translations, price, sale, stock_quantity, images }</li>
                <li><strong>Update the whole product:</strong> <code>PUT /api/products/{id}</code></li>
                <li><strong>Update the pricing and the stock</strong> <code>PATCH /api/products/{id}/pricing</code></li>
            </ul>
            <p class="note">⚠️ All product data (translations, descriptions, etc.) must be provided for every supported locale.</p>
            <p class="note">⚠️ The <code>sale</code> field is a decimal (e.g. <code>0.10</code> for 10% discount).</p>

            <h3>7.2 Users management</h3>
            <ul>
                <li><strong>List:</strong> <code>GET /api/users</code> (supports filters & pagination)</li>
                <li><strong>One user:</strong> <code>GET /api/users/{id}</code></li>
                <li><strong>Update a user:</strong> <code>PATCH /api/users/{id}</code> { name, roles[], twofa_enabled }</li>
                <li><strong>Check pending email change:</strong> <code>GET /api/users/email/{id}</code></li>
            </ul>
            <p class="note">⚠️ The user can be deactivated by changing <code>is_active</code>.</p>

            <h3>7.3 Sales reporting</h3>
            <p><code>GET /api/admin/sales</code> (supports filters & pagination)</p>

            <h3>7.4 Employee account creation</h3>
            <p><code>POST /api/users/employees</code></p>

            <h3>7.5 Ticket scanning for employees</h3>
            <p><code>POST /api/tickets/scan/{token}</code></p>
            <p class="note">⚠️ After scanning the ticket is validated and mark as <code>used</code>.</p>

            <h3>7.6 Payments (admin)</h3>
            <ul>
                <li><code>GET /api/payments</code> (supports filters & pagination)</li>
                <li><code>POST /api/payments/{uuid}/refund</code></li>
            </ul>
            <p class="note">⚠️ The refund is not automatically done on the tickets, the admin must do it manually.</p>
        </div>

        <div class="section" id="logout">
            <h2 data-icon="🔌">8. Logout</h2>
            <p><code>POST /api/auth/logout</code> (Auth) – invalidate token.</p>
        </div>

        <a href="{{ url('/') }}" class="bottom-back">← Back to Home</a>
    </div>

    <a href="#top" class="to-top">↑ Top</a>
</body>

</html>
