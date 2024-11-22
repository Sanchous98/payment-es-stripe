<?php

use PaymentSystem\Contracts\EncryptInterface;
use PaymentSystem\ValueObjects\CreditCard;

dataset('source', function () {
    yield 'token source' => new CreditCard(
        CreditCard\Number::fromNumber('4242424242424242', new class implements EncryptInterface
        {
            public function encrypt(string $data): string
            {
                return $data;
            }
        }),
        new CreditCard\Expiration(12, 34),
        new CreditCard\Holder('Andrea Palladio'),
        new CreditCard\Cvc(),
    );
});