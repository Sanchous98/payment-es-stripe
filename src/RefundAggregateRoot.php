<?php

namespace PaymentSystem\Stripe;

use EventSauce\EventSourcing\AggregateRoot;
use EventSauce\EventSourcing\AggregateRootBehaviour;
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

    public function create(Refund $refund): static
    {
        $this->recordThat(new RefundCreated($refund));

        return $this;
    }

    protected function applyRefundCreated(RefundCreated $event): void
    {
        $this->stripeRefund = $event->refund;
    }
}