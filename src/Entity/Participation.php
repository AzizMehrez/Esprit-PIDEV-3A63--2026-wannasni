<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\ParticipationRepository;

/**
 * Participation Entity - Represents a senior's participation in an activity
 * Standalone entity with separate table
 */
#[ORM\Entity(repositoryClass: ParticipationRepository::class)]
#[ORM\Table(name: 'participations')]
class Participation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Activity::class)]
    #[ORM\JoinColumn(name: 'activity_id', referencedColumnName: 'id', nullable: false)]
    private ?Activity $activity = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $seniorId = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $participantId = null;

    #[ORM\Column(type: 'string', length: 50)]
    private string $status = 'inscrit'; // inscrit, annulé, présent, absent_excusé, absent_non_excusé

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $registrationDate = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $registeredAt = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $registrationMethod = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $rating = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $feedbackRating = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $feedback = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $feedbackComment = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $moodBefore = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $moodAfter = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $problemsEncountered = null;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $recommendToFriends = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $photoUrls = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $presenceConfirmationDate = null;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $hasCertificate = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $shareWithFamily = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $title = null;

    public function __construct()
    {
        $this->registrationDate = new \DateTime();
        $this->registeredAt = new \DateTime();
    }

    // Getters
    public function getId(): ?int { return $this->id; }
    public function getActivity(): ?Activity { return $this->activity; }
    public function getSeniorId(): ?int { return $this->seniorId; }
    public function getParticipantId(): ?int { return $this->participantId; }
    public function getStatus(): string { return $this->status; }
    public function getRegistrationDate(): ?\DateTimeInterface { return $this->registrationDate; }
    public function getRegisteredAt(): ?\DateTimeInterface { return $this->registeredAt; }
    public function getRegistrationMethod(): ?string { return $this->registrationMethod; }
    public function getRating(): ?int { return $this->rating; }
    public function getFeedbackRating(): ?int { return $this->feedbackRating; }
    public function getFeedback(): ?string { return $this->feedback; }
    public function getFeedbackComment(): ?string { return $this->feedbackComment; }
    public function getMoodBefore(): ?int { return $this->moodBefore; }
    public function getMoodAfter(): ?int { return $this->moodAfter; }
    public function getProblemsEncountered(): ?string { return $this->problemsEncountered; }
    public function getRecommendToFriends(): ?bool { return $this->recommendToFriends; }
    public function getPhotoUrls(): ?string { return $this->photoUrls; }
    public function getPresenceConfirmationDate(): ?\DateTimeInterface { return $this->presenceConfirmationDate; }
    public function getHasCertificate(): ?bool { return $this->hasCertificate; }
    public function getShareWithFamily(): ?string { return $this->shareWithFamily; }
    public function getTitle(): ?string { return $this->title ?? $this->activity?->getTitle(); }

    // Setters
    public function setActivity(?Activity $activity): self { $this->activity = $activity; return $this; }
    public function setSeniorId(?int $seniorId): self { $this->seniorId = $seniorId; return $this; }
    public function setParticipantId(?int $participantId): self { $this->participantId = $participantId; return $this; }
    public function setStatus(string $status): self { $this->status = $status; return $this; }
    public function setRegistrationDate(?\DateTimeInterface $registrationDate): self { $this->registrationDate = $registrationDate; return $this; }
    public function setRegisteredAt(?\DateTimeInterface $registeredAt): self { $this->registeredAt = $registeredAt; return $this; }
    public function setRegistrationMethod(?string $registrationMethod): self { $this->registrationMethod = $registrationMethod; return $this; }
    public function setRating(?int $rating): self { $this->rating = $rating; return $this; }
    public function setFeedbackRating(?int $rating): self { $this->feedbackRating = $rating; return $this; }
    public function setFeedback(?string $feedback): self { $this->feedback = $feedback; return $this; }
    public function setFeedbackComment(?string $feedback): self { $this->feedbackComment = $feedback; return $this; }
    public function setMoodBefore(?int $moodBefore): self { $this->moodBefore = $moodBefore; return $this; }
    public function setMoodAfter(?int $moodAfter): self { $this->moodAfter = $moodAfter; return $this; }
    public function setProblemsEncountered(?string $problemsEncountered): self { $this->problemsEncountered = $problemsEncountered; return $this; }
    public function setRecommendToFriends(?bool $recommendToFriends): self { $this->recommendToFriends = $recommendToFriends; return $this; }
    public function setPhotoUrls(?string $photoUrls): self { $this->photoUrls = $photoUrls; return $this; }
    public function setPresenceConfirmationDate(?\DateTimeInterface $presenceConfirmationDate): self { $this->presenceConfirmationDate = $presenceConfirmationDate; return $this; }
    public function setHasCertificate(?bool $hasCertificate): self { $this->hasCertificate = $hasCertificate; return $this; }
    public function setShareWithFamily(?string $shareWithFamily): self { $this->shareWithFamily = $shareWithFamily; return $this; }
    public function setTitle(?string $title): self { $this->title = $title; return $this; }

    // Delegate methods for Activity properties (for template compatibility)
    public function getDescription(): ?string { return $this->activity?->getDescription(); }
    public function getType(): ?string { return $this->activity?->getType(); }
    public function getStartTime(): ?\DateTimeInterface { return $this->activity?->getStartTime(); }
    public function getLocation(): ?string { return $this->activity?->getLocation(); }
    
    // Alias getters for template compatibility - keep backward compatibility
    public function getParticipantActivityId(): ?int { return $this->participantId; }
}
