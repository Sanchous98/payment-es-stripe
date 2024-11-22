<?php

namespace PaymentSystem\Stripe\Consumers;

use EventSauce\EventSourcing\EventConsumption\EventConsumer;
use EventSauce\EventSourcing\Message;
use PaymentSystem\Contracts\DecryptInterface;
use PaymentSystem\Enum\SourceEnum;
use PaymentSystem\Events\PaymentMethodCreated;
use PaymentSystem\Events\PaymentMethodUpdated;
use PaymentSystem\Stripe\Repositories\PaymentMethodRepositoryInterface;
use PaymentSystem\Stripe\ValueObjects\TokenId;
use PaymentSystem\ValueObjects\Token;
use Stripe\Customer;
use Stripe\PaymentMethod;
use Stripe\Service\PaymentMethodService;

class PaymentMethodConsumer extends EventConsumer
{
    public function __construct(
        private readonly PaymentMethodRepositoryInterface $paymentMethodRepository,
        private readonly PaymentMethodService $paymentMethodService,
        private readonly DecryptInterface $decrypt,
    ) {
    }

    protected function handlePaymentMethodCreated(PaymentMethodCreated $event, Message $message): void
    {
        $apiKey = $message->header('X-Stripe-Key');

        if ($event->source->getType() === SourceEnum::TOKEN && str_starts_with($event->source->unwrap()->tokenId, 'pm_')) {
            $method = PaymentMethod::retrieve($event->source->unwrap()->tokenId, ['api_key' => $apiKey]);

            if ($method->customer === null) {
                $method = $method->attach(['customer' => $this->getCustomer($event, $message)->id]);
            }
        } else {
            $cvc = $event->source->unwrap()->cvc?->getCvc($this->decrypt);
            $method = $this->paymentMethodService->create(
                [
                    'billing_details' => [
                        'address' => [
                            'city' => $event->billingAddress->city,
                            'country' => $event->billingAddress->country,
                            'line1' => $event->billingAddress->addressLine,
                            'line2' => $event->billingAddress->addressLineExtra,
                            'postal_code' => $event->billingAddress->postalCode,
                            'state' => $event->billingAddress->state,
                        ],
                        'email' => $event->billingAddress->email,
                        'name' => $event->billingAddress->firstName . ' ' . $event->billingAddress->lastName,
                        'phone' => $event->billingAddress->phone,
                    ],
                ] + match ($event->source->getType()) {
                    SourceEnum::CARD => [
                        'type' => 'card',
                        'card' => [
                            'exp_month' => $event->source->unwrap()->expiration->format('n'),
                            'exp_year' => $event->source->unwrap()->expiration->format('y'),
                            'number' => $event->source->unwrap()->number->getNumber($this->decrypt),
                            ...(isset($cvc) ? ['cvc' => $cvc] : []),
                        ],
                    ],
                    SourceEnum::TOKEN => $this->addToken($event->source->unwrap(), $apiKey),
                },
                ['api_key' => $apiKey],
            );

            $method = $method->attach(['customer' => $this->getCustomer($event, $message)->id]);
        }

        $paymentMethod = $this->paymentMethodRepository
            ->retrieve($message->aggregateRootId())
            ->attach($method);

        $this->paymentMethodRepository->persist($paymentMethod);
    }

    protected function handlePaymentMethodUpdated(PaymentMethodUpdated $event, Message $message): void
    {
        $apiKey = $message->header('X-Stripe-Key');
        $paymentMethod = $this->paymentMethodRepository->retrieve($message->aggregateRootId());

        $stripePaymentMethod = $this->paymentMethodService->update($paymentMethod->getStripePaymentMethod()->id, [
            'billing_details' => [
                'address' => [
                    'city' => $event->billingAddress->city,
                    'country' => $event->billingAddress->country,
                    'line1' => $event->billingAddress->addressLine,
                    'line2' => $event->billingAddress->addressLineExtra,
                    'postal_code' => $event->billingAddress->postalCode,
                    'state' => $event->billingAddress->state,
                ],
                'email' => $event->billingAddress->email,
                'name' => $event->billingAddress->firstName . ' ' . $event->billingAddress->lastName,
                'phone' => $event->billingAddress->phone,
            ],
        ], ['api_key' => $apiKey]);

        $paymentMethod->update($stripePaymentMethod);
        $this->paymentMethodRepository->persist($paymentMethod);
    }

    private function getCustomer(PaymentMethodCreated $event, Message $message): Customer
    {
        return Customer::create([
            'name' => $event->billingAddress->firstName . ' ' . $event->billingAddress->lastName,
            'address' => [
                'city' => $event->billingAddress->city,
                'country' => (string)$event->billingAddress->country,
                'line1' => $event->billingAddress->addressLine,
                'line2' => $event->billingAddress->addressLineExtra,
                'postal_code' => $event->billingAddress->postalCode,
                'state' => (string)$event->billingAddress->state,
            ],
            'phone' => (string)$event->billingAddress->phone,
            'email' => (string)$event->billingAddress->email,
        ], ['api_key' => $message->header('X-Stripe-Key')]);
    }

    private function addToken(Token $token, string $apiKey): array
    {
        $id = new TokenId($token->tokenId);
        $token = \Stripe\Token::retrieve($id, ['api_key' => $apiKey]);

        return [
            'type' => $token->type,
            $token->type => ['token' => $token->id],
        ];
    }
}