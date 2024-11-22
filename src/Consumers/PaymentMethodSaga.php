<?php

namespace PaymentSystem\Stripe\Consumers;

use EventSauce\EventSourcing\Message;
use EventSauce\EventSourcing\MessageConsumer;
use PaymentSystem\Contracts\DecryptInterface;
use PaymentSystem\Events\PaymentMethodCreated;
use PaymentSystem\Events\PaymentMethodUpdated;
use PaymentSystem\Repositories\PaymentMethodRepositoryInterface;
use PaymentSystem\Repositories\TokenRepositoryInterface;
use PaymentSystem\Stripe\Contract\ConfigProviderInterface;
use PaymentSystem\Stripe\Events\PaymentMethodAttached;
use PaymentSystem\Stripe\PaymentMethodAggregateRoot as StripePaymentMethodAggregateRoot;
use PaymentSystem\Stripe\Events\PaymentMethodAttached as StripeAttached;
use PaymentSystem\Stripe\Exceptions\UnsupportedSourceTypeException;
use PaymentSystem\Stripe\Repositories\StripePaymentMethodRepositoryInterface;
use PaymentSystem\Stripe\Repositories\StripeTokenRepositoryInterface;
use PaymentSystem\TokenAggregateRoot;
use PaymentSystem\ValueObjects\BillingAddress;
use PaymentSystem\ValueObjects\CreditCard;
use Stripe\Customer;
use Stripe\Exception\ApiErrorException;
use Stripe\Service\CustomerService;
use Stripe\Service\PaymentMethodService;

readonly class PaymentMethodSaga implements MessageConsumer
{
    public function __construct(
        private ConfigProviderInterface $configProvider,
        private StripePaymentMethodRepositoryInterface $stripePaymentMethodRepository,
        private StripeTokenRepositoryInterface $stripeTokenRepository,
        private PaymentMethodService $paymentMethodService,
        private CustomerService $customerService,
        private DecryptInterface $decrypt,
        private PaymentMethodRepositoryInterface $paymentMethodRepository,
        private TokenRepositoryInterface $tokenRepository,
    ) {
    }

    public function handle(Message $message): void
    {
        $payload = $message->payload();

        switch ($payload::class) {
            case PaymentMethodCreated::class:
                $this->handlePaymentMethodCreated($payload, $message);
                return;
            case PaymentMethodUpdated::class:
                $this->handlePaymentMethodUpdated($payload, $message);
                return;
            case PaymentMethodAttached::class:
                $this->handlePaymentMethodAttached($payload, $message);
                return;
        }
    }

    protected function handlePaymentMethodCreated(PaymentMethodCreated $event, Message $message): void
    {
        $apiKey = $this->configProvider->getApiKey($message);

        try {
            $data = ['billing_details' => self::address($event->billingAddress)];

            if ($event->tokenId !== null) {
                $this->tokenRepository->persist(
                    $this->tokenRepository
                        ->retrieve($event->tokenId)
                        ->use(function (TokenAggregateRoot $aggregateRoot) use($apiKey, $event, $data, $message) {
                            $token = $this->stripeTokenRepository->retrieve($aggregateRoot->aggregateRootId());

                            $data['type'] = $token->getStripeToken()->type;
                            $data[$token->getStripeToken()->type]['token'] = $token->getStripeToken()->id;
                            $method = $this->paymentMethodService->create($data, ['api_key' => $apiKey]);
                            $method = $method->attach(['customer' => $this->getCustomer($event->billingAddress, $apiKey)->id]);

                            $paymentMethod = StripePaymentMethodAggregateRoot::attach($message->aggregateRootId(), $method);

                            $this->stripePaymentMethodRepository->persist($paymentMethod);
                        }),
                );
            } else {
                $source = $event->source;
                $data['type'] = $source::TYPE;
                $data[$source::TYPE] = match ($source::class) {
                    CreditCard::class => [
                        'number' => $source->number->getNumber($this->decrypt),
                        'exp_month' => $source->expiration->format('n'),
                        'exp_year' => $source->expiration->format('Y'),
                        'cvc' => $source->cvc->getCvc($this->decrypt),
                    ],
                    default => throw new UnsupportedSourceTypeException(),
                };

                $method = $this->paymentMethodService->create($data, ['api_key' => $apiKey]);
                $method = $method->attach(['customer' => $this->getCustomer($event->billingAddress, $apiKey)->id]);

                $paymentMethod = StripePaymentMethodAggregateRoot::attach($message->aggregateRootId(), $method);
                $this->stripePaymentMethodRepository->persist($paymentMethod);
            }
        } catch (ApiErrorException $e) {
            $this->paymentMethodRepository->persist(
                $this->paymentMethodRepository
                    ->retrieve($message->aggregateRootId())
                    ->fail(),
            );
        }
    }

    private function getCustomer(BillingAddress $billingAddress, string $apiKey): Customer
    {
        return $this->customerService->create(self::address($billingAddress), ['api_key' => $apiKey]);
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

    protected function handlePaymentMethodUpdated(PaymentMethodUpdated $event, Message $message): void
    {
        $apiKey = $this->configProvider->getApiKey($message);
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