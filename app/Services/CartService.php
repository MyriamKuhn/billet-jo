<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\User;

class CartService
{
    /**
     * Create a new cart for the user if it doesn't exist.
     *
     * @param  \App\Models\User  $user
     * @return \App\Models\Cart
     */
    public function createCartForUser(User $user)
    {
        if ($user->cart) {
            // If the user already has a cart, return it
            return $user->cart;
        }

        // If the user doesn't have a cart, create one
        return Cart::create([
            'user_id' => $user->id,
        ]);
    }
}
