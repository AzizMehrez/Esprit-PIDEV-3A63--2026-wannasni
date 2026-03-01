<?php

namespace App\Entity;

use App\Repository\ConversationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ConversationRepository::class)]
#[ORM\Table(name: 'conversation')]
#[ORM\UniqueConstraint(name: 'unique_conversation_pair', columns: ['user_a_id', 'user_b_id'])]
class Conversation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    /** user_a_id always has the lower id */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $userA = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $userB = null;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $lastMessageAt = null;

    #[ORM\OneToMany(targetEntity: Message::class, mappedBy: 'conversation', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'ASC'])]
    private Collection $messages;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->messages = new ArrayCollection();
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

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getLastMessageAt(): ?\DateTimeInterface
    {
        return $this->lastMessageAt;
    }

    public function setLastMessageAt(?\DateTimeInterface $lastMessageAt): self
    {
        $this->lastMessageAt = $lastMessageAt;
        return $this;
    }

    /** @return Collection<int, Message> */
    public function getMessages(): Collection
    {
        return $this->messages;
    }

    public function getOtherUser(User $me): ?User
    {
        if ($this->userA && $this->userA->getId() === $me->getId()) {
            return $this->userB;
        }
        return $this->userA;
    }

    public function involvesUser(User $user): bool
    {
        return ($this->userA && $this->userA->getId() === $user->getId())
            || ($this->userB && $this->userB->getId() === $user->getId());
    }
}
