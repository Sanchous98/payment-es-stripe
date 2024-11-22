<?php

namespace PaymentSystem\Stripe\Consumers;

use EventSauce\EventSourcing\Message;
use EventSauce\EventSourcing\MessageConsumer;
use PaymentSystem\Events\RefundCreated;
use PaymentSystem\Repositories\RefundRepositoryInterface;
use PaymentSystem\Stripe\Contract\ConfigProviderInterface;
use PaymentSystem\Stripe\Events\RefundCreated as StripeCreated;
use PaymentSystem\Stripe\Repositories\StripePaymentIntentRepositoryInterface;
use PaymentSystem\Stripe\Repositories\StripeRefundRepositoryInterface;
use Stripe\Exception\InvalidRequestException;
use Stripe\Service\RefundService;
use PaymentSystem\Stripe\RefundAggregateRoot as StripeRefundAggregateRoot;

readonly class RefundSaga implements MessageConsumer
{
    public function __construct(
        private ConfigProviderInterface $configProvider,
        private StripeRefundRepositoryInterface $stripeRefundRepository,
        private StripePaymentIntentRepositoryInterface $intentRepository,
        private RefundService $refundService,
        private RefundRepositoryInterface $refundRepository,
    ) {
    }

    public function handle(Message $message): void
    {
        $payload = $message->payload();

        switch ($payload::class) {
            case RefundCreated::class:
                $this->handleRefundCreated($payload, $message);
                return;
            case StripeCreated::class:
                $this->handleStripeCreated($message);
                return;
        }
    }

    protected function handleRefundCreated(RefundCreated $event, Message $message): void
    {
        $apiKey = $this->configProvider->getApiKey($message);
        $paymentIntent = $this->intentRepository->retrieve($event->paymentIntentId);

        try {
            $stripeRefund = $this->refundService->create([
                'amount' => $event->money->getAmount(),
                'payment_intent' => $paymentIntent->getStripePaymentIntent()->id,
            ], ['api_key' => $apiKey]);
        } catch (InvalidRequestException $e) {
            $this->refundRepository->persist(
                $this->refundRepository
                    ->retrieve($message->aggregateRootId())
                    ->decline($e->getMessage())
            );

            return;
        }

        $refund = StripeRefundAggregateRoot::create($message->aggregateRootId(), $stripeRefund);
        $this->stripeRefundRepository->persist($refund);
    }

    protected function handleStripeCreated(Message $message): void
    {
        $this->refundRepository->persist(
            $this->refundRepository
                ->retrieve($message->aggregateRootId())
                ->success()
        );
    }
}