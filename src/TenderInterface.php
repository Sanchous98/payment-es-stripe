<?php

namespace PaymentSystem\Stripe;

use EventSauce\EventSourcing\AggregateRoot;
use Stripe\PaymentMethod;
use Stripe\Token;

interface TenderInterface extends AggregateRoot
{
    public function getTender(): PaymentMethod|Token;
}