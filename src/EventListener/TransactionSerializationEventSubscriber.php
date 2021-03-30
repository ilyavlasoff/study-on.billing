<?php

namespace App\EventListener;

use App\Entity\Course;
use App\Entity\Transaction;
use JMS\Serializer\EventDispatcher\EventSubscriberInterface;
use JMS\Serializer\EventDispatcher\ObjectEvent;
use JMS\Serializer\JsonSerializationVisitor;

class TransactionSerializationEventSubscriber implements EventSubscriberInterface
{

    /**
     * @inheritDoc
     */
    public static function getSubscribedEvents()
    {
        return [
            [
                'event' => 'serializer.post_serialize',
                'method' => 'onTransactionPostSerialize',
                'class' => Transaction::class,
            ],
        ];
    }

    public function onTransactionPostSerialize(ObjectEvent $event): void
    {
        /** @var Transaction $transaction */
        $transaction = $event->getObject();
        /** @var JsonSerializationVisitor $visitor */
        $visitor = $event->getVisitor();

        if ($visitor->hasData('type')) {
            $visitor->setData('type', $transaction->getStringOperationType());
        }
    }
}
