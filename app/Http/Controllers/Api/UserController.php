<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class UserController extends Controller
    {
        /**
     * @OA\Get(
     *     path="/api/user/ping",
     *     summary="Test route",
     *     tags={"Users"},
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
