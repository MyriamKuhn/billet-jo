<?php

namespace App\Swagger;

/**
 * @OA\OpenApi(
 *   @OA\Info(
 *     title="Paris 2024 Olympic Games Ticketing API",
 *     version="1.0.0",
 *     description="
This API manages the entire ticket lifecycle for the Paris 2024 Olympic Games, covering:

- User registration and profile management

- Shopping cart and payment processing

- Ticket generation and entry validation


Developed as part of a Bachelor's in Digital Solutions Development, it follows a modular Laravel monolith architecture,
organized into internal packages (users, tickets, cart, payment, etc.) for maintainability and scalability.

Notifications (emails and system messages) are handled internally via a dedicated Laravel service and are not exposed publicly.

Access is secured by a restrictive CORS policy and token-based authentication (Laravel Sanctum).

The API supports multiple languages (English, French, German) injected in the header and is designed to be user-friendly and developer-friendly.",
 *     @OA\Contact(name="Myriam Kühn", email="myriam.kuehn@free.fr", url="https://myriamkuhn.com/"),
 *     @OA\License(name="MIT", url="https://opensource.org/licenses/MIT")
 *   ),
 *   @OA\ExternalDocumentation(
 *     description="Full API documentation",
 *     url="https://docs.paris2024.example.com"
 *   ),
 *
 *   @OA\Server(url="http://localhost:8000", description="Local dev"),
 *   @OA\Server(url="https://api-jo2024.mkcodecreations.dev", description="Prod"),
 *
 *   @OA\Components(
 *     @OA\SecurityScheme(
 *       securityScheme="bearerAuth",
 *       type="http",
 *       scheme="bearer",
 *       bearerFormat="JWT",
 *       description="Use `Bearer {token}` in Authorization header"
 *     ),
 *
 *    @OA\Response(
 *      response="BadRequest",
 *      description="Bad request",
 *      @OA\JsonContent(
 *        required={"message","code"},
 *        @OA\Property(property="message", type="string", example="Bad request"),
 *        @OA\Property(property="code",    type="string", example="bad_request")
 *        )
 *    ),
 *     @OA\Response(
 *       response="Unauthenticated",
 *       description="Authentication required",
 *       @OA\JsonContent(
 *         required={"message","code"},
 *         @OA\Property(property="message", type="string", example="Authentication required"),
 *         @OA\Property(property="code",    type="string", example="unauthenticated")
 *       )
 *     ),
 *     @OA\Response(
 *       response="Forbidden",
 *       description="Forbidden",
 *       @OA\JsonContent(
 *         required={"message","code"},
 *         @OA\Property(property="message", type="string", example="You do not have permission to perform this action"),
 *         @OA\Property(property="code",    type="string", example="forbidden")
 *       )
 *     ),
 *     @OA\Response(
 *       response="NotFound",
 *       description="Resource not found",
 *       @OA\JsonContent(
 *         required={"message","code"},
 *         @OA\Property(property="message", type="string", example="Resource not found"),
 *         @OA\Property(property="code",    type="string", example="not_found")
 *       )
 *     ),
 *     @OA\Response(
 *       response="MethodNotAllowed",
 *       description="Method not allowed",
 *       @OA\JsonContent(
 *        required={"message","code"},
 *        @OA\Property(property="message", type="string", example="Method not allowed"),
 *        @OA\Property(property="code",    type="string", example="method_not_allowed")
 *        )
 *     ),
 *     @OA\Response(
 *       response="CSRFTokenMismatch",
 *       description="CSRF token mismatch",
 *       @OA\JsonContent(
 *        required={"message","code"},
 *        @OA\Property(property="message", type="string", example="CSRF token mismatch"),
 *        @OA\Property(property="code",    type="string", example="csrf_token_mismatch")
 *       )
 *     ),
 *     @OA\Response(
 *       response="ValidationError",
 *       description="Validation error",
 *       @OA\JsonContent(
 *         required={"message", "code", "errors"},
 *         @OA\Property(property="message", type="string", example="The given data was invalid"),
 *         @OA\Property(property="code",    type="string", example="validation_error"),
 *         @OA\Property(
 *           property="errors",
 *           type="object",
 *           @OA\AdditionalProperties(
 *             type="array",
 *             @OA\Items(type="string")
 *           )
 *         )
 *       )
 *     ),
 *     @OA\Response(
 *       response="TooManyRequests",
 *       description="Too many requests",
 *       @OA\JsonContent(
 *        required={"message","code"},
 *        @OA\Property(property="message", type="string", example="Too many requests"),
 *        @OA\Property(property="code",    type="string", example="too_many_requests")
 *       )
 *     ),
 *     @OA\Response(
 *       response="InternalError",
 *       description="Internal server error",
 *       @OA\JsonContent(
 *         required={"message","code"},
 *         @OA\Property(property="message", type="string", example="Unexpected error"),
 *         @OA\Property(property="code",    type="string", example="internal_error")
 *       )
 *     ),
 *     @OA\Response(
 *       response="ServiceUnavailable",
 *       description="Service temporarily unavailable",
 *       @OA\JsonContent(
 *         required={"message","code"},
 *         @OA\Property(property="message", type="string", example="Service temporarily unavailable"),
 *         @OA\Property(property="code",    type="string", example="service_unavailable")
 *       )
 *     ),
 *     @OA\Response(
 *       response="NoContent",
 *       description="Operation successful, no content"
 *     ),
 *  @OA\Response(
 *      response="DatabaseError",
 *      description="Database error",
 *      @OA\JsonContent(
 *          required={"message","code"},
 *          @OA\Property(property="message", type="string", example="Database error"),
 *          @OA\Property(property="code",    type="string", example="database_error")
 *      )
 *  ),
 *  @OA\Response(
 *      response="TicketAlreadyProcessed",
 *      description="This ticket was already processed",
 *          @OA\JsonContent(
 *              required={"status","timestamp","user","event","code","message"},
 *              @OA\Property(property="status",    type="string", example="used"),
 *              @OA\Property(property="timestamp", type="string", format="date-time", example="2024-07-26T19:30:00Z"),
 *
 *              @OA\Property(
 *                  property="user",
 *                  type="object",
 *                  @OA\Property(property="firstname", type="string", example="Jean"),
 *                  @OA\Property(property="lastname",  type="string", example="Dupont"),
 *                  @OA\Property(property="email",     type="string", format="email", example="jean.dupont@example.com")
 *              ),
 *
 *              @OA\Property(
 *                  property="event",
 *                  type="object",
 *                  @OA\Property(property="name",     type="string", example="Opening Ceremony"),
 *                  @OA\Property(property="date",     type="string", format="date", example="2024-07-26"),
 *                  @OA\Property(property="time",     type="string", example="19h30 (accès recommandé dès 18h00)"),
 *                  @OA\Property(property="location", type="string", example="Stade de France, Saint-Denis")
 *              ),
 *
 *              @OA\Property(property="code",    type="string", example="ticket_already_processed"),
 *              @OA\Property(property="message", type="string", example="This ticket was already used on 2024-07-26T19:30:00Z")
 *          )
 *  ),
 *  @OA\Response(
 *      response="UserNotFound",
 *      description="User not found",
 *      @OA\JsonContent(
 *          required={"message","code","redirect_url"},
 *          @OA\Property(property="message",      type="string", example="User not found"),
 *          @OA\Property(property="code",         type="string", example="user_not_found"),
 *          @OA\Property(property="redirect_url", type="string", format="url", example="https://frontend.app/verification-result/invalid")
 *      )
 *  ),
 *  @OA\Response(
 *      response="InvalidVerificationLink",
 *      description="Invalid verification link",
 *      @OA\JsonContent(
 *          required={"message","code","redirect_url"},
 *          @OA\Property(property="message",      type="string", example="Invalid verification link"),
 *          @OA\Property(property="code",         type="string", example="invalid_verification_link"),
 *          @OA\Property(property="redirect_url", type="string", format="url", example="https://frontend.app/verification-result/invalid")
 *      )
 *  ),
 *  @OA\Response(
 *      response="AlreadyVerified",
 *      description="Email is already verified",
 *      @OA\JsonContent(
 *          required={"message","code","redirect_url"},
 *          @OA\Property(property="message",      type="string", example="Email is already verified"),
 *          @OA\Property(property="code",         type="string", example="already_verified"),
 *          @OA\Property(property="redirect_url", type="string", format="url", example="https://frontend.app/verification-result/already-verified")
 *      )
 *  ),
 *  @OA\Response(
 *      response="VerificationTokenMissing",
 *      description="Invalid or expired verification token",
 *      @OA\JsonContent(
 *          required={"message","code","redirect_url"},
 *          @OA\Property(property="message",      type="string", example="Invalid or expired verification token"),
 *          @OA\Property(property="code",         type="string", example="verification_token_missing"),
 *          @OA\Property(property="redirect_url", type="string", format="url", example="https://frontend.app/verification-result/invalid")
 *      )
 *  ),
 *  @OA\Response(
 *      response="EmailUpdateNotFound",
 *      description="Email request not found",
 *      @OA\JsonContent(
 *          required={"message","code","redirect_url"},
 *          @OA\Property(property="message",      type="string", example="Email request not found"),
 *          @OA\Property(property="code",         type="string", example="email_not_found"),
 *          @OA\Property(property="redirect_url", type="string", format="url", example="https://frontend.app/verification-result/invalid")
 *      )
 *  ),
 *
 * @OA\Parameter(
 *     parameter="AcceptLanguageHeader",
 *     name="Accept-Language",
 *     in="header",
 *     description="Language of the local response. Supported values : `en`, `fr`, `de`. Default `en`.",
 *     required=false,
 *     @OA\Schema(
 *       type="string",
 *       enum={"en","fr","de"},
 *       default="en"
 *     )
 *   )
 * ),
 *
 *   @OA\Tag(name="Authentication", description="User authentication and registration"),
 *   @OA\Tag(name="Users",          description="Operations related to user management"),
 *   @OA\Tag(name="Tickets",        description="Ticket creation, generation, validation, and tracking"),
 *   @OA\Tag(name="Payments",       description="Payment processing and secure transactions"),
 *   @OA\Tag(name="Invoices",       description="Invoice generation and download"),
 *   @OA\Tag(name="Carts",          description="Operations about shopping cart"),
 *   @OA\Tag(name="Products",       description="Product management, including categories and details")
 * )
 */
class Info {}
