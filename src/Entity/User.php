<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use App\Model\User as UserDto;
use Swagger\Annotations as SWG;

/**
 * @ORM\Entity(repositoryClass=UserRepository::class)
 * @ORM\Table(name="billing_user")
 * @UniqueEntity(
 *   fields={"email"},
 *   message="User with email {{ value }} is already exists. Try to login instead"
 * )
 * @Serializer\ExclusionPolicy("all")
 */
class User implements UserInterface
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     * @SWG\Property(description="The unique database identifier of user")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=180, unique=true)
     * @Serializer\Expose()
     * @Serializer\SerializedName("username")
     * @SWG\Property(type="string", maxLength=180, description="User unique email")
     */
    private $email;

    /**
     * @ORM\Column(type="json")
     * @Serializer\Expose()
     * @SWG\Property(type="array", description="Array of user action permissions ")
     */
    private $roles = [];

    /**
     * @var string The hashed password
     * @ORM\Column(type="string")
     */
    private $password;

    /**
     * @var float
     * @ORM\Column(type="float")
     * @Serializer\Expose()
     * @SWG\Property(type="float", description="Cash amount available in user account")
     */
    private $balance;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUsername(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    public function setRoles(array $roles): self
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function getPassword(): string
    {
        return (string) $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    /**
     * Returning a salt is only needed, if you are not using a modern
     * hashing algorithm (e.g. bcrypt or sodium) in your security.yaml.
     *
     * @see UserInterface
     */
    public function getSalt(): ?string
    {
        return null;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials()
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }

    public static function fromDto(UserDto $userDto, UserPasswordEncoderInterface $encoder): self
    {
        $user = new self();
        $user->setEmail($userDto->getEmail());
        $user->setRoles(['ROLE_USER']);
        $encodedPassword = $encoder->encodePassword($user, $userDto->getPassword());
        $user->setPassword($encodedPassword);
        $user->setBalance(0);

        return $user;
    }

    public function getBalance(): ?float
    {
        return $this->balance;
    }

    public function setBalance(float $balance): self
    {
        $this->balance = $balance;

        return $this;
    }
}
