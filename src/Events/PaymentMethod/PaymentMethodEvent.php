<?php

namespace PaymentSystem\Stripe\Events\PaymentMethod;

use EventSauce\EventSourcing\Serialization\SerializablePayload;
use Stripe\PaymentMethod;

abstract readonly class PaymentMethodEvent implements SerializablePayload
{

    public function __construct(public PaymentMethod $paymentMethod)
    {
    }

    public function toPayload(): array
    {
        return ['object' => $this->paymentMethod->toArray()];
    }

    public static function fromPayload(array $payload): static
    {
        return new static(PaymentMethod::constructFrom($payload['object']));
    }
}