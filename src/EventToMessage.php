<?php

namespace PaymentSystem\Stripe;

use DateTime;
use DateTimeImmutable;
use EventSauce\EventSourcing\DefaultHeadersDecorator;
use EventSauce\EventSourcing\Message;
use EventSauce\EventSourcing\MessageDecorator;
use PaymentSystem\Stripe\Events\PaymentIntent;
use PaymentSystem\Stripe\Events\PaymentMethod;
use PaymentSystem\Stripe\Events\Refund;
use Stripe\Event;

final readonly class EventToMessage
{
    public function __construct(private MessageDecorator $decorator = new DefaultHeadersDecorator())
    {
    }

    public function toMessage(Event $event): Message
    {
        $time = (new DateTime())->setTimestamp($event->created);

        return match ($event->type) {
            Event::PAYMENT_INTENT_CANCELED => $this->decorator
                ->decorate(new Message(new PaymentIntent\Canceled($event->data->object)))
                ->withTimeOfRecording(DateTimeImmutable::createFromMutable($time)),
            Event::PAYMENT_INTENT_CREATED => $this->decorator
                ->decorate(new Message(new PaymentIntent\Created($event->data->object)))
                ->withTimeOfRecording(DateTimeImmutable::createFromMutable($time)),
            Event::PAYMENT_INTENT_SUCCEEDED => $this->decorator
                ->decorate(new Message(new PaymentIntent\Succeeded($event->data->object)))
                ->withTimeOfRecording(DateTimeImmutable::createFromMutable($time)),
            Event::PAYMENT_METHOD_ATTACHED => $this->decorator
                ->decorate(new Message(new PaymentMethod\Attached($event->data->object)))
                ->withTimeOfRecording(DateTimeImmutable::createFromMutable($time)),
            Event::PAYMENT_METHOD_UPDATED => $this->decorator
                ->decorate(new Message(new PaymentMethod\Updated($event->data->object)))
                ->withTimeOfRecording(DateTimeImmutable::createFromMutable($time)),
            Event::REFUND_CREATED => $this->decorator
                ->decorate(new Message(new Refund\Created($event->data->object)))
                ->withTimeOfRecording(DateTimeImmutable::createFromMutable($time)),
        };
    }
}