<?php

namespace PaymentSystem\Stripe;

use EventSauce\EventSourcing\AggregateRoot;
use EventSauce\EventSourcing\AggregateRootBehaviour;
use EventSauce\EventSourcing\AggregateRootId;
use PaymentSystem\Stripe\Events\RefundCreated;
use Stripe\Refund;

class RefundAggregateRoot implements AggregateRoot
{
    use AggregateRootBehaviour;

    private Refund $stripeRefund;

    public function getStripeRefund(): Refund
    {
        return $this->stripeRefund;
    }

    public static function create(AggregateRootId $id, Refund $refund): static
    {
        $self = new static($id);
        $self->recordThat(new RefundCreated($refund));

        return $self;
    }

    protected function apply(RefundCreated $event): void
    {
        $this->stripeRefund = $event->refund;
        ++$this->aggregateRootVersion;
    }
}