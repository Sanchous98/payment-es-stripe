<?php

namespace PaymentSystem\Stripe\Repositories;

use EventSauce\EventSourcing\AggregateRootRepository;
use PaymentSystem\Stripe\RefundAggregateRoot;

/**
 * @extends AggregateRootRepository<RefundAggregateRoot>
 */
interface StripeRefundRepositoryInterface extends AggregateRootRepository
{
}