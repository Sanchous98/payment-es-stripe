<?php

namespace PaymentSystem\Stripe\Repositories;

use EventSauce\EventSourcing\AggregateRootId;
use PaymentSystem\Stripe\RefundAggregateRoot;

interface StripeRefundRepositoryInterface
{
    public function retrieve(AggregateRootId $aggregateRootId): RefundAggregateRoot;

    public function persist(RefundAggregateRoot $refund): void;
}