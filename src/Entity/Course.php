<?php

namespace App\Entity;

use App\Repository\CourseRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use JMS\Serializer\Annotation as JMS;

/**
 * @ORM\Entity(repositoryClass=CourseRepository::class)
 * @UniqueEntity("code", message="This code is already exists")
 * @JMS\ExclusionPolicy("none")
 */
class Course
{
    private const COURSE_TYPES = [
        0 => 'free',
        1 => 'rent',
        2 => 'buy'
    ];

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     * @JMS\Exclude()
     * @var int
     */
    private $id;

    /**
     * @ORM\Column(type="string", unique=true, nullable=false, length=255)
     * @Assert\NotNull(message="Course code can not be nullable")
     * @Assert\NotBlank(message="Course code can not be blank")
     * @Assert\Length(max="255", maxMessage="Maximal code string length is {{ limit }} symbols")
     * @JMS\Type("string")
     * @JMS\Groups({"edit", "create"})
     * @var string | null
     */
    private $code;

    /**
     * @ORM\Column(type="smallint", nullable=false)
     * @Assert\NotNull(message="Type not found")
     * @Assert\NotBlank(message="Type can not be blank")
     * @JMS\Accessor(getter="getStringType", setter="setStringType")
     * @JMS\Type("string")
     * @JMS\Groups({"edit", "create"})
     * @var int | null
     */
    private $type;

    /**
     * @ORM\Column(type="string", length=255, nullable=false)
     * @Assert\NotNull(message="Course must contain title")
     * @Assert\NotBlank(message="Course title can not be blank")
     * @Assert\Length(max=255, maxMessage="Maximal title length is {{ limit }} symbols")
     * @JMS\Type("string")
     * @JMS\Groups({"edit", "create"})
     * @var string | null
     */
    private $title;

    /**
     * @ORM\Column(type="float", nullable=true)
     * @Assert\Range(min=0, minMessage="Cost can not be negative")
     * @JMS\Type("float")
     * @JMS\Groups({"edit", "create"})
     * @JMS\SerializedName("price")
     * @var float | null
     */
    private $cost;

    /**
     * @ORM\Column(type="dateinterval", nullable=true)
     * @JMS\Groups({"edit", "create"})
     * @var \DateInterval | null
     */
    private $rentTime;

    /**
     * @var bool
     * @ORM\Column(type="boolean", nullable=false)
     * @JMS\Exclude()
     */
    private $active;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Transaction", mappedBy="course")
     * @JMS\Exclude()
     */
    private $operations;

    /**
     * @param ExecutionContextInterface $context
     * @param $payload
     * @Assert\Callback()
     */
    public function validate(ExecutionContextInterface $context, $payload)
    {
        if ($this->type === array_flip(self::COURSE_TYPES)['rent']) {
            if (!$this->rentTime) {
                $context->buildViolation('This course must contain rent time')
                    ->atPath('rentTime')
                    ->addViolation();
            }

            if (!$this->cost) {
                $context->buildViolation('Rent course can not be free')
                    ->atPath('rentTime')
                    ->addViolation();
            }
        }

        if ($this->type !== array_flip(self::COURSE_TYPES)['rent'] && $this->rentTime) {
            $context->buildViolation('Non-rent course can not contain rent time value')
                ->atPath('rentTime')
                ->addViolation();
        }

        if ($this->type === array_flip(self::COURSE_TYPES)['free'] && $this->cost) {
            $context->buildViolation('Free course can not contain cost value')
                ->atPath('cost')
                ->addViolation();
        }

        if ($this->type && !array_key_exists($this->type, self::COURSE_TYPES)) {
            $context->buildViolation('Incorrect course type, available only [free, rent, buy] types')
                ->atPath('type')
                ->addViolation();
        }
    }

    public function setStringType(string $stringType): void
    {
        if (!array_key_exists($stringType, array_flip(self::COURSE_TYPES))) {
            $this->type = -1;
        } else {
            $this->type = array_flip(self::COURSE_TYPES)[$stringType];
        }
    }

    public function getStringType(): string
    {
        return self::COURSE_TYPES[$this->type];
    }

    public function __construct()
    {
        $this->operations = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return Collection|Transaction[]
     */
    public function getOperations(): Collection
    {
        return $this->operations;
    }

    public function addOperation(Transaction $operation): self
    {
        if (!$this->operations->contains($operation)) {
            $this->operations[] = $operation;
            $operation->setCourse($this);
        }

        return $this;
    }

    public function removeOperation(Transaction $operation): self
    {
        if ($this->operations->removeElement($operation)) {
            // set the owning side to null (unless already changed)
            if ($operation->getCourse() === $this) {
                $operation->setCourse(null);
            }
        }

        return $this;
    }

    /**
     * @return string|null
     */
    public function getTitle(): ?string
    {
        return $this->title;
    }

    /**
     * @param string|null $title
     */
    public function setTitle(?string $title): void
    {
        $this->title = $title;
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

    /**
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->active;
    }

    /**
     * @param bool $active
     */
    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    /**
     * @return string|null
     */
    public function getCode(): ?string
    {
        return $this->code;
    }

    /**
     * @param string|null $code
     */
    public function setCode(?string $code): void
    {
        $this->code = $code;
    }

    /**
     * @return int|null
     */
    public function getType(): ?int
    {
        return $this->type;
    }

    /**
     * @param int|null $type
     */
    public function setType(?int $type): void
    {
        $this->type = $type;
    }

    /**
     * @return float|null
     */
    public function getCost(): ?float
    {
        return $this->cost;
    }

    /**
     * @param float|null $cost
     */
    public function setCost(?float $cost): void
    {
        $this->cost = $cost;
    }

}
