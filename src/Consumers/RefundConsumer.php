<?php

namespace PaymentSystem\Stripe\Consumers;

use EventSauce\EventSourcing\EventConsumption\EventConsumer;
use EventSauce\EventSourcing\Message;
use PaymentSystem\Events\RefundCreated;
use PaymentSystem\Stripe\Repositories\PaymentIntentRepositoryInterface;
use PaymentSystem\Stripe\Repositories\RefundRepositoryInterface;
use Stripe\Refund;
use Stripe\Service\RefundService;

class RefundConsumer extends EventConsumer
{
    public function __construct(
        private readonly RefundRepositoryInterface $refundRepository,
        private readonly PaymentIntentRepositoryInterface $intentRepository,
        private readonly RefundService $refundService,
    )
    {
    }

    protected function handleRefundCreated(RefundCreated $event, Message $message): void
    {
        $apiKey = $message->header('X-Stripe-Key');
        $refund = $this->refundRepository->retrieve($message->aggregateRootId());
        $paymentIntent = $this->intentRepository->retrieve($refund->payment_intent_id);

        $stripeRefund = $this->refundService->create([
            'amount' => $event->money->getAmount(),
            'payment_intent' => $paymentIntent->getStripePaymentIntent()->id,
        ], ['apiKey' => $apiKey]);

        $this->refundRepository->persist($refund->create($stripeRefund));
    }
}