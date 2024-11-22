<?php

namespace PaymentSystem\Stripe\Repositories;

use EventSauce\EventSourcing\AggregateRootId;
use PaymentSystem\Stripe\TenderInterface;

interface StripeTenderRepositoryInterface
{
    public function retrieve(AggregateRootId $aggregateRootId): TenderInterface;

    public function persist(TenderInterface $tender): void;
}