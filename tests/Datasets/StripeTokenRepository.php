<?php

namespace PaymentSystem\Stripe\Tests\Datasets;

use EventSauce\EventSourcing\ClassNameInflector;
use EventSauce\EventSourcing\EventSourcedAggregateRootRepository;
use EventSauce\EventSourcing\MessageDecorator;
use EventSauce\EventSourcing\MessageDispatcher;
use EventSauce\EventSourcing\MessageRepository;
use PaymentSystem\Stripe\Repositories\StripeTokenRepositoryInterface;
use PaymentSystem\Stripe\TokenAggregateRoot as StripeTokenAggregateRoot;

class StripeTokenRepository extends EventSourcedAggregateRootRepository implements StripeTokenRepositoryInterface
{
    public function __construct(
        MessageRepository $messageRepository,
        MessageDispatcher $dispatcher = null,
        MessageDecorator $decorator = null,
        ClassNameInflector $classNameInflector = null
    ) {
        parent::__construct(
            StripeTokenAggregateRoot::class,
            $messageRepository,
            $dispatcher,
            $decorator,
            $classNameInflector
        );
    }
}