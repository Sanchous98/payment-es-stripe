<?php

namespace PaymentSystem\Stripe\Consumers;

use EventSauce\EventSourcing\Message;
use EventSauce\EventSourcing\MessageConsumer;
use PaymentSystem\Contracts\DecryptInterface;
use PaymentSystem\Events\TokenCreated;
use PaymentSystem\Repositories\TokenRepositoryInterface;
use PaymentSystem\Stripe\Contract\ConfigProviderInterface;
use PaymentSystem\Stripe\Exceptions\UnsupportedSourceTypeException;
use PaymentSystem\Stripe\Repositories\StripeTokenRepositoryInterface;
use PaymentSystem\Stripe\TokenAggregateRoot as StripeTokenAggregateRoot;
use Stripe\Exception\ApiErrorException;
use Stripe\Service\TokenService;

readonly class TokenSaga implements MessageConsumer
{
    public function __construct(
        private ConfigProviderInterface $configProvider,
        private StripeTokenRepositoryInterface $stripeTokenRepository,
        private TokenRepositoryInterface $tokenRepository,
        private TokenService $tokenService,
        private DecryptInterface $decrypt,
    ) {
    }

    protected function handleTokenCreated(TokenCreated $event, Message $message): void
    {
        $apiKey = $this->configProvider->getApiKey($message);

        try {
            $stripeToken = $this->tokenService->create([
                $event->source::TYPE => match ($event->source::TYPE) {
                    'card' => [
                        'number' => $event->source->number->getNumber($this->decrypt),
                        'exp_month' => $event->source->expiration->format('n'),
                        'exp_year' => $event->source->expiration->format('Y'),
                        'cvc' => $event->source->cvc->getCvc($this->decrypt),
                    ],
                    default => throw new UnsupportedSourceTypeException(),
                },
            ], ['api_key' => $apiKey]);
        } catch (ApiErrorException $e) {
            $this->tokenRepository->persist(
                $this->tokenRepository
                    ->retrieve($message->aggregateRootId())
                    ->decline($e->getMessage())
            );

            return;
        }

        $this->stripeTokenRepository
            ->persist(StripeTokenAggregateRoot::create($message->aggregateRootId(), $stripeToken));
    }

    public function handle(Message $message): void
    {
        $payload = $message->payload();

        switch ($payload::class) {
            case TokenCreated::class:
                $this->handleTokenCreated($payload, $message);
                return;
        }
    }
}