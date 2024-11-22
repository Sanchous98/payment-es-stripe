<?php

namespace PaymentSystem\Stripe\Tests\Datasets;

use EventSauce\EventSourcing\AggregateRoot;
use EventSauce\EventSourcing\AggregateRootId;
use EventSauce\EventSourcing\ClassNameInflector;
use EventSauce\EventSourcing\DefaultHeadersDecorator;
use EventSauce\EventSourcing\DotSeparatedSnakeCaseInflector;
use EventSauce\EventSourcing\EventSourcedAggregateRootRepository;
use EventSauce\EventSourcing\Header;
use EventSauce\EventSourcing\Message;
use EventSauce\EventSourcing\MessageDecorator;
use EventSauce\EventSourcing\MessageDispatcher;
use EventSauce\EventSourcing\MessageRepository;
use EventSauce\EventSourcing\SynchronousMessageDispatcher;
use EventSauce\EventSourcing\UnableToReconstituteAggregateRoot;
use Generator;
use PaymentSystem\Stripe\PaymentMethodAggregateRoot;
use PaymentSystem\Stripe\Repositories\StripeTenderRepositoryInterface;
use PaymentSystem\Stripe\TokenAggregateRoot;
use Throwable;

/**
 * @extends EventSourcedAggregateRootRepository<PaymentMethodAggregateRoot|TokenAggregateRoot>
 */
class StripeTenderRepository extends EventSourcedAggregateRootRepository implements StripeTenderRepositoryInterface
{
    public function __construct(
        private MessageRepository $messages,
        private MessageDispatcher $dispatcher = new SynchronousMessageDispatcher(),
        private MessageDecorator $decorator = new DefaultHeadersDecorator(),
        private ClassNameInflector $classNameInflector = new DotSeparatedSnakeCaseInflector(),
    ) {
        parent::__construct('', $this->messages, $this->dispatcher, $this->decorator, $this->classNameInflector);
    }

    public function retrieve(AggregateRootId $aggregateRootId): object
    {
        try {
            $messages = $this->messages->retrieveAll($aggregateRootId);
            /** @var Message $event */
            $event = $messages->current();
            /** @var AggregateRoot $className */
            $className = $this->classNameInflector->typeToClassName($event->header(Header::AGGREGATE_ROOT_TYPE));

            return $className::reconstituteFromEvents($aggregateRootId, (function (Generator $messages) {
                foreach ($messages as $message) {
                    yield $message->payload();
                }

                return $messages->getReturn();
            })($messages));
        } catch (Throwable $throwable) {
            throw UnableToReconstituteAggregateRoot::becauseOf($throwable->getMessage(), $throwable);
        }
    }
}