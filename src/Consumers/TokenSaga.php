<?php

namespace PaymentSystem\Stripe\Consumers;

use EventSauce\EventSourcing\AggregateRootRepository;
use EventSauce\EventSourcing\Message;
use EventSauce\EventSourcing\MessageConsumer;
use PaymentSystem\Contracts\DecryptInterface;
use PaymentSystem\Events\PaymentMethodSucceeded;
use PaymentSystem\Events\TokenCreated;
use PaymentSystem\PaymentMethodAggregateRoot;
use PaymentSystem\Stripe\Exceptions\UnsupportedSourceTypeException;
use PaymentSystem\Stripe\Repositories\StripeTokenRepositoryInterface;
use PaymentSystem\TokenAggregateRoot;
use PaymentSystem\Stripe\TokenAggregateRoot as StripeTokenAggregateRoot;
use Stripe\Exception\ApiErrorException;
use Stripe\Service\TokenService;
use Stripe\Token;

readonly class TokenSaga implements MessageConsumer
{
    /**
     * @param StripeTokenRepositoryInterface $stripeTokenRepository
     * @param AggregateRootRepository<TokenAggregateRoot> $tokenRepository
     * @param DecryptInterface $decrypt
     */
    public function __construct(
        private StripeTokenRepositoryInterface $stripeTokenRepository,
        private AggregateRootRepository $tokenRepository,
        private TokenService $tokenService,
        private DecryptInterface $decrypt,
    ) {
    }

    protected function handleTokenCreated(TokenCreated $event, Message $message): void
    {
        $apiKey = $message->header('X-Stripe-Key');

        try {
            $stripeToken = $this->tokenService->create([
                $event->card::TYPE => match ($event->card::TYPE) {
                    'card' => [
                        'number' => $event->card->number->getNumber($this->decrypt),
                        'exp_month' => $event->card->expiration->format('n'),
                        'exp_year' => $event->card->expiration->format('Y'),
                        'cvc' => $event->card->cvc->getCvc($this->decrypt),
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