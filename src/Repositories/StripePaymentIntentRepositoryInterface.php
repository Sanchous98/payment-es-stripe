<?php

namespace PaymentSystem\Stripe\Repositories;

use EventSauce\EventSourcing\AggregateRootId;
use PaymentSystem\Stripe\PaymentIntentAggregateRoot;

interface StripePaymentIntentRepositoryInterface
{
    public function retrieve(AggregateRootId $aggregateRootId): PaymentIntentAggregateRoot;

    public function persist(PaymentIntentAggregateRoot $paymentIntent): void;
}