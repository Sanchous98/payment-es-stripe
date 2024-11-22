<?php

namespace PaymentSystem\Stripe;

use EventSauce\EventSourcing\AggregateRoot;
use EventSauce\EventSourcing\AggregateRootBehaviour;
use EventSauce\EventSourcing\AggregateRootId;
use PaymentSystem\Stripe\Events\TokenCreated;
use Stripe\Token;

class TokenAggregateRoot implements AggregateRoot, TenderInterface
{
    use AggregateRootBehaviour;

    private Token $stripeToken;

    public function getStripeToken(): Token
    {
        return $this->stripeToken;
    }

    public function getTender(): Token
    {
        return $this->stripeToken;
    }

    public static function create(AggregateRootId $id, Token $token): static
    {
        $self = new static($id);
        $self->recordThat(new TokenCreated($token));

        return $self;
    }

    public function apply(TokenCreated $event): void
    {
        $this->stripeToken = $event->token;
        ++$this->aggregateRootVersion;
    }
}