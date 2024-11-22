<?php

namespace PaymentSystem\Stripe\ValueObjects;

use InvalidArgumentException;

readonly class PaymentIntentId extends StringId
{
    public function __construct(string $id)
    {
        str_starts_with($id, 'pi_') || throw new InvalidArgumentException('invalid payment intent id');
        parent::__construct($id);
    }
}