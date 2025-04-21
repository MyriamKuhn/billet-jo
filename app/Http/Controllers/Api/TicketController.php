<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class TicketController extends Controller
{
        /**
     * @OA\Get(
     *     path="/api/ticket/ping",
     *     summary="Test route",
     *     tags={"Tickets"},
     *     @OA\Response(
     *         response=200,
     *         description="OK"
     *     )
     * )
     */
    public function ping() {
        return response()->json(['message' => 'pong']);
    }
}
