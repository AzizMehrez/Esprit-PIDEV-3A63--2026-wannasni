<?php

namespace App\Entity;

use App\Repository\MessageRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MessageRepository::class)]
#[ORM\Table(name: 'message')]
#[ORM\Index(columns: ['conversation_id', 'created_at'], name: 'idx_message_conv_date')]
class Message
{
    public const TYPE_TEXT = 'text';
    public const TYPE_VOICE = 'voice';
    public const TYPE_IMAGE = 'image';
    public const TYPE_VIDEO = 'video';
    public const TYPE_POST_SHARE = 'post_share';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Conversation::class, inversedBy: 'messages')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Conversation $conversation = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $sender = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $content = null;

    #[ORM\Column(type: 'string', length: 20)]
    private string $messageType = self::TYPE_TEXT;

    /** Path to attached media (voice message, image, video) */
    #[ORM\Column(type: 'string', length: 500, nullable: true)]
    private ?string $attachmentPath = null;

    /** If type=post_share, holds the shared post id */
    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $sharedPostId = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isRead = false;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getConversation(): ?Conversation
    {
        return $this->conversation;
    }

    public function setConversation(?Conversation $conversation): self
    {
        $this->conversation = $conversation;
        return $this;
    }

    public function getSender(): ?User
    {
        return $this->sender;
    }

    public function setSender(?User $sender): self
    {
        $this->sender = $sender;
        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(?string $content): self
    {
        $this->content = $content;
        return $this;
    }

    public function getMessageType(): string
    {
        return $this->messageType;
    }

    public function setMessageType(string $messageType): self
    {
        $this->messageType = $messageType;
        return $this;
    }

    public function getAttachmentPath(): ?string
    {
        return $this->attachmentPath;
    }

    public function setAttachmentPath(?string $attachmentPath): self
    {
        $this->attachmentPath = $attachmentPath;
        return $this;
    }

    public function getSharedPostId(): ?int
    {
        return $this->sharedPostId;
    }

    public function setSharedPostId(?int $sharedPostId): self
    {
        $this->sharedPostId = $sharedPostId;
        return $this;
    }

    public function isRead(): bool
    {
        return $this->isRead;
    }

    public function setIsRead(bool $isRead): self
    {
        $this->isRead = $isRead;
        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }
}
