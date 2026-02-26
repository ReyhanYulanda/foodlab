<?php

namespace App\Repositories;

use App\Models\Checkout;

class CheckoutRepository
{
    public function create(array $data): Checkout
    {
        return Checkout::create($data);
    }
}
