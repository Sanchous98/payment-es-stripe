<?php

namespace PaymentSystem\Stripe\ValueObjects;

use EventSauce\EventSourcing\AggregateRootId;

readonly class StringId implements AggregateRootId
{
    public function __construct(
        private string $id,
    ) {
    }

    public static function fromString(string $aggregateRootId): static
    {
        return new static($aggregateRootId);
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    public function toString(): string
    {
        return $this->id;
    }
}