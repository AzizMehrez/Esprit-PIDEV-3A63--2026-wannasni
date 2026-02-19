<?php

namespace App\Entity;

use App\Repository\UserConnectionRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Represents an accepted connection between two users.
 * Created when an invite is accepted; enables messaging.
 */
#[ORM\Entity(repositoryClass: UserConnectionRepository::class)]
#[ORM\Table(name: 'user_connection')]
#[ORM\UniqueConstraint(name: 'unique_connection_pair', columns: ['user_a_id', 'user_b_id'])]
class UserConnection
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    /** user_a_id is always the lower id to avoid duplicates */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $userA = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $userB = null;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $connectedAt;

    public function __construct()
    {
        $this->connectedAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUserA(): ?User
    {
        return $this->userA;
    }

    public function setUserA(?User $userA): self
    {
        $this->userA = $userA;
        return $this;
    }

    public function getUserB(): ?User
    {
        return $this->userB;
    }

    public function setUserB(?User $userB): self
    {
        $this->userB = $userB;
        return $this;
    }

    public function getConnectedAt(): \DateTimeInterface
    {
        return $this->connectedAt;
    }

    /**
     * Given one side of the connection, return the other user.
     */
    public function getOtherUser(User $me): ?User
    {
        if ($this->userA && $this->userA->getId() === $me->getId()) {
            return $this->userB;
        }
        return $this->userA;
    }
}
