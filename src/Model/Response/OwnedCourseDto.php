<?php

namespace App\Model\Response;

use App\Entity\Course;
use JMS\Serializer\Annotation as JMS;

class OwnedCourseDto
{
    /**
     * @var string
     * @JMS\Type("string")
     */
    private $code;

    /**
     * @var string
     * @JMS\Type("string")
     */
    private $type;

    /**
     * @var string
     * @JMS\Type("string")
     */
    private $title;

    /**
     * @var float
     * @JMS\Type("float")
     */
    private $price;

    /**
     * @var bool
     * @JMS\Type("bool")
     */
    private $owned;

    /**
     * @var \DateTime | null
     * @JMS\Type("DateTime<'Y-m-d\TH:i:sP'>")
     */
    private $ownedUntil;

    /**
     * @var \DateInterval | null
     * @JMS\Type("DateInterval")
     */
    private $rentTime;

    public function __construct(Course $course)
    {
        $this->code = $course->getCode();
        $this->type = $course->getStringType();
        $this->title = $course->getTitle();
        $this->price = $course->getCost();
        $this->rentTime = $course->getRentTime();
    }

    /**
     * @return string
     */
    public function getCode(): string
    {
        return $this->code;
    }

    /**
     * @param string $code
     */
    public function setCode(string $code): void
    {
        $this->code = $code;
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @param string $type
     */
    public function setType(string $type): void
    {
        $this->type = $type;
    }

    /**
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @param string $title
     */
    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    /**
     * @return float
     */
    public function getPrice(): float
    {
        return $this->price;
    }

    /**
     * @param float $price
     */
    public function setPrice(float $price): void
    {
        $this->price = $price;
    }

    /**
     * @return bool
     */
    public function isOwned(): bool
    {
        return $this->owned;
    }

    /**
     * @param bool $owned
     */
    public function setOwned(bool $owned): void
    {
        $this->owned = $owned;
    }

    /**
     * @return \DateTime|null
     */
    public function getOwnedUntil(): ?\DateTime
    {
        return $this->ownedUntil;
    }

    /**
     * @param \DateTime|null $ownedUntil
     */
    public function setOwnedUntil(?\DateTime $ownedUntil): void
    {
        $this->ownedUntil = $ownedUntil;
    }

    /**
     * @return \DateInterval|null
     */
    public function getRentTime(): ?\DateInterval
    {
        return $this->rentTime;
    }

    /**
     * @param \DateInterval|null $rentTime
     */
    public function setRentTime(?\DateInterval $rentTime): void
    {
        $this->rentTime = $rentTime;
    }
}
