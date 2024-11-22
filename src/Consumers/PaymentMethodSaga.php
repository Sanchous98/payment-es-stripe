<?php

namespace PaymentSystem\Stripe\Consumers;

use EventSauce\EventSourcing\AggregateRootRepository;
use EventSauce\EventSourcing\Message;
use EventSauce\EventSourcing\MessageConsumer;
use PaymentSystem\Contracts\DecryptInterface;
use PaymentSystem\Enum\SourceEnum;
use PaymentSystem\Events\PaymentMethodCreated;
use PaymentSystem\Events\PaymentMethodUpdated;
use PaymentSystem\PaymentMethodAggregateRoot;
use PaymentSystem\Stripe\Events\PaymentMethodAttached;
use PaymentSystem\Stripe\Events\PaymentMethodAttached as StripeAttached;
use PaymentSystem\Stripe\Repositories\PaymentMethodRepositoryInterface as StripePaymentMethodRepositoryInterface;
use PaymentSystem\Stripe\ValueObjects\TokenId;
use PaymentSystem\ValueObjects\BillingAddress;
use PaymentSystem\ValueObjects\Token;
use RuntimeException;
use Stripe\Customer;
use Stripe\Exception\ApiErrorException;
use Stripe\PaymentMethod;
use Stripe\Service\PaymentMethodService;

readonly class PaymentMethodSaga implements MessageConsumer
{
    /**
     * @param StripePaymentMethodRepositoryInterface $stripePaymentMethodRepository
     * @param PaymentMethodService $paymentMethodService
     * @param DecryptInterface $decrypt
     * @param AggregateRootRepository<PaymentMethodAggregateRoot> $paymentMethodRepository
     */
    public function __construct(
        private StripePaymentMethodRepositoryInterface $stripePaymentMethodRepository,
        private PaymentMethodService $paymentMethodService,
        private DecryptInterface $decrypt,
        private AggregateRootRepository $paymentMethodRepository,
    ) {
    }

    public function handle(Message $message): void
    {
        switch ($message->payload()::class) {
            case PaymentMethodCreated::class:
                $this->handlePaymentMethodCreated($message->payload(), $message);
                return;
            case PaymentMethodUpdated::class:
                $this->handlePaymentMethodUpdated($message->payload(), $message);
                return;
            case PaymentMethodAttached::class:
                $this->handlePaymentMethodAttached($message->payload(), $message);
                return;
        }
    }

    protected function handlePaymentMethodCreated(PaymentMethodCreated $event, Message $message): void
    {
        $apiKey = $message->header('X-Stripe-Key');

        try {
            if ($event->source->getType() === SourceEnum::TOKEN && str_starts_with(
                    $event->source->unwrap()->tokenId,
                    'pm_'
                )) {
                $method = PaymentMethod::retrieve($event->source->unwrap()->tokenId, ['api_key' => $apiKey]);

                if ($method->customer === null) {
                    $method = $method->attach(['customer' => self::getCustomer($event->billingAddress, $apiKey)->id]);
                }
            } else {
                $cvc = $event->source->unwrap()->cvc?->getCvc($this->decrypt);
                $method = $this->paymentMethodService->create([
                    'billing_details' => self::address($event->billingAddress),
                    ...match ($event->source->getType()) {
                        SourceEnum::CARD => [
                            'type' => 'card',
                            'card' => [
                                'exp_month' => $event->source->unwrap()->expiration->format('n'),
                                'exp_year' => $event->source->unwrap()->expiration->format('y'),
                                'number' => $event->source->unwrap()->number->getNumber($this->decrypt),
                                ...(isset($cvc) ? ['cvc' => $cvc] : []),
                            ],
                        ],
                        SourceEnum::TOKEN => self::addToken($event->source->unwrap(), $apiKey),
                        SourceEnum::CASH => throw new RuntimeException('cash payment method is not supported'),
                    }
                ], ['api_key' => $apiKey]);

                $method = $method->attach(['customer' => self::getCustomer($event->billingAddress, $apiKey)->id]);
            }
        } catch (ApiErrorException) {
            $this->paymentMethodRepository->persist(
                $this->paymentMethodRepository
                    ->retrieve($message->aggregateRootId())
                    ->fail(),
            );

            return;
        }

        $paymentMethod = $this->stripePaymentMethodRepository
            ->retrieve($message->aggregateRootId())
            ->attach($method);

        $this->stripePaymentMethodRepository->persist($paymentMethod);
    }

    private static function getCustomer(BillingAddress $billingAddress, string $apiKey): Customer
    {
        return Customer::create(self::address($billingAddress), ['api_key' => $apiKey]);
    }

    private static function address(BillingAddress $billingAddress): array
    {
        return [
            'name' => "$billingAddress->firstName $billingAddress->lastName",
            'address' => [
                'city' => $billingAddress->city,
                'country' => (string)$billingAddress->country,
                'line1' => $billingAddress->addressLine,
                'line2' => $billingAddress->addressLineExtra,
                'postal_code' => $billingAddress->postalCode,
                'state' => (string)$billingAddress->state,
            ],
            'phone' => (string)$billingAddress->phone,
            'email' => (string)$billingAddress->email,
        ];
    }

    private static function addToken(Token $token, string $apiKey): array
    {
        $id = new TokenId($token->tokenId);
        $token = \Stripe\Token::retrieve($id, ['api_key' => $apiKey]);

        return [
            'type' => $token->type,
            $token->type => ['token' => $token->id],
        ];
    }

    protected function handlePaymentMethodUpdated(PaymentMethodUpdated $event, Message $message): void
    {
        $apiKey = $message->header('X-Stripe-Key');
        $paymentMethod = $this->stripePaymentMethodRepository->retrieve($message->aggregateRootId());

        $stripePaymentMethod = $this->paymentMethodService->update($paymentMethod->getStripePaymentMethod()->id, [
            'billing_details' => self::address($event->billingAddress)
        ], ['api_key' => $apiKey]);

        $paymentMethod->update($stripePaymentMethod);
        $this->stripePaymentMethodRepository->persist($paymentMethod);
    }

    protected function handlePaymentMethodAttached(StripeAttached $event, Message $message): void
    {
        $this->paymentMethodRepository->persist(
            $this->paymentMethodRepository
                ->retrieve($message->aggregateRootId())
                ->success()
        );
    }
}