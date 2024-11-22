<?php

namespace PaymentSystem\Stripe\Repositories;

use EventSauce\EventSourcing\AggregateRootRepository;
use PaymentSystem\Stripe\TokenAggregateRoot;

/**
 * @extends AggregateRootRepository<TokenAggregateRoot>
 */
interface TokenRepositoryInterface extends AggregateRootRepository
{
}