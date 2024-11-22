<?php

namespace PaymentSystem\Stripe\Tests;

use EventSauce\EventSourcing\AggregateRootRepository;
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
use PaymentSystem\Stripe\Consumers\TokenSaga;
use PaymentSystem\Stripe\Repositories\StripeTenderRepositoryInterface;
use PaymentSystem\Stripe\Tests\Datasets\StripePaymentIntentRepository;
use PaymentSystem\Stripe\Tests\Datasets\StripePaymentMethodRepository;
use PaymentSystem\Stripe\Tests\Datasets\StripeRefundRepository;
use PaymentSystem\Stripe\Tests\Datasets\StripeTenderRepository;
use PaymentSystem\Stripe\Tests\Datasets\StripeTokenRepository;
use PaymentSystem\Stripe\Repositories\StripePaymentIntentRepositoryInterface;
use PaymentSystem\Stripe\Repositories\StripePaymentMethodRepositoryInterface;
use PaymentSystem\Stripe\Repositories\StripeTokenRepositoryInterface;
use PaymentSystem\Stripe\Repositories\StripeRefundRepositoryInterface;
use PaymentSystem\TokenAggregateRoot;
use PHPUnit\Framework\TestCase as BaseTestCase;
use ReflectionObject;
use Stripe\StripeClient;

abstract class TestCase extends BaseTestCase
{
    private static MessageDispatcher $dispatcher;
    private static MessageRepository $domainRepository;
    private static MessageRepository $stripeRepository;
    private static StripePaymentMethodRepositoryInterface $stripePaymentMethodRepository;
    private static StripeTokenRepositoryInterface $stripeTokenRepository;
    private static StripePaymentIntentRepositoryInterface $stripePaymentIntentRepository;
    private static StripeRefundRepositoryInterface $stripeRefundRepository;
    private static StripeTenderRepositoryInterface $stripeTenderRepository;
    /** @var AggregateRootRepository<PaymentMethodAggregateRoot> */
    private static AggregateRootRepository $domainPaymentMethodRepository;
    /** @var AggregateRootRepository<PaymentIntentAggregateRoot> */
    private static AggregateRootRepository $domainPaymentIntentRepository;
    /** @var AggregateRootRepository<RefundAggregateRoot> */
    private static AggregateRootRepository $domainRefundRepository;
    /** @var AggregateRootRepository<TokenAggregateRoot> */
    private static AggregateRootRepository $domainTokenRepository;

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

        self::$stripePaymentMethodRepository = new StripePaymentMethodRepository(self::$stripeRepository, self::$dispatcher);
        self::$stripePaymentIntentRepository = new StripePaymentIntentRepository(self::$stripeRepository, self::$dispatcher);
        self::$stripeRefundRepository = new StripeRefundRepository(self::$stripeRepository, self::$dispatcher);
        self::$stripeTokenRepository = new StripeTokenRepository(self::$stripeRepository, self::$dispatcher);
        self::$stripeTenderRepository = new StripeTenderRepository(self::$stripeRepository, self::$dispatcher);

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
        self::$domainTokenRepository = new EventSourcedAggregateRootRepository(
            TokenAggregateRoot::class,
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
            self::$stripeTokenRepository,
            $client->paymentMethods,
            $client->customers,
            $plainDecrypter,
            self::$domainPaymentMethodRepository,
            self::$domainTokenRepository,
        );
        $paymentIntentConsumer = new PaymentIntentSaga(
            self::$stripePaymentIntentRepository,
            self::$stripeTenderRepository,
            $client->paymentIntents,
            self::$domainPaymentIntentRepository
        );
        $refundConsumer = new RefundSaga(
            self::$stripeRefundRepository,
            self::$stripePaymentIntentRepository,
            $client->refunds,
            self::$domainRefundRepository
        );
        $tokenConsumer = new TokenSaga(
            self::$stripeTokenRepository,
            self::$domainTokenRepository,
            $client->tokens,
            $plainDecrypter,
        );

        $reflection = new ReflectionObject($messageDispatcher);
        $reflection->getProperty('consumers')->setValue($messageDispatcher, [
            $paymentMethodConsumer,
            $paymentIntentConsumer,
            $refundConsumer,
            $tokenConsumer,
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

    public function stripeTokenRepository(): StripeTokenRepositoryInterface
    {
        return self::$stripeTokenRepository;
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

    /**
     * @return AggregateRootRepository<TokenAggregateRoot>
     */
    public function domainTokenRepository(): AggregateRootRepository
    {
        return self::$domainTokenRepository;
    }
}
