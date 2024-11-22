<?php

use EventSauce\EventSourcing\DecoratingMessageDispatcher;
use EventSauce\EventSourcing\EventSourcedAggregateRootRepository;
use EventSauce\EventSourcing\InMemoryMessageRepository;
use EventSauce\EventSourcing\Message;
use EventSauce\EventSourcing\MessageDecorator;
use EventSauce\EventSourcing\SynchronousMessageDispatcher;
use PaymentSystem\Commands\CreatePaymentMethodCommandInterface;
use PaymentSystem\Contracts\DecryptInterface;
use PaymentSystem\PaymentMethodAggregateRoot;
use PaymentSystem\Stripe\Consumers\PaymentMethodConsumer;
use PaymentSystem\Stripe\Repositories\PaymentMethodRepositoryInterface;
use PaymentSystem\Stripe\Tests\IntId;
use PaymentSystem\ValueObjects\BillingAddress;
use PaymentSystem\ValueObjects\Source;
use Stripe\Service\PaymentMethodService;
use Stripe\StripeClient;
use PaymentSystem\Stripe\PaymentMethodAggregateRoot as StripePaymentMethodAggregateRoot;

class StripePaymentMethodRepository extends EventSourcedAggregateRootRepository implements PaymentMethodRepositoryInterface
{
}

class StripeApiKeyDecorator implements MessageDecorator
{
    public function decorate(Message $message): Message
    {
        return $message->withHeader('X-Stripe-Key', $_ENV['API_KEY']);
    }
}

test('payment methods flow', function (BillingAddress $billingAddress, Source $source) {
    $decrypt = $this->createStub(DecryptInterface::class);
    $decrypt->method('decrypt')->willReturnArgument(0);

    $messageDispatcher = new SynchronousMessageDispatcher();
    $repository = new StripePaymentMethodRepository(StripePaymentMethodAggregateRoot::class, new InMemoryMessageRepository(), new DecoratingMessageDispatcher($messageDispatcher, new StripeApiKeyDecorator()));
    $consumer = new PaymentMethodConsumer($repository, new PaymentMethodService(new StripeClient($_ENV['API_KEY'])), $decrypt);

    $reflection = new ReflectionObject($messageDispatcher);
    $reflection->getProperty('consumers')->setValue($messageDispatcher, [$consumer]);

    $paymentMethodRepository = new EventSourcedAggregateRootRepository(PaymentMethodAggregateRoot::class, new InMemoryMessageRepository(), new DecoratingMessageDispatcher($messageDispatcher, new StripeApiKeyDecorator()));
    $paymentMethod = $paymentMethodRepository->retrieve(new IntId(1));
    $command = $this->createStub(CreatePaymentMethodCommandInterface::class);
    $command->method('getSource')->willReturn($source);
    $command->method('getBillingAddress')->willReturn($billingAddress);
    $paymentMethod->create($command);
    $paymentMethodRepository->persist($paymentMethod);

    expect($repository->retrieve($paymentMethod->aggregateRootId()))->toBeInstanceOf(StripePaymentMethodAggregateRoot::class);
})->with('billing address', 'source');