<?php

namespace PaymentSystem\Stripe\Consumers;

use EventSauce\EventSourcing\AggregateRootRepository;
use EventSauce\EventSourcing\Message;
use EventSauce\EventSourcing\MessageConsumer;
use PaymentSystem\Events\PaymentIntentAuthorized;
use PaymentSystem\Events\PaymentIntentCanceled;
use PaymentSystem\Events\PaymentIntentCaptured;
use PaymentSystem\PaymentIntentAggregateRoot;
use PaymentSystem\Stripe\Repositories\StripePaymentIntentRepositoryInterface;
use PaymentSystem\Stripe\Repositories\StripeTenderRepositoryInterface;
use Stripe\Exception\InvalidRequestException;
use Stripe\PaymentMethod;
use Stripe\Service\PaymentIntentService;
use PaymentSystem\Stripe\PaymentIntentAggregateRoot as StripePaymentIntentAggregateRoot;
use Stripe\Token;

readonly class PaymentIntentSaga implements MessageConsumer
{
    /**
     * @param AggregateRootRepository<PaymentIntentAggregateRoot> $paymentIntentRepository
     */
    public function __construct(
        private StripePaymentIntentRepositoryInterface $stripePaymentIntentRepository,
        private StripeTenderRepositoryInterface $tenderRepository,
        private PaymentIntentService $paymentIntentService,
        private AggregateRootRepository $paymentIntentRepository,
    ) {
    }

    public function handle(Message $message): void
    {
        $payload = $message->payload();

        switch ($payload::class) {
            case PaymentIntentAuthorized::class:
                $this->handlePaymentIntentAuthorized($payload, $message);
                return;
            case PaymentIntentCaptured::class:
                $this->handlePaymentIntentCaptured($payload, $message);
                return;
            case PaymentIntentCanceled::class:
                $this->handlePaymentIntentCanceled($payload, $message);
                return;
        }
    }

    protected function handlePaymentIntentAuthorized(PaymentIntentAuthorized $event, Message $message): void
    {
        $apiKey = $message->header('X-Stripe-Key');

        $stripeTender = $this->tenderRepository
            ->retrieve($event->tenderId)
            ->getTender();

        try {
            $stripeIntent = $this->paymentIntentService->create([
                'amount' => $event->money->getAmount(),
                'currency' => $event->money->getCurrency()->getCode(),
                'capture_method' => 'manual',
                'confirm' => true,
                'description' => $event->description,
                ...isset($stripeTender->customer) ? ['customer' => $stripeTender->customer] : [],
                ...match ($stripeTender::class) {
                    Token::class => [
                        'payment_method_data' => [
                            'type' => $stripeTender->type,
                            $stripeTender->type => [
                                'token' => $stripeTender->id,
                            ]
                        ]
                    ],
                    PaymentMethod::class => [
                        'payment_method' => $stripeTender->id
                    ],
                },
                'automatic_payment_methods' => [
                    'allow_redirects' => 'never',
                    'enabled' => true,
                ],
                ...(isset($event->threeDSResult) ? [
                    'payment_method_options' => [
                        'card' => [
                            'three_d_secure' => [
                                'cryptogram' => $event->threeDSResult->authenticationValue,
                                'transaction_id' => $event->threeDSResult->dsTransactionId,
                                'version' => $event->threeDSResult->version->value,
                                'ares_trans_status' => $event->threeDSResult->status->value,
                                'electronic_commerce_indicator' => $event->threeDSResult->eci->value,
                            ],
                        ],
                    ]
                ] : []),
                ...(!empty($event->merchantDescriptor) ? ['statement_descriptor' => $event->merchantDescriptor] : [])
            ], ['api_key' => $apiKey]);
        } catch (InvalidRequestException $e) {
            $this->paymentIntentRepository->persist(
                $this->paymentIntentRepository
                    ->retrieve($message->aggregateRootId())
                    ->decline($e->getMessage())
            );

            return;
        }

        $this->stripePaymentIntentRepository
            ->persist(StripePaymentIntentAggregateRoot::create($message->aggregateRootId(), $stripeIntent));
    }

    protected function handlePaymentIntentCaptured(PaymentIntentCaptured $event, Message $message): void
    {
        $apiKey = $message->header('X-Stripe-Key');
        $paymentIntent = $this->stripePaymentIntentRepository->retrieve($message->aggregateRootId());

        $paymentMethodId = null;

        if (isset($event->tenderId)) {
            $paymentMethodId = $this->tenderRepository
                ->retrieve($event->tenderId)
                ->getStripePaymentMethod()
                ->id;
        }

        try {
            $paymentIntent->capture($paymentIntent->getStripePaymentIntent()->capture([
                ...(isset($event->amount) ? ['amount_to_capture' => $event->amount] : []),
                ...(isset($paymentMethodId) ? ['payment_method' => $paymentMethodId] : []),
            ], ['api_key' => $apiKey]));
        } catch (InvalidRequestException $e) {
            $this->paymentIntentRepository->persist(
                $this->paymentIntentRepository->retrieve($message->aggregateRootId())->decline($e->getMessage())
            );
        }
        $this->stripePaymentIntentRepository->persist($paymentIntent);
    }

    protected function handlePaymentIntentCanceled(PaymentIntentCanceled $event, Message $message): void
    {
        $apiKey = $message->header('X-Stripe-Key');
        $paymentIntent = $this->stripePaymentIntentRepository->retrieve($message->aggregateRootId());
        $paymentIntent->cancel(
            $this->paymentIntentService->cancel(
                $paymentIntent->getStripePaymentIntent()->id,
                opts: ['api_key' => $apiKey]
            )
        );
        $this->stripePaymentIntentRepository->persist($paymentIntent);
    }
}