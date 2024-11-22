<?php

namespace PaymentSystem\Stripe\Tests\Datasets;

use EventSauce\EventSourcing\ClassNameInflector;
use EventSauce\EventSourcing\EventSourcedAggregateRootRepository;
use EventSauce\EventSourcing\MessageDecorator;
use EventSauce\EventSourcing\MessageDispatcher;
use EventSauce\EventSourcing\MessageRepository;
use PaymentSystem\Stripe\PaymentIntentAggregateRoot;
use PaymentSystem\Stripe\Repositories\StripePaymentIntentRepositoryInterface;

class StripePaymentIntentRepository extends EventSourcedAggregateRootRepository implements StripePaymentIntentRepositoryInterface
{
    public function __construct(
        MessageRepository $messageRepository,
        MessageDispatcher $dispatcher = null,
        MessageDecorator $decorator = null,
        ClassNameInflector $classNameInflector = null
    ) {
        parent::__construct(
            PaymentIntentAggregateRoot::class,
            $messageRepository,
            $dispatcher,
            $decorator,
            $classNameInflector
        );
    }
}