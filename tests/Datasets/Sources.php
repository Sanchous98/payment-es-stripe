<?php

use PaymentSystem\ValueObjects\Source;
use PaymentSystem\ValueObjects\Token;

dataset('source', function () {
    yield 'token source' => Source::wrap(new Token('tok_visa'));
});