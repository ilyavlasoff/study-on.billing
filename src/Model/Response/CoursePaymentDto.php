<?php

namespace App\Model\Response;

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
     * @JMS\Type("DateTime<'Y-m-d\TH:i:sP'>")
     */
    private $expiresAt;

    public function __construct(Transaction $transaction)
    {
        if ($transaction->getCourse()) {
            $this->courseType = $transaction->getCourse()->getStringType();
        }
        if ($transaction->getValidUntil()) {
            $this->expiresAt = $transaction->getValidUntil();
        }
    }

    /**
     * @return bool
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * @param bool $success
     */
    public function setSuccess(bool $success): void
    {
        $this->success = $success;
    }

    /**
     * @return string
     */
    public function getCourseType(): string
    {
        return $this->courseType;
    }

    /**
     * @param string $courseType
     */
    public function setCourseType(string $courseType): void
    {
        $this->courseType = $courseType;
    }

    /**
     * @return string
     */
    public function getExpiresAt(): string
    {
        return $this->expiresAt;
    }

    /**
     * @param string $expiresAt
     */
    public function setExpiresAt(string $expiresAt): void
    {
        $this->expiresAt = $expiresAt;
    }

}
