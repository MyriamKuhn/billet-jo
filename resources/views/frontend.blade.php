<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Quick Start Guide</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Quick start guide for visitors, users, admins and employees.">
    <meta name="author" content="Myriam Kühn">
    <meta name="keywords" content="API, Quick Start, Guide, Visitor, User, Admin, Employee">
    <link rel="icon" href="/images/favicon32x32.png" sizes="32x32" type="image/png">
    <link rel="icon" href="/images/favicon16x16.png" sizes="16x16" type="image/png">
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

        img {
            width: 100%;
            max-width: 150px;
            margin-bottom: 1rem;
            cursor: zoom-in;
        }

        a, a:visited {
            color: #00bcd4; text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
    </style>
</head>

<body>
    <div class="container" id="top">
        <a href="{{ url('/') }}" class="back">← Back to Home</a>

        <h1>Quick Start Guide</h1>
        <p>This page offers a quick start guide to help you become familiar with all the key navigation points on the site.</p>
        <p>Check the live version at <a href="https://jo2024.mkcodecreations.dev/api/documentation">Deployed Site</a>.</p>

        <div class="toc-list">
            <a href="#common" class="toc-link">⚙️ Common</a>
            <a href="#visitor" class="toc-link">👤 Visitor</a>
            <a href="#user" class="toc-link">🔐 User</a>
            <a href="#admin" class="toc-link">🛠️ Admin</a>
            <a href="#employee" class="toc-link">👷 Employee</a>
        </div>

        <div class="section" id="common">
            <h2 data-icon="⚙️">1. Common</h2>
            <h3>1.1 Navigation</h3>
            <p>Use the top navigation bar to move between pages and access settings:</p>
            <ul>
                <li>Select your preferred language.</li>
                <li>Switch between light and dark theme for optimal readability.</li>
                <li>Monitor your cart icon to see the current item count.</li>
                <li>On mobile, tap the menu icon to open the drawer navigation.</li>
                <li>Scroll down to reveal the “Back to Top” button in the bottom right corner.</li>
            </ul>
            <a href="{{ asset('/images/top_navigation_desktop.png') }}" target="_blank" rel="noopener">
                <img src="{{ asset('/images/top_navigation_desktop.png') }}" alt="Top navigation for desktop example">
            </a>
            <a href="{{ asset('/images/mobile_navigation.png') }}" target="_blank" rel="noopener">
                <img src="{{ asset('/images/mobile_navigation.png') }}" alt="Top navigation for mobile example">
            </a>

            <h3>1.2 Header &amp; Footer</h3>
            <p>The header shows links to Home, Offers, Cart, and access.</p>
            <p>The footer provides quick links to legal pages (Terms, Privacy, Imprint) and the Contact page.</p>

            <h3>1.3 Home</h3>
            <p>The homepage welcomes everyone with an overview of upcoming events and a prominent call-to-action to view offers.</p>

            <h3>1.4 Offers</h3>
            <p>Browse all available offers complete with images and key details.</p>
            <p>Use the filters sidebar to narrow results by date, category, or location.</p>
            <p>Click “More Info” on any offer to open a detailed preview window.</p>
            <a href="{{ asset('/images/offers_desktop.png') }}" target="_blank" rel="noopener">
                <img src="{{ asset('/images/offers_desktop.png') }}" alt="Filter for desktop example">
            </a>
            <a href="{{ asset('/images/offers_mobile.png') }}" target="_blank" rel="noopener">
                <img src="{{ asset('/images/offers_mobile.png') }}" alt="Filter for mobile example">
            </a>
            <a href="{{ asset('/images/offers_info.png') }}" target="_blank" rel="noopener">
                <img src="{{ asset('/images/offers_info.png') }}" alt="More information example">
            </a>

            <h3>1.5 Cart</h3>
            <p>View and manage items in your cart from any page.</p>

            <h3>1.6 Legal &amp; Contact</h3>
            <p>Access our Terms of Service, Privacy Policy, and Imprint in the Legal section. Please review them before ordering.</p>
            <p>Visit the Contact page for support and inquiries.</p>
        </div>

        <div class="section" id="visitor">
            <h2 data-icon="👤">2. Visitor</h2>
            <h3>2.1 Cart</h3>
            <p>As a visitor, you can browse offers and add items to your cart, but you must be logged in to complete a purchase.</p>
            <p>If you click “Checkout” without being logged in, you will be redirected to the login page. After signing in, you will automatically return to your cart to proceed.</p>
            <p>On login the guest cart will automatically merge with the user cart, so no product is lost by logging.</p>
            <p class="note">Visitors can only explore offers and add items; ordering requires an account.</p>
            <a href="{{ asset('/images/cart_preview.png') }}" target="_blank" rel="noopener">
                <img src="{{ asset('/images/cart_preview.png') }}" alt="Cart preview example">
            </a>
            <a href="{{ asset('/images/cart_view.png') }}" target="_blank" rel="noopener">
                <img src="{{ asset('/images/cart_view.png') }}" alt="Cart view example">
            </a>

            <h3>2.2 Registration</h3>
            <p>Click the “Register” button in the navigation bar or on the login page to create an account.</p>
            <p>Fill in your details and submit the form. You will receive a verification email.</p>
            <p>Your password must have at least 15 characters, one uppercase letter, one lowercase letter, one number and one special character.</p>
            <p>Click the link in the email to activate your account.</p>
            <p class="note">If you don't receive the email, check your spam folder or try to log in and the email will be resend automatically or you can click “Resend Verification” on the login page.</p>

            <h3>2.3 Login</h3>
            <p>Click the “Login” button in the navigation bar to sign in.</p>
            <p>Enter your registered email and password. If two-factor authentication (2FA) is enabled, enter the code from your authenticator app.</p>
            <p>Once logged in, you can access your profile, your tickets, your bills and complete purchases.</p>
            <p class="note">Don't have an account? Register first to proceed.</p>

            <h3>2.4 Forgot Password</h3>
            <p>Click “Forgot Password?” on the login page or in the navigation and enter your email to receive a reset link.</p>
            <p>Follow the instructions in the email to set a new password.</p>
            <p class="note">If you don't see the email, check your spam folder or contact support.</p>
            <a href="{{ asset('/images/menu_visitor.png') }}" target="_blank" rel="noopener">
                <img src="{{ asset('/images/menu_visitor.png') }}" alt="Visitor navigation example">
            </a>
        </div>

        <!-- USER SECTION -->
        <div class="section" id="user">
            <h2 data-icon="🔐">3. User</h2>
            <h3>3.1 Sign In (with optional “Remember me” and 2FA via Google Authenticator)</h3>
            <p>As a registered user, you can log in to access your profile, manage your orders, and complete purchases.</p>
            <p>Click the “Login” button in the navigation bar or on the login page. By checking “Remember me”, you will stay logged in for 7 days.</p>
            <p>Enter your registered email and password. If two-factor authentication (2FA) is enabled, enter the code from your authenticator app.</p>
            <p>Once logged in, you can access your profile, your tickets, your invoices, and continue shopping.</p>
            <a href="{{ asset('/images/menu_login.png') }}" target="_blank" rel="noopener">
                <img src="{{ asset('/images/menu_login.png') }}" alt="Login example">
            </a>
            <a href="{{ asset('/images/menu_2fa.png') }}" target="_blank" rel="noopener">
                <img src="{{ asset('/images/menu_2fa.png') }}" alt="2FA example">
            </a>

            <h3>3.2 User Dashboard</h3>
            <p>After logging in, you will be redirected to your dashboard.</p>
            <p>Here you can view and update your personal information.</p>
            <p>You can change your first and last name.</p>
            <p class="note">Orders placed before you update your name will still be addressed to the previous name.</p>
            <p>You can also update your email address.</p>
            <p class="note">
                If you change your email, you’ll receive a verification link (valid for 1 hour) and a cancellation link (valid for 48 hours) in case you did not request the change.
            </p>
            <p>Finally, you can change your password by providing your current password and choosing a new one.</p>
            <p class="note">
                Your new password must be at least 15 characters long and include one uppercase letter, one lowercase letter, one number, and one special character.
            </p>
            <p>
                You can enable two-factor authentication here. Scan the QR code with your authenticator app (or enter the code manually). After enabling, you’ll receive recovery codes—please save them somewhere safe. You can use these codes to disable 2FA if you ever lose access to your authenticator app.
            </p>
            <p class="note">
                If you lose both your recovery codes and your authenticator app, you’ll need to contact support to disable two-factor authentication.
            </p>
            <a href="{{ asset('/images/user_dashboard.png') }}" target="_blank" rel="noopener">
                <img src="{{ asset('/images/user_dashboard.png') }}" alt="User Dashboard example">
            </a>

            <h3>3.3 My Orders</h3>
            <p>In the “My Orders” section, you can view all your past and current orders.</p>
            <p>Each order displays the date, total amount, and status (e.g., Pending, Completed).</p>
            <p>Click the icon to download the invoice for that order.</p>
            <p class="note">You can only view orders placed with this account. You may filter orders by status, date, or reference number.</p>
            <a href="{{ asset('/images/orders.png') }}" target="_blank" rel="noopener">
                <img src="{{ asset('/images/orders.png') }}" alt="Orders List example">
            </a>

            <h3>3.4 My Tickets</h3>
            <p>In the “My Tickets” section, you can view all tickets you’ve purchased.</p>
            <p>Each ticket shows a QR code for event entry, the ticket reference, event name, date, time, location, seat information, and status.</p>
            <p>You can also download the ticket and its corresponding invoice.</p>
            <p class="note">You can only view tickets purchased with this account. You may filter tickets by status and date.</p>
            <a href="{{ asset('/images/tickets.png') }}" target="_blank" rel="noopener">
                <img src="{{ asset('/images/tickets.png') }}" alt="Tickets List example">
            </a>

            <h3>3.5 Checkout</h3>
            <p>As a logged-in user, you can complete purchases. Go to your cart, accept the General Terms and Conditions of Sale, and click “Checkout”.</p>
            <p>On the checkout page, enter your card information (Stripe test mode) and your ZIP code.</p>
            <p>After providing your payment details, click “Pay” to finalize the purchase. Once complete, you’ll receive your tickets by email and can access both tickets and invoices in your dashboard.</p>
            <p class="note">
                For card payments you need a valid card number, expiration date, and CVC. The ZIP code is used for verification.
                For testing, you can use:
                <ul>
                <li><strong>Success:</strong> 4242 4242 4242 4242, exp. 12/35, CVC 253, ZIP 25365</li>
                <li><strong>Decline:</strong> 4000 0000 0000 0002, exp. 12/35, CVC 253, ZIP 25365</li>
                </ul>
                See <a href="https://docs.stripe.com/testing">Stripe’s testing documentation</a> for more test cards.
            </p>
            <p class="note">After successful payment, you will receive a confirmation email with your tickets and invoice attached.</p>
            <p class="note">The amount of available tickets is updated in real-time. If an offer is sold out, you will not be able to add it to your cart.</p>
            <a href="{{ asset('/images/card_payment.png') }}" target="_blank" rel="noopener">
                <img src="{{ asset('/images/card_payment.png') }}" alt="Card payment example">
            </a>

            <h3>3.6 Logout</h3>
            <p>To log out, click the “Logout” button in the navigation bar.</p>
            <p>You will be redirected to the homepage and any “Remember me” session will be cleared. To access protected areas again, you’ll need to log in.</p>
            <a href="{{ asset('/images/user_menu.png') }}" target="_blank" rel="noopener">
                <img src="{{ asset('/images/user_menu.png') }}" alt="User menu example">
            </a>
        </div>

        <!-- ADMIN SECTION -->
        <div class="section" id="admin">
            <h2 data-icon="🛠️">4. Admin</h2>
            <p>Coming soon...</p>
        </div>

        <!-- EMPLOYEE SECTION -->
        <div class="section" id="employee">
            <h2 data-icon="👷">5. Employee</h2>
            <p>Coming soon...</p>
        </div>

        <a href="{{ url('/') }}" class="bottom-back">← Back to Home</a>
    </div>

    <a href="#top" class="to-top">↑ Top</a>
</body>

</html>
