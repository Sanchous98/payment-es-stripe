<?php

namespace PaymentSystem\Stripe\Consumers;

use EventSauce\EventSourcing\EventConsumption\EventConsumer;
use EventSauce\EventSourcing\Message;
use PaymentSystem\Events\PaymentIntentAuthorized;
use PaymentSystem\Events\PaymentIntentCanceled;
use PaymentSystem\Events\PaymentIntentCaptured;
use PaymentSystem\Stripe\Repositories\PaymentIntentRepositoryInterface;
use PaymentSystem\Stripe\Repositories\PaymentMethodRepositoryInterface;
use Stripe\PaymentIntent;
use Stripe\Service\PaymentIntentService;
use Stripe\StripeClient;

class PaymentIntentConsumer extends EventConsumer
{
    public function __construct(
        private readonly PaymentIntentRepositoryInterface $paymentIntentRepository,
        private readonly PaymentMethodRepositoryInterface $paymentMethodRepository,
        private readonly PaymentIntentService $paymentIntentService,
    ) {
    }

    protected function handlePaymentIntentAuthorized(PaymentIntentAuthorized $event, Message $message): void
    {
        $apiKey = $message->header('X-Stripe-Key');

        $stripeIntent = $this->paymentIntentService->create([
            'amount' => $event->money->getAmount(),
            'currency' => $event->money->getCurrency()->getCode(),
            'capture_method' => 'manual',
            'confirm' => true,
            'description' => $event->description,
            'payment_method' => $this->paymentMethodRepository
                ->retrieve($event->paymentMethodId)
                ->getStripePaymentMethod()
                ->id,
            'payment_method_options' => isset($event->threeDSResult) ? [
                'card' => [
                    'three_d_secure' => [
                        'cryptogram' => $event->threeDSResult->authenticationValue,
                        'transaction_id' => $event->threeDSResult->dsTransactionId,
                        'version' => $event->threeDSResult->version->value,
                        'ares_trans_status' => $event->threeDSResult->status->value,
                        'electronic_commerce_indicator' => $event->threeDSResult->eci->value,
                    ],
                ],
            ] : [],
            'automatic_payment_methods' => [
                'allow_redirects' => 'never',
                'enabled' => true,
            ],
            ...(!empty($event->merchantDescriptor) ? ['statement_descriptor' => $event->merchantDescriptor] : [])
        ], ['apiKey' => $apiKey]);

        $paymentIntent = $this->paymentIntentRepository
            ->retrieve($message->aggregateRootId())
            ->create($stripeIntent);

        $this->paymentIntentRepository->persist($paymentIntent);
    }

    protected function handlePaymentIntentCanceled(PaymentIntentCanceled $event, Message $message): void
    {
        $apiKey = $message->header('X-Stripe-Key');
        $paymentIntent = $this->paymentIntentRepository->retrieve($message->aggregateRootId());
        $paymentIntent->cancel($this->paymentIntentService->cancel($paymentIntent->getStripePaymentIntent()->id, opts: ['apiKey' => $apiKey]));
        $this->paymentIntentRepository->persist($paymentIntent);
    }

    protected function handlePaymentIntentCaptured(PaymentIntentCaptured $event, Message $message): void
    {
        $apiKey = $message->header('X-Stripe-Key');
        $paymentIntent = $this->paymentIntentRepository->retrieve($message->aggregateRootId());
        $paymentIntent->cancel($paymentIntent->getStripePaymentIntent()->capture([
            ...(isset($event->amount) ? ['amount_to_capture' => $event->amount] : []),
            ...(isset($event->amount) ? ['payment_method' => $event->amount] : []),
        ], ['apiKey' => $apiKey]));
        $this->paymentIntentRepository->persist($paymentIntent);
    }
}