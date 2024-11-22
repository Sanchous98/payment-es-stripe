<?php

namespace PaymentSystem\Stripe\Events;

use EventSauce\EventSourcing\Serialization\SerializablePayload;
use Stripe\Token;

readonly class TokenCreated implements SerializablePayload
{
    public function __construct(public Token $token)
    {
    }

    public static function fromPayload(array $payload): static
    {
        return new static(Token::constructFrom($payload['object']));
    }

    public function toPayload(): array
    {
        return ['object' => $this->token->toArray()];
    }
}