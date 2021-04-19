<?php

namespace App\Model\Response;

use App\Entity\Transaction;
use JMS\Serializer\Annotation as JMS;

class TransactionHistoryDto
{
    /**
     * @JMS\Type("int")
     */
    private $id;

    /**
     * @JMS\Type("DateTime<'Y-m-d\TH:i:sP'>")
     */
    private $createdAt;

    /**
     * @JMS\Type("DateTime<'Y-m-d\TH:i:sP'>")
     */
    private $validUntil;

    /**
     * @JMS\Type("string")
     */
    private $type;

    /**
     * @JMS\Type("string")
     */
    private $courseCode;

    /**
     * @JMS\Type("float")
     */
    private $amount;

    public function __construct(Transaction $transaction)
    {
        $this->id = $transaction->getId();
        $this->createdAt = $transaction->getCreatedAt();
        $this->validUntil = $transaction->getValidUntil();
        $this->type = $transaction->getStringOperationType();
        $this->amount = $transaction->getValue();
        if ($transaction->getCourse()) {
            $this->courseCode = $transaction->getCourse()->getCode();
        }
    }

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return mixed
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * @return mixed
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @return mixed
     */
    public function getCourseCode()
    {
        return $this->courseCode;
    }

    /**
     * @return mixed
     */
    public function getAmount()
    {
        return $this->amount;
    }

    /**
     * @return \DateTimeInterface|null
     */
    public function getValidUntil(): ?\DateTimeInterface
    {
        return $this->validUntil;
    }

    /**
     * @param \DateTimeInterface|null $validUntil
     */
    public function setValidUntil(?\DateTimeInterface $validUntil): void
    {
        $this->validUntil = $validUntil;
    }

}