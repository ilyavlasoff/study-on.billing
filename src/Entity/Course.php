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
     * @Assert\Length(max="255", maxMessage="Maximal code string length is {{ limit }} symbols, given {{ value }}")
     * @JMS\Type("string")
     * @JMS\Groups({"edit", "create"})
     * @var string
     */
    private $code;

    /**
     * @ORM\Column(type="smallint", nullable=false)
     * @Assert\NotNull(message="Type not found")
     * @JMS\Accessor(getter="getStringType", setter="setStringType")
     * @JMS\Type("string")
     * @JMS\Groups({"edit", "create"})
     */
    private $type;

    /**
     * @ORM\Column(type="string", length=255, nullable=false)
     * @Assert\NotNull(message="Course must contain title")
     * @JMS\Type("string")
     * @JMS\Groups({"edit", "create"})
     * @var string
     */
    private $title;

    /**
     * @ORM\Column(type="float", nullable=true)
     * @JMS\Type("float")
     * @JMS\Groups({"edit", "create"})
     * @JMS\SerializedName("price")
     * @var float
     */
    private $cost;

    /**
     * @ORM\Column(type="dateinterval", nullable=true)
     * @JMS\Groups({"edit", "create"})
     * @var \DateInterval
     */
    private $rentTime;

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
        if ($this->type === array_flip(self::COURSE_TYPES)['rent'] && !$this->rentTime) {
            $context->buildViolation('This course must contain rent time')
                ->atPath('rentTime')
                ->addViolation();
        } elseif ($this->type !== array_flip(self::COURSE_TYPES)['rent'] && $this->rentTime) {
            $context->buildViolation('Rent course can not contain rent time value')
                ->atPath('rentTime')
                ->addViolation();
        }

        if ($this->type === array_flip(self::COURSE_TYPES)['free'] && $this->cost) {
            $context->buildViolation('Free course can not contain cost value')
                ->atPath('cost')
                ->addViolation();
        }
    }

    public function setStringType(string $stringType): void
    {
        if (!array_key_exists($stringType, array_flip(self::COURSE_TYPES))) {
            throw new \ValueError('This type doesnt exists');
        }

        $this->type = array_flip(self::COURSE_TYPES)[$stringType];
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

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): self
    {
        $this->code = $code;

        return $this;
    }

    public function getType(): ?int
    {
        return $this->type;
    }

    public function setType(int $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getCost(): ?float
    {
        return $this->cost;
    }

    public function setCost(?float $cost): self
    {
        $this->cost = $cost;

        return $this;
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

    public function getRentTime(): ?\DateInterval
    {
        return $this->rentTime;
    }

    public function setRentTime(?\DateInterval $rentTime): self
    {
        $this->rentTime = $rentTime;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }
}
