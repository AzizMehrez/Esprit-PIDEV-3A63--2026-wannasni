<?php

namespace App\Entity;

use App\Repository\PostMediaRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PostMediaRepository::class)]
#[ORM\Table(name: 'post_media')]
class PostMedia
{
    public const TYPE_IMAGE = 'image';
    public const TYPE_VIDEO = 'video';
    public const TYPE_VOICE = 'voice';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Post::class, inversedBy: 'media')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Post $post = null;

    #[ORM\Column(type: 'string', length: 500)]
    private string $filePath = '';

    #[ORM\Column(type: 'string', length: 20)]
    private string $mediaType = self::TYPE_IMAGE; // image, video, voice

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $originalName = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $fileSize = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $sortOrder = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPost(): ?Post
    {
        return $this->post;
    }

    public function setPost(?Post $post): self
    {
        $this->post = $post;
        return $this;
    }

    public function getFilePath(): string
    {
        return $this->filePath;
    }

    public function setFilePath(string $filePath): self
    {
        $this->filePath = $filePath;
        return $this;
    }

    public function getMediaType(): string
    {
        return $this->mediaType;
    }

    public function setMediaType(string $mediaType): self
    {
        $this->mediaType = $mediaType;
        return $this;
    }

    public function getOriginalName(): ?string
    {
        return $this->originalName;
    }

    public function setOriginalName(?string $originalName): self
    {
        $this->originalName = $originalName;
        return $this;
    }

    public function getFileSize(): ?int
    {
        return $this->fileSize;
    }

    public function setFileSize(?int $fileSize): self
    {
        $this->fileSize = $fileSize;
        return $this;
    }

    public function getSortOrder(): ?int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(?int $sortOrder): self
    {
        $this->sortOrder = $sortOrder;
        return $this;
    }
}
