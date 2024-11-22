<?php

namespace PaymentSystem\Stripe\Events\Refund;

use EventSauce\EventSourcing\Serialization\SerializablePayload;
use Stripe\Refund;

readonly class Created implements SerializablePayload
{
    public function __construct(public Refund $refund)
    {
    }

    public function toPayload(): array
    {
        return ['object' => $this->refund->toArray()];
    }

    public static function fromPayload(array $payload): static
    {
        return new static(Refund::constructFrom($payload['object']));
    }
}