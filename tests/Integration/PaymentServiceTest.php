<?php

use Money\Currency;
use Money\Money;
use PaymentSystem\Commands\AuthorizePaymentCommandInterface;
use PaymentSystem\Commands\CapturePaymentCommandInterface;
use PaymentSystem\Commands\CreatePaymentMethodCommandInterface;
use PaymentSystem\Commands\CreateRefundCommandInterface;
use PaymentSystem\Commands\CreateTokenCommandInterface;
use PaymentSystem\Commands\CreateTokenPaymentMethodCommandInterface;
use PaymentSystem\Contracts\SourceInterface;
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
use PaymentSystem\Stripe\TokenAggregateRoot as StripeTokenAggregateRoot;
use PaymentSystem\Stripe\RefundAggregateRoot as StripeRefundAggregateRoot;
use PaymentSystem\Stripe\ValueObjects\StringId;
use PaymentSystem\TokenAggregateRoot;
use PaymentSystem\ValueObjects\BillingAddress;
use PaymentSystem\ValueObjects\ThreeDSResult;
use Stripe\Exception\ApiErrorException;
use Stripe\PaymentIntent;
use Stripe\Refund;

use function Pest\Faker\fake;

it('supports payment methods flow', function (BillingAddress $billingAddress, SourceInterface $source) {
    $command = $this->createStub(CreatePaymentMethodCommandInterface::class);
    $command->method('getId')->willReturn(new StringId('test_pm'));
    $command->method('getSource')->willReturn($source);
    $command->method('getBillingAddress')->willReturn($billingAddress);
    $paymentMethod = PaymentMethodAggregateRoot::create($command);
    $this->domainPaymentMethodRepository()->persist($paymentMethod);

    expect($this->stripePaymentMethodRepository()->retrieve($paymentMethod->aggregateRootId()))
        ->toBeInstanceOf(StripePaymentMethodAggregateRoot::class)
        ->and($this->domainPaymentMethodRepository()->retrieve($paymentMethod->aggregateRootId()))
        ->toBeInstanceOf(PaymentMethodAggregateRoot::class)
        ->is(PaymentMethodStatusEnum::SUCCEEDED)->toBeTrue();
})->with('billing address', 'source');

it('supports payment intents flow', function () {
    $paymentMethod = $this->domainPaymentMethodRepository()->retrieve(new StringId('test_pm'));

    expect($paymentMethod)->is(PaymentMethodStatusEnum::SUCCEEDED)->toBeTrue();

    $command = $this->createStub(AuthorizePaymentCommandInterface::class);
    $command->method('getId')->willReturn(new StringId('test_pi'));
    $command->method('getMoney')->willReturn(new Money(100, new Currency('USD')));
    $command->method('getTender')->willReturn($paymentMethod);
    $command->method('getMerchantDescriptor')->willReturn('');
    $command->method('getDescription')->willReturn('');
    $command->method('getThreeDSResult')->willReturn(null);

    $paymentIntent = PaymentIntentAggregateRoot::authorize($command);
    $this->domainPaymentIntentRepository()->persist($paymentIntent);

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
})->depends('it supports payment methods flow');

it('supports refunds flow', function () {
    $paymentMethod = $this->domainPaymentMethodRepository()->retrieve(new StringId('test_pm'));

    expect($paymentMethod)->is(PaymentMethodStatusEnum::SUCCEEDED)->toBeTrue();

    $command = $this->createStub(AuthorizePaymentCommandInterface::class);
    $command->method('getId')->willReturn(new StringId('test_pi'));
    $command->method('getMoney')->willReturn(new Money(100, new Currency('USD')));
    $command->method('getTender')->willReturn($paymentMethod);
    $command->method('getMerchantDescriptor')->willReturn('');
    $command->method('getDescription')->willReturn('');
    $command->method('getThreeDSResult')->willReturn(null);

    $paymentIntent = PaymentIntentAggregateRoot::authorize($command);
    $this->domainPaymentIntentRepository()->persist($paymentIntent);

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

    $command = $this->createStub(CreateRefundCommandInterface::class);
    $command->method('getId')->willReturn(new StringId('test_re'));
    $command->method('getMoney')->willReturn(new Money(100, new Currency('USD')));
    $command->method('getPaymentIntent')->willReturn($paymentIntent);

    $refund = RefundAggregateRoot::create($command);
    $this->domainRefundRepository()->persist($refund);

    expect($this->stripeRefundRepository()->retrieve($refund->aggregateRootId()))
        ->toBeInstanceOf(StripeRefundAggregateRoot::class)
        ->getStripeRefund()->status->toBe(Refund::STATUS_SUCCEEDED)
        ->and($this->domainRefundRepository()->retrieve($refund->aggregateRootId()))
        ->toBeInstanceOf(RefundAggregateRoot::class)
        ->is(RefundStatusEnum::SUCCEEDED)->toBeTrue();
})->depends('it supports payment intents flow');

it('does not save domain payment intent when stripe fails', function () {
    $paymentMethod = $this->domainPaymentMethodRepository()->retrieve(new StringId('test_pm'));

    expect($paymentMethod)->is(PaymentMethodStatusEnum::SUCCEEDED)->toBeTrue();

    $command = $this->createStub(AuthorizePaymentCommandInterface::class);
    $command->method('getId')->willReturn(new StringId('test_pi'));
    $command->method('getMoney')->willReturn(new Money(100, new Currency('USD')));
    $command->method('getTender')->willReturn($paymentMethod);
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
        $paymentIntent = PaymentIntentAggregateRoot::authorize($command);
        $this->domainPaymentIntentRepository()->persist($paymentIntent);
    } catch (ApiErrorException) {
    }

    $paymentIntent = $this->domainPaymentIntentRepository()->retrieve($paymentIntent->aggregateRootId());

    expect($paymentIntent)->is(PaymentIntentStatusEnum::DECLINED)->toBeTrue();
})->depends('it supports payment methods flow');

it('supports tokens flow', function (SourceInterface $source) {
    $command = $this->createStub(CreateTokenCommandInterface::class);
    $command->method('getCard')->willReturn($source);
    $command->method('getId')->willReturn(new StringId('test_tok'));

    $token = TokenAggregateRoot::create($command);
    $this->domainTokenRepository()->persist($token);

    expect($this->stripeTokenRepository()->retrieve($token->aggregateRootId()))
        ->toBeInstanceOf(StripeTokenAggregateRoot::class)
        ->and($this->domainTokenRepository()->retrieve($token->aggregateRootId()))
        ->toBeInstanceOf(TokenAggregateRoot::class)
        ->isValid()->toBeTrue();
})->with('source');

it('supports token payment method flow', function (BillingAddress $billingAddress) {
    $token = $this->domainTokenRepository()->retrieve(new StringId('test_tok'));

    $command = $this->createStub(CreateTokenPaymentMethodCommandInterface::class);
    $command->method('getId')->willReturn(new StringId('test_pm'));
    $command->method('getToken')->willReturn($token);
    $command->method('getBillingAddress')->willReturn($billingAddress);

    $paymentMethod = PaymentMethodAggregateRoot::createFromToken($command);
    $this->domainPaymentMethodRepository()->persist($paymentMethod);

    expect($this->stripePaymentMethodRepository()->retrieve($paymentMethod->aggregateRootId()))
        ->toBeInstanceOf(StripePaymentMethodAggregateRoot::class)
        ->and($this->domainPaymentMethodRepository()->retrieve($paymentMethod->aggregateRootId()))
        ->toBeInstanceOf(PaymentMethodAggregateRoot::class)
        ->is(PaymentMethodStatusEnum::SUCCEEDED)->toBeTrue()
        ->getSource()->toBe($token->getCard());
})->with('billing address')->depends('it supports tokens flow');

it('supports token payment intent flow', function (SourceInterface $source) {
    $command = $this->createStub(CreateTokenCommandInterface::class);
    $command->method('getCard')->willReturn($source);
    $command->method('getId')->willReturn(new StringId('test_tok'));

    $token = TokenAggregateRoot::create($command);
    $this->domainTokenRepository()->persist($token);

    expect($token)->isValid()->toBeTrue('token already used');

    $command = $this->createStub(AuthorizePaymentCommandInterface::class);
    $command->method('getId')->willReturn(new StringId('test_pi'));
    $command->method('getMoney')->willReturn(new Money(100, new Currency('USD')));
    $command->method('getTender')->willReturn($token);
    $command->method('getMerchantDescriptor')->willReturn('');
    $command->method('getDescription')->willReturn('');
    $command->method('getThreeDSResult')->willReturn(null);

    $paymentIntent = PaymentIntentAggregateRoot::authorize($command);
    $this->domainPaymentIntentRepository()->persist($paymentIntent);

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
})->with('source');