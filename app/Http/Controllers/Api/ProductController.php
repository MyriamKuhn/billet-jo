<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ProductController extends Controller
{
        /**
     * @OA\Get(
     *     path="/api/product/ping",
     *     summary="Test route",
     *     tags={"Products"},
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
