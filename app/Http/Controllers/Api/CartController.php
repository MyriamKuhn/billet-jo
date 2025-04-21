<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CartController extends Controller
{
        /**
     * @OA\Get(
     *     path="/api/cart/ping",
     *     summary="Test route",
     *     tags={"Cart"},
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
