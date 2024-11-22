<?php

namespace PaymentSystem\Stripe;

use EventSauce\EventSourcing\AggregateRootBehaviour;
use EventSauce\EventSourcing\Snapshotting\AggregateRootWithSnapshotting;
use EventSauce\EventSourcing\Snapshotting\Snapshot;
use PaymentSystem\SnapshotBehaviour;
use PaymentSystem\Stripe\Events\PaymentMethod\Attached;
use PaymentSystem\Stripe\Events\PaymentMethod\PaymentMethodEvent;
use PaymentSystem\Stripe\Events\PaymentMethod\Updated;
use Stripe\PaymentMethod;
use Stripe\SetupIntent;

class PaymentMethodAggregateRoot implements AggregateRootWithSnapshotting
{
    use SnapshotBehaviour;
    use AggregateRootBehaviour;

    private PaymentMethod $stripePaymentMethod;

    public function getStripePaymentMethod(): PaymentMethod
    {
        return $this->stripePaymentMethod;
    }

    public function attach(PaymentMethod $method): static
    {
        $this->recordThat(new Attached($method));

        return $this;
    }

    public function update(PaymentMethod $method): static
    {
        $this->recordThat(new Updated($method));

        return $this;
    }

    protected function apply(PaymentMethodEvent $event): void
    {
        $this->stripePaymentMethod = $event->paymentMethod;
    }

    protected function applySnapshot(Snapshot $snapshot): void
    {
        $this->stripePaymentMethod = PaymentMethod::constructFrom($snapshot->state());
    }

    protected function createSnapshotState(): array
    {
        return $this->stripePaymentMethod->toArray();
    }
}