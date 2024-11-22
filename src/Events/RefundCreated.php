<?php

namespace PaymentSystem\Stripe\Events;

use EventSauce\EventSourcing\Serialization\SerializablePayload;
use Stripe\Refund;

readonly class RefundCreated implements SerializablePayload
{
    public function __construct(public Refund $refund)
    {
    }

    public static function fromPayload(array $payload): static
    {
        return new static(Refund::constructFrom($payload['object']));
    }

    public function toPayload(): array
    {
        return ['object' => $this->refund->toArray()];
    }
}