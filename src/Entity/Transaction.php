<?php

namespace App\Entity;

use App\Repository\TransactionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * @ORM\Entity(repositoryClass=TransactionRepository::class)
 */
class Transaction
{
    private const STRING_OPERATION_TYPES = [
        0 => 'payment',
        1 => 'deposit',
    ];

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\User", inversedBy="transactions")
     * @ORM\JoinColumn(nullable=false)
     */
    private $user;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Course", inversedBy="operations")
     * @ORM\JoinColumn(nullable=true)
     */
    private $course;

    /**
     * @ORM\Column(type="smallint", nullable=false)
     * @Assert\NotNull(message="Operation type not found")
     */
    private $operationType;

    /**
     * @ORM\Column(type="float", nullable=true)
     */
    private $value;

    /**
     * @ORM\Column(type="datetime", nullable=false)
     */
    private $createdAt;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $validUntil;

    /**
     * @param ExecutionContextInterface $context
     * @param $payload
     * @Assert\Callback()
     */
    public function validate(ExecutionContextInterface $context, $payload)
    {
        if (!$this->validUntil && $this->getCourse() && 1 === $this->getStringOperationType()) {
            $context->buildViolation('Valid until value is required')
                ->atPath('validUntil')
                ->addViolation();
        }
    }

    public function setStringOperationType(string $operationType): void
    {
        if (!array_key_exists($operationType, array_flip(self::STRING_OPERATION_TYPES))) {
            throw new \ValueError('Operation type is invalid');
        }
        $this->operationType = array_flip(self::STRING_OPERATION_TYPES)[$operationType];
    }

    public function getStringOperationType(): string
    {
        return self::STRING_OPERATION_TYPES[$this->getOperationType()];
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOperationType(): ?int
    {
        return $this->operationType;
    }

    public function setOperationType(int $operationType): self
    {
        $this->operationType = $operationType;

        return $this;
    }

    public function getValue(): ?float
    {
        return $this->value;
    }

    public function setValue(?float $value): self
    {
        $this->value = $value;

        return $this;
    }

    public function getValidUntil(): ?\DateTimeInterface
    {
        return $this->validUntil;
    }

    public function setValidUntil(?\DateTimeInterface $validUntil): self
    {
        $this->validUntil = $validUntil;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getCourse(): ?Course
    {
        return $this->course;
    }

    public function setCourse(?Course $course): self
    {
        $this->course = $course;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }
}
