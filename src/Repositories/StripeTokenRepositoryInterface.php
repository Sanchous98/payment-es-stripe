<?php

namespace PaymentSystem\Stripe\Repositories;

use EventSauce\EventSourcing\AggregateRootId;
use PaymentSystem\Stripe\TokenAggregateRoot;

interface StripeTokenRepositoryInterface
{
    public function retrieve(AggregateRootId $aggregateRootId): TokenAggregateRoot;

    public function persist(TokenAggregateRoot $token): void;
}