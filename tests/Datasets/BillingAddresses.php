<?php

use PaymentSystem\ValueObjects\Country;
use PaymentSystem\ValueObjects\Email;
use PaymentSystem\ValueObjects\BillingAddress;

use PaymentSystem\ValueObjects\PhoneNumber;

use function Pest\Faker\fake;

dataset('billing address', function () {
    yield new BillingAddress(
        firstName: fake()->firstName,
        lastName: fake()->lastName,
        city: fake()->city,
        country: new Country(fake()->countryCode),
        postalCode: fake()->postcode,
        email: new Email(fake()->email),
        phone: new PhoneNumber(fake()->e164PhoneNumber()),
        addressLine: fake()->address,
    );
});