<?php

namespace App\Swagger;

/**
 * @OA\OpenApi(
 *     @OA\Info(
 *         title="Paris 2024 Olympic Games Ticketing API",
 *         version="1.0.0",
 *         description="This API manages the entire ticket lifecycle for the Paris 2024 Olympic Games: user registration, shopping cart management, payment processing, ticket generation, and entry validation.

This project was developed as part of the **Bachelor's degree in Digital Solutions Development**. The architecture is based on a **modular Laravel monolith**, structured into internal services (users, tickets, cart, payment, etc.) organized as internal packages to ensure better organization, maintainability, and scalability.

The notification system, including emails and system messages, is managed internally through a dedicated Laravel service and is not exposed as a public API.",
 *         @OA\Contact(
 *             name="Myriam Kühn",
 *             email="myriam.kuehn@free.fr",
 *             url="https://myriamkuhn.com/"
 *         ),
 *         @OA\License(
 *             name="MIT",
 *             url="https://opensource.org/licenses/MIT"
 *         )
 *     ),
 *     @OA\Server(
 *         url="http://localhost:8000",
 *         description="Local development environment"
 *     ),
 *     @OA\Server(
 *         url="https://api-jo2024.mkcodecreations.dev",
 *         description="Production server (planned)"
 *     ),
 *     @OA\Tag(
 *         name="Users",
 *         description="Account management, authentication, roles, and profiles"
 *     ),
 *     @OA\Tag(
 *         name="Tickets",
 *         description="Ticket creation, generation, validation, and tracking"
 *     ),
 *     @OA\Tag(
 *         name="Payments",
 *         description="Payment processing and secure transactions"
 *     ),
 *     @OA\Tag(
 *         name="Cart",
 *         description="Management of items added to the shopping cart"
 *     ),
 *     @OA\Tag(
 *         name="Products",
 *         description="Management of products available for purchase"
 *     ),
 * )
 */
class Info {}
