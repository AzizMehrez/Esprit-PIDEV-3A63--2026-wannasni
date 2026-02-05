<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Participation Entity - Represents a senior's participation in an activity
 */
#[ORM\Entity]
#[ORM\Table(name: 'participations')]
class Participation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'integer')]
    private ?int $activityId = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $participantId = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $status = null; // inscrit / présent / absent_excusé / absent_non_excusé

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $registrationDate = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $registrationMethod = null; // appli / téléphone / en_personne

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $feedbackRating = null; // 1-5 stars

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $feedbackComment = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $moodBefore = null; // 1-5

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $moodAfter = null; // 1-5

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $problemsEncountered = null;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $recommendToFriends = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $photoUrls = null; // JSON array of URLs

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $presenceConfirmationDate = null;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $hasCertificate = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $shareWithFamily = null; // oui / non

    // Legacy fields for backward compatibility
    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $seniorId = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $registeredAt = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $title = null; // legacy field

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $rating = null; // legacy field (use feedbackRating)

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $feedback = null; // legacy field (use feedbackComment)

    public function __construct()
    {
        $this->registrationDate = new \DateTime();
    }

    // ============ GETTERS & SETTERS ============

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getActivityId(): ?int
    {
        return $this->activityId;
    }

    public function setActivityId(?int $activityId): self
    {
        $this->activityId = $activityId;
        return $this;
    }

    public function getParticipantId(): ?int
    {
        return $this->participantId;
    }

    public function setParticipantId(?int $participantId): self
    {
        $this->participantId = $participantId;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getRegistrationDate(): ?\DateTimeInterface
    {
        return $this->registrationDate;
    }

    public function setRegistrationDate(?\DateTimeInterface $registrationDate): self
    {
        $this->registrationDate = $registrationDate;
        return $this;
    }

    public function getRegistrationMethod(): ?string
    {
        return $this->registrationMethod;
    }

    public function setRegistrationMethod(?string $registrationMethod): self
    {
        $this->registrationMethod = $registrationMethod;
        return $this;
    }

    public function getFeedbackRating(): ?int
    {
        return $this->feedbackRating;
    }

    public function setFeedbackRating(?int $feedbackRating): self
    {
        $this->feedbackRating = $feedbackRating;
        return $this;
    }

    public function getFeedbackComment(): ?string
    {
        return $this->feedbackComment;
    }

    public function setFeedbackComment(?string $feedbackComment): self
    {
        $this->feedbackComment = $feedbackComment;
        return $this;
    }

    public function getMoodBefore(): ?int
    {
        return $this->moodBefore;
    }

    public function setMoodBefore(?int $moodBefore): self
    {
        $this->moodBefore = $moodBefore;
        return $this;
    }

    public function getMoodAfter(): ?int
    {
        return $this->moodAfter;
    }

    public function setMoodAfter(?int $moodAfter): self
    {
        $this->moodAfter = $moodAfter;
        return $this;
    }

    public function getProblemsEncountered(): ?string
    {
        return $this->problemsEncountered;
    }

    public function setProblemsEncountered(?string $problemsEncountered): self
    {
        $this->problemsEncountered = $problemsEncountered;
        return $this;
    }

    public function getRecommendToFriends(): ?bool
    {
        return $this->recommendToFriends;
    }

    public function setRecommendToFriends(?bool $recommendToFriends): self
    {
        $this->recommendToFriends = $recommendToFriends;
        return $this;
    }

    public function getPhotoUrls(): ?string
    {
        return $this->photoUrls;
    }

    public function setPhotoUrls(?string $photoUrls): self
    {
        $this->photoUrls = $photoUrls;
        return $this;
    }

    public function getPresenceConfirmationDate(): ?\DateTimeInterface
    {
        return $this->presenceConfirmationDate;
    }

    public function setPresenceConfirmationDate(?\DateTimeInterface $presenceConfirmationDate): self
    {
        $this->presenceConfirmationDate = $presenceConfirmationDate;
        return $this;
    }

    public function getHasCertificate(): ?bool
    {
        return $this->hasCertificate;
    }

    public function setHasCertificate(?bool $hasCertificate): self
    {
        $this->hasCertificate = $hasCertificate;
        return $this;
    }

    public function getShareWithFamily(): ?string
    {
        return $this->shareWithFamily;
    }

    public function setShareWithFamily(?string $shareWithFamily): self
    {
        $this->shareWithFamily = $shareWithFamily;
        return $this;
    }

    // ============ LEGACY GETTERS & SETTERS ============

    public function getSeniorId(): ?int
    {
        return $this->seniorId ?? $this->participantId;
    }

    public function setSeniorId(?int $seniorId): self
    {
        $this->seniorId = $seniorId;
        return $this;
    }

    public function getRegisteredAt(): ?\DateTimeInterface
    {
        return $this->registeredAt ?? $this->registrationDate;
    }

    public function setRegisteredAt(?\DateTimeInterface $registeredAt): self
    {
        $this->registeredAt = $registeredAt;
        return $this;
    }

    public function getRating(): ?int
    {
        return $this->rating ?? $this->feedbackRating;
    }

    public function setRating(?int $rating): self
    {
        $this->rating = $rating;
        return $this;
    }

    public function getFeedback(): ?string
    {
        return $this->feedback ?? $this->feedbackComment;
    }

    public function setFeedback(?string $feedback): self
    {
        $this->feedback = $feedback;
        return $this;
    }
}
