<?php

namespace PaymentSystem\Stripe\Repositories;

use EventSauce\EventSourcing\AggregateRootId;
use PaymentSystem\Stripe\PaymentMethodAggregateRoot;

interface StripePaymentMethodRepositoryInterface
{
    public function retrieve(AggregateRootId $aggregateRootId): PaymentMethodAggregateRoot;

    public function persist(PaymentMethodAggregateRoot $paymentMethod): void;
}