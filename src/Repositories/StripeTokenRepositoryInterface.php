<?php

namespace PaymentSystem\Stripe\Repositories;

use EventSauce\EventSourcing\AggregateRootRepository;
use PaymentSystem\Stripe\TokenAggregateRoot;

/**
 * @extends AggregateRootRepository<TokenAggregateRoot>
 */
interface StripeTokenRepositoryInterface extends AggregateRootRepository
{
}