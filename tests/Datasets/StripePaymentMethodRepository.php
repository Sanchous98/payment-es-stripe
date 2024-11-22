<?php

namespace PaymentSystem\Stripe\Tests\Datasets;

use EventSauce\EventSourcing\ClassNameInflector;
use EventSauce\EventSourcing\EventSourcedAggregateRootRepository;
use EventSauce\EventSourcing\MessageDecorator;
use EventSauce\EventSourcing\MessageDispatcher;
use EventSauce\EventSourcing\MessageRepository;
use PaymentSystem\Stripe\PaymentMethodAggregateRoot;
use PaymentSystem\Stripe\Repositories\StripePaymentMethodRepositoryInterface;

class StripePaymentMethodRepository extends EventSourcedAggregateRootRepository implements StripePaymentMethodRepositoryInterface
{
    public function __construct(
        MessageRepository $messageRepository,
        MessageDispatcher $dispatcher = null,
        MessageDecorator $decorator = null,
        ClassNameInflector $classNameInflector = null
    ) {
        parent::__construct(
            PaymentMethodAggregateRoot::class,
            $messageRepository,
            $dispatcher,
            $decorator,
            $classNameInflector
        );
    }
}