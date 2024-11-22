<?php

use Money\Currency;
use Money\Money;
use PaymentSystem\Commands\AuthorizePaymentCommandInterface;
use PaymentSystem\Commands\CapturePaymentCommandInterface;
use PaymentSystem\Commands\CreatePaymentMethodCommandInterface;
use PaymentSystem\Commands\CreateRefundCommandInterface;
use PaymentSystem\Enum\ECICodesEnum;
use PaymentSystem\Enum\PaymentIntentStatusEnum;
use PaymentSystem\Enum\PaymentMethodStatusEnum;
use PaymentSystem\Enum\RefundStatusEnum;
use PaymentSystem\Enum\SupportedVersionsEnum;
use PaymentSystem\Enum\ThreeDSStatusEnum;
use PaymentSystem\PaymentIntentAggregateRoot;
use PaymentSystem\PaymentMethodAggregateRoot;
use PaymentSystem\RefundAggregateRoot;
use PaymentSystem\Stripe\PaymentIntentAggregateRoot as StripePaymentIntentAggregateRoot;
use PaymentSystem\Stripe\PaymentMethodAggregateRoot as StripePaymentMethodAggregateRoot;
use PaymentSystem\Stripe\RefundAggregateRoot as StripeRefundAggregateRoot;
use PaymentSystem\Stripe\ValueObjects\StringId;
use PaymentSystem\ValueObjects\BillingAddress;
use PaymentSystem\ValueObjects\Source;
use PaymentSystem\ValueObjects\ThreeDSResult;
use Stripe\Exception\ApiErrorException;
use Stripe\PaymentIntent;
use Stripe\Refund;

use function Pest\Faker\fake;

it(
    'supports payment method flow',
    $paymentMethodTest = function (BillingAddress $billingAddress, Source $source) {
        $paymentMethod = $this->domainPaymentMethodRepository()->retrieve(new StringId('test_pm'));
        $command = $this->createStub(CreatePaymentMethodCommandInterface::class);
        $command->method('getSource')->willReturn($source);
        $command->method('getBillingAddress')->willReturn($billingAddress);
        $paymentMethod->create($command);
        $this->domainPaymentMethodRepository()->persist($paymentMethod);

        expect($this->stripePaymentMethodRepository()->retrieve($paymentMethod->aggregateRootId()))
            ->toBeInstanceOf(StripePaymentMethodAggregateRoot::class)
            ->and($this->domainPaymentMethodRepository()->retrieve($paymentMethod->aggregateRootId()))
            ->toBeInstanceOf(PaymentMethodAggregateRoot::class)
            ->is(PaymentMethodStatusEnum::SUCCEEDED)->toBeTrue();
    }
)->with('billing address', 'source');

it('supports payment intent flow', function () {
    $paymentMethod = $this->domainPaymentMethodRepository()->retrieve(new StringId('test_pm'));

    expect($paymentMethod)->is(PaymentMethodStatusEnum::SUCCEEDED)->toBeTrue();

    $paymentIntent = $this->domainPaymentIntentRepository()->retrieve(new StringId('test_pi'));

    $command = $this->createStub(AuthorizePaymentCommandInterface::class);
    $command->method('getMoney')->willReturn(new Money(100, new Currency('USD')));
    $command->method('getPaymentMethod')->willReturn($paymentMethod);
    $command->method('getMerchantDescriptor')->willReturn('');
    $command->method('getDescription')->willReturn('');
    $command->method('getThreeDSResult')->willReturn(null);

    $this->domainPaymentIntentRepository()->persist($paymentIntent->authorize($command));

    expect($this->stripePaymentIntentRepository()->retrieve($paymentIntent->aggregateRootId()))
        ->toBeInstanceOf(StripePaymentIntentAggregateRoot::class)
        ->getStripePaymentIntent()->status->toBe(PaymentIntent::STATUS_REQUIRES_CAPTURE)
        ->and($paymentIntent)
        ->toBeInstanceOf(PaymentIntentAggregateRoot::class)
        ->is(PaymentIntentStatusEnum::REQUIRES_CAPTURE)->toBeTrue();

    $this->domainPaymentIntentRepository()->persist($paymentIntent->cancel());

    expect($this->stripePaymentIntentRepository()->retrieve($paymentIntent->aggregateRootId()))
        ->toBeInstanceOf(StripePaymentIntentAggregateRoot::class)
        ->getStripePaymentIntent()->status->toBe(PaymentIntent::STATUS_CANCELED)
        ->and($paymentIntent)
        ->toBeInstanceOf(PaymentIntentAggregateRoot::class)
        ->is(PaymentIntentStatusEnum::CANCELED)->toBeTrue();
})->depends('it supports payment method flow');

it('supports refunds flow', function () {
    $paymentMethod = $this->domainPaymentMethodRepository()->retrieve(new StringId('test_pm'));

    expect($paymentMethod)->is(PaymentMethodStatusEnum::SUCCEEDED)->toBeTrue();

    $paymentIntent = $this->domainPaymentIntentRepository()->retrieve(new StringId('test_pi'));

    $command = $this->createStub(AuthorizePaymentCommandInterface::class);
    $command->method('getMoney')->willReturn(new Money(100, new Currency('USD')));
    $command->method('getPaymentMethod')->willReturn($paymentMethod);
    $command->method('getMerchantDescriptor')->willReturn('');
    $command->method('getDescription')->willReturn('');
    $command->method('getThreeDSResult')->willReturn(null);

    $this->domainPaymentIntentRepository()->persist($paymentIntent->authorize($command));

    expect($this->stripePaymentIntentRepository()->retrieve($paymentIntent->aggregateRootId()))
        ->toBeInstanceOf(StripePaymentIntentAggregateRoot::class)
        ->getStripePaymentIntent()->status->toBe(PaymentIntent::STATUS_REQUIRES_CAPTURE)
        ->and($paymentIntent)
        ->toBeInstanceOf(PaymentIntentAggregateRoot::class)
        ->is(PaymentIntentStatusEnum::REQUIRES_CAPTURE)->toBeTrue();

    $command = $this->createStub(CapturePaymentCommandInterface::class);
    $this->domainPaymentIntentRepository()->persist($paymentIntent->capture($command));

    expect($this->stripePaymentIntentRepository()->retrieve($paymentIntent->aggregateRootId()))
        ->toBeInstanceOf(StripePaymentIntentAggregateRoot::class)
        ->getStripePaymentIntent()->status->toBe(PaymentIntent::STATUS_SUCCEEDED)
        ->and($paymentIntent)
        ->toBeInstanceOf(PaymentIntentAggregateRoot::class)
        ->is(PaymentIntentStatusEnum::SUCCEEDED)->toBeTrue();

    $refund = $this->domainRefundRepository()->retrieve(new StringId('test_re'));

    $command = $this->createStub(CreateRefundCommandInterface::class);
    $command->method('getMoney')->willReturn(new Money(100, new Currency('USD')));
    $command->method('getPaymentIntent')->willReturn($paymentIntent);

    $this->domainRefundRepository()->persist($refund->create($command));

    expect($this->stripeRefundRepository()->retrieve($refund->aggregateRootId()))
        ->toBeInstanceOf(StripeRefundAggregateRoot::class)
        ->getStripeRefund()->status->toBe(Refund::STATUS_SUCCEEDED)
        ->and($this->domainRefundRepository()->retrieve($refund->aggregateRootId()))
        ->toBeInstanceOf(RefundAggregateRoot::class)
        ->is(RefundStatusEnum::SUCCEEDED)->toBeTrue();
})->depends('it supports payment intent flow');

it('does not save domain payment intent when stripe fails', function () {
    $paymentMethod = $this->domainPaymentMethodRepository()->retrieve(new StringId('test_pm'));

    expect($paymentMethod)->is(PaymentMethodStatusEnum::SUCCEEDED)->toBeTrue();

    $paymentIntent = $this->domainPaymentIntentRepository()->retrieve(new StringId('test_pi'));

    $command = $this->createStub(AuthorizePaymentCommandInterface::class);
    $command->method('getMoney')->willReturn(new Money(100, new Currency('USD')));
    $command->method('getPaymentMethod')->willReturn($paymentMethod);
    $command->method('getMerchantDescriptor')->willReturn('');
    $command->method('getDescription')->willReturn('');
    $command->method('getThreeDSResult')->willReturn(
        new ThreeDSResult(
            ThreeDSStatusEnum::REJECTED,
            'mjkjcfmcjkfc',
            ECICodesEnum::VISA_FAILED,
            fake()->uuid(),
            fake()->uuid(),
            null,
            SupportedVersionsEnum::V220
        )
    );

    try {
        $this->domainPaymentIntentRepository()->persist($paymentIntent->authorize($command));
    } catch (ApiErrorException) {
    }

    $paymentIntent = $this->domainPaymentIntentRepository()->retrieve($paymentIntent->aggregateRootId());

    expect($paymentIntent)->is(PaymentIntentStatusEnum::DECLINED)->toBeTrue();
})->depends('it supports payment method flow');