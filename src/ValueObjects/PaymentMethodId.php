<?php

namespace PaymentSystem\Stripe\ValueObjects;

use InvalidArgumentException;

readonly class PaymentMethodId extends StringId
{
    public function __construct(string $id)
    {
        str_starts_with($id, 'pm_') || throw new InvalidArgumentException('invalid payment method id');
        parent::__construct($id);
    }
}