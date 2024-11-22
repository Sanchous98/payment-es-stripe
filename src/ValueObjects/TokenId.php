<?php

namespace PaymentSystem\Stripe\ValueObjects;

use InvalidArgumentException;

readonly class TokenId extends StringId
{
    public function __construct(string $id)
    {
        str_starts_with($id, 'tok_') || throw new InvalidArgumentException('invalid token id');
        parent::__construct($id);
    }
}