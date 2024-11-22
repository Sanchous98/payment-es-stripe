<?php

namespace PaymentSystem\Stripe\Tests;

use EventSauce\EventSourcing\AggregateRootRepository;
use EventSauce\EventSourcing\ClassNameInflector;
use EventSauce\EventSourcing\DecoratingMessageDispatcher;
use EventSauce\EventSourcing\EventSourcedAggregateRootRepository;
use EventSauce\EventSourcing\InMemoryMessageRepository;
use EventSauce\EventSourcing\Message;
use EventSauce\EventSourcing\MessageDecorator;
use EventSauce\EventSourcing\MessageDispatcher;
use EventSauce\EventSourcing\MessageRepository;
use EventSauce\EventSourcing\SynchronousMessageDispatcher;
use PaymentSystem\Contracts\DecryptInterface;
use PaymentSystem\PaymentIntentAggregateRoot;
use PaymentSystem\PaymentMethodAggregateRoot;
use PaymentSystem\RefundAggregateRoot;
use PaymentSystem\Stripe\Consumers\PaymentIntentSaga;
use PaymentSystem\Stripe\Consumers\PaymentMethodSaga;
use PaymentSystem\Stripe\Consumers\RefundSaga;
use PaymentSystem\Stripe\Consumers\StripeRefundConsumer;
use PaymentSystem\Stripe\PaymentIntentAggregateRoot as StripePaymentIntentAggregateRoot;
use PaymentSystem\Stripe\PaymentMethodAggregateRoot as StripePaymentMethodAggregateRoot;
use PaymentSystem\Stripe\RefundAggregateRoot as StripeRefundAggregateRoot;
use PaymentSystem\Stripe\Repositories\PaymentIntentRepositoryInterface as StripePaymentIntentRepositoryInterface;
use PaymentSystem\Stripe\Repositories\PaymentMethodRepositoryInterface as StripePaymentMethodRepositoryInterface;
use PaymentSystem\Stripe\Repositories\RefundRepositoryInterface as StripeRefundRepositoryInterface;
use PHPUnit\Framework\TestCase as BaseTestCase;
use ReflectionObject;
use Stripe\StripeClient;

abstract class TestCase extends BaseTestCase
{
    private static MessageDispatcher $dispatcher;
    private static MessageRepository $domainRepository;
    private static MessageRepository $stripeRepository;
    private static StripePaymentMethodRepositoryInterface $stripePaymentMethodRepository;
    private static StripePaymentIntentRepositoryInterface $stripePaymentIntentRepository;
    private static StripeRefundRepositoryInterface $stripeRefundRepository;
    /** @var AggregateRootRepository<PaymentMethodAggregateRoot> */
    private static AggregateRootRepository $domainPaymentMethodRepository;
    /** @var AggregateRootRepository<PaymentIntentAggregateRoot> */
    private static AggregateRootRepository $domainPaymentIntentRepository;
    /** @var AggregateRootRepository<RefundAggregateRoot> */
    private static AggregateRootRepository $domainRefundRepository;

    public static function setUpBeforeClass(): void
    {
        $stripeApiKeyDecorator = new class implements MessageDecorator {
            public function decorate(Message $message): Message
            {
                return $message->withHeader('X-Stripe-Key', $_ENV['API_KEY']);
            }
        };

        self::$dispatcher = new DecoratingMessageDispatcher(
            $messageDispatcher = new SynchronousMessageDispatcher(),
            $stripeApiKeyDecorator
        );
        self::$domainRepository = new InMemoryMessageRepository();
        self::$stripeRepository = new InMemoryMessageRepository();

        self::$stripePaymentMethodRepository = new class(self::$stripeRepository, self::$dispatcher) extends
            EventSourcedAggregateRootRepository implements StripePaymentMethodRepositoryInterface {
            public function __construct(
                MessageRepository $messageRepository,
                MessageDispatcher $dispatcher = null,
                MessageDecorator $decorator = null,
                ClassNameInflector $classNameInflector = null
            ) {
                parent::__construct(
                    StripePaymentMethodAggregateRoot::class,
                    $messageRepository,
                    $dispatcher,
                    $decorator,
                    $classNameInflector
                );
            }
        };
        self::$stripePaymentIntentRepository = new class(self::$stripeRepository, self::$dispatcher) extends
            EventSourcedAggregateRootRepository implements StripePaymentIntentRepositoryInterface {
            public function __construct(
                MessageRepository $messageRepository,
                MessageDispatcher $dispatcher = null,
                MessageDecorator $decorator = null,
                ClassNameInflector $classNameInflector = null
            ) {
                parent::__construct(
                    StripePaymentIntentAggregateRoot::class,
                    $messageRepository,
                    $dispatcher,
                    $decorator,
                    $classNameInflector
                );
            }
        };
        self::$stripeRefundRepository = new class(self::$stripeRepository, self::$dispatcher) extends
            EventSourcedAggregateRootRepository implements StripeRefundRepositoryInterface {
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
        };

        self::$domainPaymentMethodRepository = new EventSourcedAggregateRootRepository(
            PaymentMethodAggregateRoot::class, self::$domainRepository, self::$dispatcher
        );
        self::$domainPaymentIntentRepository = new EventSourcedAggregateRootRepository(
            PaymentIntentAggregateRoot::class, self::$domainRepository, self::$dispatcher
        );
        self::$domainRefundRepository = new EventSourcedAggregateRootRepository(
            RefundAggregateRoot::class,
            self::$domainRepository,
            self::$dispatcher
        );

        $plainDecrypter = new class implements DecryptInterface {
            public function decrypt(string $data): string
            {
                return $data;
            }
        };

        $client = new StripeClient($_ENV['API_KEY']);
        $paymentMethodConsumer = new PaymentMethodSaga(
            self::$stripePaymentMethodRepository,
            $client->paymentMethods,
            $plainDecrypter,
            self::$domainPaymentMethodRepository,
        );
        $paymentIntentConsumer = new PaymentIntentSaga(
            self::$stripePaymentIntentRepository,
            self::$stripePaymentMethodRepository,
            $client->paymentIntents,
            self::$domainPaymentIntentRepository
        );
        $refundConsumer = new RefundSaga(
            self::$stripeRefundRepository,
            self::$stripePaymentIntentRepository,
            $client->refunds,
            self::$domainRefundRepository
        );

        $reflection = new ReflectionObject($messageDispatcher);
        $reflection->getProperty('consumers')->setValue($messageDispatcher, [
            $paymentMethodConsumer,
            $paymentIntentConsumer,
            $refundConsumer,
        ]);
    }

    public function stripePaymentMethodRepository(): StripePaymentMethodRepositoryInterface
    {
        return self::$stripePaymentMethodRepository;
    }

    public function stripePaymentIntentRepository(): StripePaymentIntentRepositoryInterface
    {
        return self::$stripePaymentIntentRepository;
    }

    public function stripeRefundRepository(): StripeRefundRepositoryInterface
    {
        return self::$stripeRefundRepository;
    }

    /**
     * @return AggregateRootRepository<PaymentMethodAggregateRoot>
     */
    public function domainPaymentMethodRepository(): AggregateRootRepository
    {
        return self::$domainPaymentMethodRepository;
    }

    /**
     * @return AggregateRootRepository<PaymentIntentAggregateRoot>
     */
    public function domainPaymentIntentRepository(): AggregateRootRepository
    {
        return self::$domainPaymentIntentRepository;
    }

    /**
     * @return AggregateRootRepository<RefundAggregateRoot>
     */
    public function domainRefundRepository(): AggregateRootRepository
    {
        return self::$domainRefundRepository;
    }
}
