<?php

namespace PaymentSystem\Stripe;

use EventSauce\EventSourcing\AggregateRootBehaviour;
use EventSauce\EventSourcing\AggregateRootId;
use EventSauce\EventSourcing\Snapshotting\AggregateRootWithSnapshotting;
use EventSauce\EventSourcing\Snapshotting\Snapshot;
use PaymentSystem\SnapshotBehaviour;
use PaymentSystem\Stripe\Events\PaymentMethodAttached;
use PaymentSystem\Stripe\Events\PaymentMethodEvent;
use PaymentSystem\Stripe\Events\PaymentMethodUpdated;
use Stripe\PaymentMethod;
use Stripe\Token;

class PaymentMethodAggregateRoot implements AggregateRootWithSnapshotting, TenderInterface
{
    use SnapshotBehaviour;
    use AggregateRootBehaviour;

    private PaymentMethod $stripePaymentMethod;

    public function getStripePaymentMethod(): PaymentMethod
    {
        return $this->stripePaymentMethod;
    }

    public function getTender(): PaymentMethod
    {
        return $this->stripePaymentMethod;
    }

    public static function attach(AggregateRootId $id, PaymentMethod $method): static
    {
        $self = new static($id);
        $self->recordThat(new PaymentMethodAttached($method));

        return $self;
    }

    public function update(PaymentMethod $method): static
    {
        $this->recordThat(new PaymentMethodUpdated($method));

        return $this;
    }

    protected function apply(PaymentMethodEvent $event): void
    {
        $this->stripePaymentMethod = $event->paymentMethod;
        ++$this->aggregateRootVersion;
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