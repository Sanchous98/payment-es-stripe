<?php

use Money\Currency;
use Money\Money;
use PaymentSystem\Events\PaymentIntentAuthorized;
use PaymentSystem\Events\PaymentIntentCaptured;
use PaymentSystem\Events\PaymentMethodCreated;
use PaymentSystem\Events\PaymentMethodSucceeded;
use PaymentSystem\Events\RefundCanceled;
use PaymentSystem\Events\RefundCreated;
use PaymentSystem\Events\RefundDeclined;
use PaymentSystem\Events\RefundSucceeded;
use PaymentSystem\PaymentIntentAggregateRoot;
use PaymentSystem\PaymentMethodAggregateRoot;
use PaymentSystem\RefundAggregateRoot;
use PaymentSystem\Tests\IntId;
use PaymentSystem\ValueObjects\BillingAddress;
use PaymentSystem\ValueObjects\Cash;
use PaymentSystem\ValueObjects\Country;
use PaymentSystem\ValueObjects\Email;
use PaymentSystem\ValueObjects\PhoneNumber;
use PaymentSystem\ValueObjects\Source;

use function Pest\Faker\fake;

dataset('created refund', function () {
    $billingAddress = new BillingAddress(
        firstName: fake()->firstName,
        lastName: fake()->lastName,
        city: fake()->city,
        country: new Country(fake()->countryCode),
        postalCode: fake()->postcode,
        email: new Email(fake()->email),
        phone: new PhoneNumber(fake()->e164PhoneNumber()),
        addressLine: fake()->address,
    );
    $source = Source::wrap(new Cash());

    $paymentMethod = PaymentMethodAggregateRoot::reconstituteFromEvents(
        new IntId(1),
        generator([
            new PaymentMethodCreated($billingAddress, $source),
            new PaymentMethodSucceeded()
        ])
    );
    $paymentIntent = PaymentIntentAggregateRoot::reconstituteFromEvents(new IntId(1), generator([
        new PaymentIntentAuthorized(new Money(100, new Currency('USD')), $paymentMethod->aggregateRootId()),
        new PaymentIntentCaptured(),
    ]));

    yield RefundAggregateRoot::reconstituteFromEvents(new IntId(1), generator([
        new RefundCreated(new Money(100, new Currency('USD')), $paymentIntent->aggregateRootId())
    ]));
});

dataset('succeeded refund', function () {
    $billingAddress = new BillingAddress(
        firstName: fake()->firstName,
        lastName: fake()->lastName,
        city: fake()->city,
        country: new Country(fake()->countryCode),
        postalCode: fake()->postcode,
        email: new Email(fake()->email),
        phone: new PhoneNumber(fake()->e164PhoneNumber()),
        addressLine: fake()->address,
    );
    $source = Source::wrap(new Cash());

    $paymentMethod = PaymentMethodAggregateRoot::reconstituteFromEvents(
        new IntId(1),
        generator([
            new PaymentMethodCreated($billingAddress, $source),
            new PaymentMethodSucceeded()
        ])
    );
    $paymentIntent = PaymentIntentAggregateRoot::reconstituteFromEvents(new IntId(1), generator([
        new PaymentIntentAuthorized(new Money(100, new Currency('USD')), $paymentMethod->aggregateRootId()),
        new PaymentIntentCaptured(),
    ]));

    yield RefundAggregateRoot::reconstituteFromEvents(new IntId(1), generator([
        new RefundCreated(new Money(100, new Currency('USD')), $paymentIntent->aggregateRootId()),
        new RefundSucceeded(),
    ]));
});

dataset('declined refund', function () {
    $billingAddress = new BillingAddress(
        firstName: fake()->firstName,
        lastName: fake()->lastName,
        city: fake()->city,
        country: new Country(fake()->countryCode),
        postalCode: fake()->postcode,
        email: new Email(fake()->email),
        phone: new PhoneNumber(fake()->e164PhoneNumber()),
        addressLine: fake()->address,
    );
    $source = Source::wrap(new Cash());

    $paymentMethod = PaymentMethodAggregateRoot::reconstituteFromEvents(
        new IntId(1),
        generator([
            new PaymentMethodCreated($billingAddress, $source),
            new PaymentMethodSucceeded()
        ])
    );
    $paymentIntent = PaymentIntentAggregateRoot::reconstituteFromEvents(new IntId(1), generator([
        new PaymentIntentAuthorized(new Money(100, new Currency('USD')), $paymentMethod->aggregateRootId()),
        new PaymentIntentCaptured(),
    ]));

    yield RefundAggregateRoot::reconstituteFromEvents(new IntId(1), generator([
        new RefundCreated(new Money(100, new Currency('USD')), $paymentIntent->aggregateRootId()),
        new RefundDeclined(''),
    ]));
});

dataset('canceled refund', function () {
    $billingAddress = new BillingAddress(
        firstName: fake()->firstName,
        lastName: fake()->lastName,
        city: fake()->city,
        country: new Country(fake()->countryCode),
        postalCode: fake()->postcode,
        email: new Email(fake()->email),
        phone: new PhoneNumber(fake()->e164PhoneNumber()),
        addressLine: fake()->address,
    );
    $source = Source::wrap(new Cash());

    $paymentMethod = PaymentMethodAggregateRoot::reconstituteFromEvents(
        new IntId(1),
        generator([
            new PaymentMethodCreated($billingAddress, $source),
            new PaymentMethodSucceeded(new IntId(1))
        ])
    );
    $paymentIntent = PaymentIntentAggregateRoot::reconstituteFromEvents(new IntId(1), generator([
        new PaymentIntentAuthorized(new Money(100, new Currency('USD')), $paymentMethod->aggregateRootId()),
        new PaymentIntentCaptured(),
    ]));

    yield RefundAggregateRoot::reconstituteFromEvents(new IntId(1), generator([
        new RefundCreated(new Money(100, new Currency('USD')), $paymentIntent->aggregateRootId()),
        new RefundCanceled(),
    ]));
});