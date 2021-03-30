<?php

namespace App\Model;

use App\Entity\Transaction;
use JMS\Serializer\Annotation as JMS;

class TransactionHistoryDto
{
    /**
     * @JMS\Type("int")
     */
    private $id;

    /**
     * @JMS\Type("string")
     */
    private $createdAt;

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
        $this->createdAt = $transaction->getCreatedAt()->format('c');
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


}