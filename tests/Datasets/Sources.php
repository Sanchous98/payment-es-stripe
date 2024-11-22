<?php

use PaymentSystem\ValueObjects\Source;
use PaymentSystem\ValueObjects\Token;

dataset('source', function () {
    yield Source::wrap(new Token('tok_visa'));
});