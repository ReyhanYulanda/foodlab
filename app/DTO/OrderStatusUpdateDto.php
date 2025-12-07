<?php

namespace App\DTO;

use Illuminate\Http\Request;

class OrderStatusUpdateDto
{
    public function __construct(
        public int $id,
        public string $status
    ) {}

    public static function fromRequest(Request $request, int $id): self
    {
        return new self(
            id: $id,
            status: $request->input('status')
        );
    }
}
