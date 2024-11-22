<?php

namespace PaymentSystem\Stripe\Events;

use EventSauce\EventSourcing\Serialization\SerializablePayload;
use Stripe\PaymentMethod;

abstract readonly class PaymentMethodEvent implements SerializablePayload
{

    public function __construct(public PaymentMethod $paymentMethod)
    {
    }

    public static function fromPayload(array $payload): static
    {
        return new static(PaymentMethod::constructFrom($payload['object']));
    }

    public function toPayload(): array
    {
        return ['object' => $this->paymentMethod->toArray()];
    }
}