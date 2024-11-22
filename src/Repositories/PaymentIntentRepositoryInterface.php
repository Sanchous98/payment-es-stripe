<?php

namespace PaymentSystem\Stripe\Repositories;

use EventSauce\EventSourcing\AggregateRootRepository;
use PaymentSystem\Stripe\PaymentIntentAggregateRoot;

/**
 * @extends AggregateRootRepository<PaymentIntentAggregateRoot>
 */
interface PaymentIntentRepositoryInterface extends AggregateRootRepository
{
}