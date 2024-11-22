<?php

namespace PaymentSystem\Stripe\Tests\Datasets;

use EventSauce\EventSourcing\ClassNameInflector;
use EventSauce\EventSourcing\EventSourcedAggregateRootRepository;
use EventSauce\EventSourcing\MessageDecorator;
use EventSauce\EventSourcing\MessageDispatcher;
use EventSauce\EventSourcing\MessageRepository;
use PaymentSystem\Stripe\RefundAggregateRoot as StripeRefundAggregateRoot;
use PaymentSystem\Stripe\Repositories\StripeRefundRepositoryInterface;

class StripeRefundRepository extends EventSourcedAggregateRootRepository implements StripeRefundRepositoryInterface
{
    public function __construct(
        MessageRepository $messageRepository,
        MessageDispatcher $dispatcher = null,
        MessageDecorator $decorator = null,
        ClassNameInflector $classNameInflector = null
    ) {
        parent::__construct(
            StripeRefundAggregateRoot::class,
            $messageRepository,
            $dispatcher,
            $decorator,
            $classNameInflector
        );
    }
}