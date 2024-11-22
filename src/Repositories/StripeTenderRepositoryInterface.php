<?php

namespace PaymentSystem\Stripe\Repositories;

use EventSauce\EventSourcing\AggregateRootRepository;
use PaymentSystem\Stripe\TenderInterface;

/**
 * @extends AggregateRootRepository<TenderInterface>
 */
interface StripeTenderRepositoryInterface extends AggregateRootRepository
{
}