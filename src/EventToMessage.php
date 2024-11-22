<?php

namespace PaymentSystem\Stripe;

use DateTime;
use DateTimeImmutable;
use EventSauce\EventSourcing\DefaultHeadersDecorator;
use EventSauce\EventSourcing\Message;
use EventSauce\EventSourcing\MessageDecorator;
use Stripe\Event;

final readonly class EventToMessage
{
    public function __construct(
        private MessageDecorator $decorator = new DefaultHeadersDecorator(),
    ) {
    }

    public function toMessage(Event $event): Message
    {
        $time = (new DateTimeImmutable())->setTimestamp($event->created);

        return match ($event->type) {
            Event::PAYMENT_INTENT_CANCELED => $this->decorator
                ->decorate(new Message(new Events\PaymentIntentCanceled($event->data->object)))
                ->withTimeOfRecording($time),
            Event::PAYMENT_INTENT_CREATED => $this->decorator
                ->decorate(new Message(new Events\PaymentIntentCreated($event->data->object)))
                ->withTimeOfRecording($time),
            Event::PAYMENT_INTENT_SUCCEEDED => $this->decorator
                ->decorate(new Message(new Events\PaymentIntentSucceeded($event->data->object)))
                ->withTimeOfRecording($time),
            Event::PAYMENT_METHOD_ATTACHED => $this->decorator
                ->decorate(new Message(new Events\PaymentMethodAttached($event->data->object)))
                ->withTimeOfRecording($time),
            Event::PAYMENT_METHOD_UPDATED => $this->decorator
                ->decorate(new Message(new Events\PaymentMethodUpdated($event->data->object)))
                ->withTimeOfRecording($time),
            Event::REFUND_CREATED => $this->decorator
                ->decorate(new Message(new Events\RefundCreated($event->data->object)))
                ->withTimeOfRecording($time),
        };
    }
}