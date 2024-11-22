<?php

namespace PaymentSystem\Stripe\Contract;

use EventSauce\EventSourcing\Message;

interface ConfigProviderInterface
{
    public function getApiKey(Message $message): string;
}