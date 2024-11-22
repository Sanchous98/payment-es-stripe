<?php

namespace PaymentSystem\Stripe\Tests;


use EventSauce\EventSourcing\AggregateRootId;

readonly class IntId implements AggregateRootId
{
    public function __construct(private int $id)
    {
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    public function toString(): string
    {
        return (string)$this->id;
    }

    public static function fromString(string $aggregateRootId): static
    {
        return new static((int) $aggregateRootId);
    }
}