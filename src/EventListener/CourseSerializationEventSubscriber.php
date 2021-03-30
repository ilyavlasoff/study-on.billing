<?php

namespace App\EventListener;

use App\Entity\Course;
use JMS\Serializer\EventDispatcher\EventSubscriberInterface;
use JMS\Serializer\EventDispatcher\ObjectEvent;
use JMS\Serializer\JsonSerializationVisitor;

class CourseSerializationEventSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            [
                'event' => 'serializer.post_serialize',
                'method' => 'onCoursePostSerialize',
                'class' => Course::class
            ],
        ];
    }

    public function onCoursePostSerialize(ObjectEvent $event): void
    {
        /** @var Course $course */
        $course = $event->getObject();

        /** @var JsonSerializationVisitor $visitor */
        $visitor = $event->getVisitor();

        if ($visitor->hasData('type')) {
            $visitor->setData('type', $course->getStringType());
        }
    }
}
