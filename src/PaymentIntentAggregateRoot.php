<?php

namespace PaymentSystem\Stripe;

use EventSauce\EventSourcing\AggregateRootBehaviour;
use EventSauce\EventSourcing\Snapshotting\AggregateRootWithSnapshotting;
use EventSauce\EventSourcing\Snapshotting\Snapshot;
use PaymentSystem\Stripe\Events\PaymentIntent\Canceled;
use PaymentSystem\Stripe\Events\PaymentIntent\Created;
use PaymentSystem\Stripe\Events\PaymentIntent\PaymentIntentEvent;
use PaymentSystem\Stripe\Events\PaymentIntent\Succeeded;
use PaymentSystem\SnapshotBehaviour;
use Stripe\PaymentIntent;

class PaymentIntentAggregateRoot implements AggregateRootWithSnapshotting
{
    use SnapshotBehaviour;
    use AggregateRootBehaviour;

    private PaymentIntent $stripePaymentIntent;

    private const STATUSES_AFTER_CREATION = [
        PaymentIntent::STATUS_REQUIRES_ACTION,
        PaymentIntent::STATUS_REQUIRES_CAPTURE,
        PaymentIntent::STATUS_REQUIRES_CONFIRMATION,
        PaymentIntent::STATUS_REQUIRES_PAYMENT_METHOD,
    ];

    public function getStripePaymentIntent(): PaymentIntent
    {
        return $this->stripePaymentIntent;
    }

    public function create(PaymentIntent $intent): static
    {
        in_array($intent->status, self::STATUSES_AFTER_CREATION) || throw new \RuntimeException('payment intent is not created');

        $this->recordThat(new Created($intent));

        return $this;
    }

    public function cancel(PaymentIntent $intent): static
    {
        $intent->status === PaymentIntent::STATUS_CANCELED || throw new \RuntimeException('payment intent is not canceled');

        $this->recordThat(new Canceled($intent));

        return $this;
    }

    public function capture(PaymentIntent $intent): static
    {
        $intent->status === PaymentIntent::STATUS_SUCCEEDED || throw new \RuntimeException('payment intent is not captured');

        $this->recordThat(new Succeeded($intent));

        return $this;
    }

    protected function apply(PaymentIntentEvent $event): void
    {
        $this->stripePaymentIntent = $event->paymentIntent;
    }

    protected function applySnapshot(Snapshot $snapshot): void
    {
        $this->stripePaymentIntent = PaymentIntent::constructFrom($snapshot->state());
    }

    protected function createSnapshotState(): array
    {
        return $this->stripePaymentIntent->toArray();
    }
}