<?php

namespace PaymentSystem\Stripe\Repositories;

use EventSauce\EventSourcing\AggregateRootRepository;
use PaymentSystem\Stripe\PaymentMethodAggregateRoot;

/**
 * @extends AggregateRootRepository<PaymentMethodAggregateRoot>
 */
interface PaymentMethodRepositoryInterface extends AggregateRootRepository
{
}