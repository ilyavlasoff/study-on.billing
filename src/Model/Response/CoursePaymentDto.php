<?php

namespace App\Model;

use App\Entity\Transaction;
use JMS\Serializer\Annotation as JMS;

class CoursePaymentDto
{
    /**
     * @JMS\Type("bool")
     */
    private $success = true;

    /**
     * @JMS\Type("string")
     */
    private $courseType;

    /**
     * @JMS\Type("string")
     */
    private $expiresAt;

    public function __construct(Transaction $transaction)
    {
        if ($transaction->getCourse()) {
            $this->courseType = $transaction->getCourse()->getStringType();
        }
        if ($transaction->getValidUntil()) {
            $this->expiresAt = $transaction->getValidUntil()->format('c');
        }
    }
}
