<?php

namespace PaymentSystem\Stripe\Events;

use EventSauce\EventSourcing\Serialization\SerializablePayload;
use Stripe\PaymentIntent;

abstract readonly class PaymentIntentEvent implements SerializablePayload
{
    public function __construct(public PaymentIntent $paymentIntent)
    {
    }

    public static function fromPayload(array $payload): static
    {
        return new static(PaymentIntent::constructFrom($payload['object']));
    }

    public function toPayload(): array
    {
        return ['object' => $this->paymentIntent->toArray()];
    }
}