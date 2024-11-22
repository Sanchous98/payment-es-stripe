<?php

namespace PaymentSystem\Stripe\Consumers;

use EventSauce\EventSourcing\AggregateRootRepository;
use EventSauce\EventSourcing\Message;
use EventSauce\EventSourcing\MessageConsumer;
use PaymentSystem\Events\RefundCreated;
use PaymentSystem\RefundAggregateRoot;
use PaymentSystem\Stripe\Events\RefundCreated as StripeCreated;
use PaymentSystem\Stripe\Repositories\PaymentIntentRepositoryInterface;
use PaymentSystem\Stripe\Repositories\RefundRepositoryInterface;
use Stripe\Exception\InvalidRequestException;
use Stripe\Service\RefundService;

readonly class RefundSaga implements MessageConsumer
{
    /**
     * @param RefundRepositoryInterface $stripeRefundRepository
     * @param PaymentIntentRepositoryInterface $intentRepository
     * @param RefundService $refundService
     * @param AggregateRootRepository<RefundAggregateRoot> $refundRepository
     */
    public function __construct(
        private RefundRepositoryInterface $stripeRefundRepository,
        private PaymentIntentRepositoryInterface $intentRepository,
        private RefundService $refundService,
        private AggregateRootRepository $refundRepository,
    ) {
    }

    public function handle(Message $message): void
    {
        switch ($message->payload()::class) {
            case RefundCreated::class:
                $this->handleRefundCreated($message->payload(), $message);
                return;
            case StripeCreated::class:
                $this->handleStripeCreated($message);
                return;
        }
    }

    protected function handleRefundCreated(RefundCreated $event, Message $message): void
    {
        $apiKey = $message->header('X-Stripe-Key');
        $refund = $this->stripeRefundRepository->retrieve($message->aggregateRootId());
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

        $this->stripeRefundRepository->persist($refund->create($stripeRefund));
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