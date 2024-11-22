<?php

namespace PaymentSystem\Stripe;

use Stripe\PaymentMethod;
use Stripe\Token;

interface TenderInterface
{
    public function getTender(): PaymentMethod|Token;
}