<?php

namespace PaymentSystem\Stripe;

use EventSauce\EventSourcing\AggregateRootBehaviour;
use EventSauce\EventSourcing\AggregateRootId;
use EventSauce\EventSourcing\Snapshotting\AggregateRootWithSnapshotting;
use EventSauce\EventSourcing\Snapshotting\Snapshot;
use PaymentSystem\SnapshotBehaviour;
use PaymentSystem\Stripe\Events\PaymentIntentCanceled;
use PaymentSystem\Stripe\Events\PaymentIntentCreated;
use PaymentSystem\Stripe\Events\PaymentIntentEvent;
use PaymentSystem\Stripe\Events\PaymentIntentSucceeded;
use RuntimeException;
use Stripe\PaymentIntent;

class PaymentIntentAggregateRoot implements AggregateRootWithSnapshotting
{
    use SnapshotBehaviour;
    use AggregateRootBehaviour;

    private const STATUSES_AFTER_CREATION = [
        PaymentIntent::STATUS_REQUIRES_ACTION,
        PaymentIntent::STATUS_REQUIRES_CAPTURE,
        PaymentIntent::STATUS_REQUIRES_CONFIRMATION,
        PaymentIntent::STATUS_REQUIRES_PAYMENT_METHOD,
    ];
    private PaymentIntent $stripePaymentIntent;

    public function getStripePaymentIntent(): PaymentIntent
    {
        return $this->stripePaymentIntent;
    }

    public static function create(AggregateRootId $id, PaymentIntent $intent): static
    {
        in_array($intent->status, self::STATUSES_AFTER_CREATION) || throw new RuntimeException('payment intent is not created');

        $self = new static($id);
        $self->recordThat(new PaymentIntentCreated($intent));

        return $self;
    }

    public function cancel(PaymentIntent $intent): static
    {
        $intent->status === PaymentIntent::STATUS_CANCELED || throw new RuntimeException(
            'payment intent is not canceled'
        );

        $this->recordThat(new PaymentIntentCanceled($intent));

        return $this;
    }

    public function capture(PaymentIntent $intent): static
    {
        $intent->status === PaymentIntent::STATUS_SUCCEEDED || throw new RuntimeException(
            'payment intent is not captured'
        );

        $this->recordThat(new PaymentIntentSucceeded($intent));

        return $this;
    }

    protected function apply(PaymentIntentEvent $event): void
    {
        $this->stripePaymentIntent = $event->paymentIntent;
        ++$this->aggregateRootVersion;
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