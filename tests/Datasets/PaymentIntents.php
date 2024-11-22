<?php

use Money\Currency;
use Money\Money;
use PaymentSystem\Events\PaymentIntentAuthorized;
use PaymentSystem\Events\PaymentIntentCanceled;
use PaymentSystem\Events\PaymentIntentCaptured;
use PaymentSystem\Events\PaymentMethodCreated;
use PaymentSystem\Events\PaymentMethodSucceeded;
use PaymentSystem\PaymentIntentAggregateRoot;
use PaymentSystem\PaymentMethodAggregateRoot;
use PaymentSystem\Stripe\ValueObjects\StringId;
use PaymentSystem\ValueObjects\BillingAddress;
use PaymentSystem\ValueObjects\Country;
use PaymentSystem\ValueObjects\Email;
use PaymentSystem\ValueObjects\PhoneNumber;

use function Pest\Faker\fake;

dataset('authorized payment', function () {
    $billingAddress = new BillingAddress(
        firstName: fake()->firstName,
        lastName: fake()->lastName,
        city: fake()->city,
        country: new Country(fake()->countryCode),
        postalCode: fake()->postcode,
        email: new Email(fake()->email),
        phone: new PhoneNumber(fake()->e164PhoneNumber),
        addressLine: fake()->address,
    );
    $source = new Cash();

    $paymentMethod = PaymentMethodAggregateRoot::reconstituteFromEvents(
        new StringId(1),
        generator([
            new PaymentMethodCreated($billingAddress, $source),
            new PaymentMethodSucceeded()
        ])
    );

    yield PaymentIntentAggregateRoot::reconstituteFromEvents(new StringId(1), generator([
        new PaymentIntentAuthorized(new Money(100, new Currency('USD')), $paymentMethod->aggregateRootId()),
    ]));
});

dataset('captured payment', function () {
    $billingAddress = new BillingAddress(
        firstName: fake()->firstName,
        lastName: fake()->lastName,
        city: fake()->city,
        country: new Country(fake()->countryCode),
        postalCode: fake()->postcode,
        email: new Email(fake()->email),
        phone: new PhoneNumber(fake()->e164PhoneNumber),
        addressLine: fake()->address,
    );
    $source = new Cash();

    $paymentMethod = PaymentMethodAggregateRoot::reconstituteFromEvents(
        new StringId(1),
        generator([
            new PaymentMethodCreated($billingAddress, $source),
            new PaymentMethodSucceeded()
        ])
    );

    yield PaymentIntentAggregateRoot::reconstituteFromEvents(new StringId(1), generator([
        new PaymentIntentAuthorized(new Money(100, new Currency('USD')), $paymentMethod->aggregateRootId()),
        new PaymentIntentCaptured(),
    ]));
});

dataset('canceled payment', function () {
    $billingAddress = new BillingAddress(
        firstName: fake()->firstName,
        lastName: fake()->lastName,
        city: fake()->city,
        country: new Country(fake()->countryCode),
        postalCode: fake()->postcode,
        email: new Email(fake()->email),
        phone: new PhoneNumber(fake()->e164PhoneNumber),
        addressLine: fake()->address,
    );
    $source = new Cash();

    $paymentMethod = PaymentMethodAggregateRoot::reconstituteFromEvents(
        new StringId(1),
        generator([
            new PaymentMethodCreated($billingAddress, $source),
            new PaymentMethodSucceeded()
        ])
    );

    yield PaymentIntentAggregateRoot::reconstituteFromEvents(new StringId(1), generator([
        new PaymentIntentAuthorized(new Money(100, new Currency('USD')), $paymentMethod->aggregateRootId()),
        new PaymentIntentCanceled()
    ]));
});