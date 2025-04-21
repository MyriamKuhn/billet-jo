<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
        /**
     * @OA\Get(
     *     path="/api/payment/ping",
     *     summary="Test route",
     *     tags={"Payments"},
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
