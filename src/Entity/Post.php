<?php

namespace App\Entity;

use App\Repository\PostRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PostRepository::class)]
#[ORM\Table(name: 'post')]
#[ORM\Index(columns: ['created_at'], name: 'idx_post_created')]
#[ORM\Index(columns: ['author_id', 'created_at'], name: 'idx_post_author_date')]
class Post
{
    public const TYPE_POST = 'post';
    public const TYPE_VIDEO = 'video';
    public const TYPE_REEL = 'reel';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $author = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $content = null;

    #[ORM\Column(type: 'string', length: 20)]
    private string $type = self::TYPE_POST; // post, video, reel

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\OneToMany(targetEntity: PostMedia::class, mappedBy: 'post', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $media;

    #[ORM\OneToMany(targetEntity: PostLike::class, mappedBy: 'post', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $likes;

    #[ORM\OneToMany(targetEntity: PostComment::class, mappedBy: 'post', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $comments;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->media = new ArrayCollection();
        $this->likes = new ArrayCollection();
        $this->comments = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAuthor(): ?User
    {
        return $this->author;
    }

    public function setAuthor(?User $author): self
    {
        $this->author = $author;
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

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    protected function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    protected function setUpdatedAt(?\DateTimeInterface $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    /** @return Collection<int, PostMedia> */
    public function getMedia(): Collection
    {
        return $this->media;
    }

    public function addMedia(PostMedia $media): self
    {
        if (!$this->media->contains($media)) {
            $this->media->add($media);
            $media->setPost($this);
        }
        return $this;
    }

    /** @return Collection<int, PostLike> */
    public function getLikes(): Collection
    {
        return $this->likes;
    }

    public function getLikesCount(): int
    {
        return $this->likes->count();
    }

    /** @return Collection<int, PostComment> */
    public function getComments(): Collection
    {
        return $this->comments;
    }

    public function getCommentsCount(): int
    {
        return $this->comments->count();
    }
}
