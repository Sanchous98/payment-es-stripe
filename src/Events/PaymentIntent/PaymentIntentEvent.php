<?php

namespace PaymentSystem\Stripe\Events\PaymentIntent;

use EventSauce\EventSourcing\Serialization\SerializablePayload;
use Stripe\PaymentIntent;

abstract readonly class PaymentIntentEvent implements SerializablePayload
{
    public function __construct(public PaymentIntent $paymentIntent)
    {
    }

    public function toPayload(): array
    {
        return ['object' => $this->paymentIntent->toArray()];
    }

    public static function fromPayload(array $payload): static
    {
        return new static(PaymentIntent::constructFrom($payload['object']));
    }
}