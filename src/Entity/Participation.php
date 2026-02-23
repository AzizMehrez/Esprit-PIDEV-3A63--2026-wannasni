<?php

namespace App\Entity;

use App\Repository\ParticipationRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Participation Entity - Represents a senior's participation in an activity
 */
#[ORM\Entity(repositoryClass: ParticipationRepository::class)]
#[ORM\Table(name: 'participations')]
class Participation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'integer', name: 'activity_id')]
    private ?int $activityId = null;

    #[ORM\Column(type: 'integer', name: 'senior_id', nullable: true)]
    private ?int $seniorId = null;

    #[ORM\Column(type: 'string', length: 50)]
    private string $status = 'registered'; // registered, attended, cancelled, inscrit, annulé

    #[ORM\Column(type: 'datetime', name: 'registration_date')]
    private ?\DateTimeInterface $registeredAt = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $rating = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $feedback = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $title = null;

    #[ORM\Column(type: 'integer', name: 'participant_id', nullable: true)]
    private ?int $participantId = null;

    #[ORM\Column(type: 'datetime', name: 'registered_at', nullable: true)]
    private ?\DateTimeInterface $registeredAtAlt = null;

    #[ORM\Column(type: 'string', length: 50, name: 'registration_method', nullable: true)]
    private ?string $registrationMethod = null;

    #[ORM\Column(type: 'integer', name: 'feedback_rating', nullable: true)]
    private ?int $feedbackRating = null;

    #[ORM\Column(type: 'text', name: 'feedback_comment', nullable: true)]
    private ?string $feedbackComment = null;

    #[ORM\Column(type: 'integer', name: 'mood_before', nullable: true)]
    private ?int $moodBefore = null;

    #[ORM\Column(type: 'integer', name: 'mood_after', nullable: true)]
    private ?int $moodAfter = null;

    #[ORM\Column(type: 'text', name: 'problems_encountered', nullable: true)]
    private ?string $problemsEncountered = null;

    #[ORM\Column(type: 'boolean', name: 'recommend_to_friends', nullable: true)]
    private ?bool $recommendToFriends = null;

    #[ORM\Column(type: 'text', name: 'photo_urls', nullable: true)]
    private ?string $photoUrls = null;

    #[ORM\Column(type: 'datetime', name: 'presence_confirmation_date', nullable: true)]
    private ?\DateTimeInterface $presenceConfirmationDate = null;

    #[ORM\Column(type: 'boolean', name: 'has_certificate', nullable: true)]
    private ?bool $hasCertificate = null;

    #[ORM\Column(type: 'string', length: 50, name: 'share_with_family', nullable: true)]
    private ?string $shareWithFamily = null;

    public function __construct()
    {
        $this->registeredAt = new \DateTime();
    }

    // Getters
    public function getId(): ?int { return $this->id; }
    public function getActivityId(): ?int { return $this->activityId; }
    public function getSeniorId(): ?int { return $this->seniorId; }
    public function getStatus(): string { return $this->status; }
    public function getRegisteredAt(): ?\DateTimeInterface { return $this->registeredAt; }
    public function getRating(): ?int { return $this->rating; }
    public function getFeedback(): ?string { return $this->feedback; }
    public function getTitle(): ?string { return $this->title; }
    public function getParticipantId(): ?int { return $this->participantId; }
    public function getRegistrationMethod(): ?string { return $this->registrationMethod; }
    public function getFeedbackRating(): ?int { return $this->feedbackRating; }
    public function getFeedbackComment(): ?string { return $this->feedbackComment; }
    public function getMoodBefore(): ?int { return $this->moodBefore; }
    public function getMoodAfter(): ?int { return $this->moodAfter; }
    public function getProblemsEncountered(): ?string { return $this->problemsEncountered; }
    public function getRecommendToFriends(): ?bool { return $this->recommendToFriends; }
    public function getPhotoUrls(): ?string { return $this->photoUrls; }
    public function getPresenceConfirmationDate(): ?\DateTimeInterface { return $this->presenceConfirmationDate; }
    public function getHasCertificate(): ?bool { return $this->hasCertificate; }
    public function getShareWithFamily(): ?string { return $this->shareWithFamily; }

    // Setters
    public function setId(?int $id): self { $this->id = $id; return $this; }
    public function setActivityId(?int $activityId): self { $this->activityId = $activityId; return $this; }
    public function setSeniorId(?int $seniorId): self { $this->seniorId = $seniorId; return $this; }
    public function setStatus(string $status): self { $this->status = $status; return $this; }
    public function setRegisteredAt(?\DateTimeInterface $registeredAt): self { $this->registeredAt = $registeredAt; return $this; }
    public function setRating(?int $rating): self { $this->rating = $rating; return $this; }
    public function setFeedback(?string $feedback): self { $this->feedback = $feedback; return $this; }
    public function setTitle(?string $title): self { $this->title = $title; return $this; }
    public function setParticipantId(?int $participantId): self { $this->participantId = $participantId; return $this; }
    public function setRegistrationMethod(?string $registrationMethod): self { $this->registrationMethod = $registrationMethod; return $this; }
    public function setFeedbackRating(?int $feedbackRating): self { $this->feedbackRating = $feedbackRating; return $this; }
    public function setFeedbackComment(?string $feedbackComment): self { $this->feedbackComment = $feedbackComment; return $this; }
    public function setMoodBefore(?int $moodBefore): self { $this->moodBefore = $moodBefore; return $this; }
    public function setMoodAfter(?int $moodAfter): self { $this->moodAfter = $moodAfter; return $this; }
    public function setProblemsEncountered(?string $problemsEncountered): self { $this->problemsEncountered = $problemsEncountered; return $this; }
    public function setRecommendToFriends(?bool $recommendToFriends): self { $this->recommendToFriends = $recommendToFriends; return $this; }
    public function setPhotoUrls(?string $photoUrls): self { $this->photoUrls = $photoUrls; return $this; }
    public function setPresenceConfirmationDate(?\DateTimeInterface $date): self { $this->presenceConfirmationDate = $date; return $this; }
    public function setHasCertificate(?bool $hasCertificate): self { $this->hasCertificate = $hasCertificate; return $this; }
    public function setShareWithFamily(?string $shareWithFamily): self { $this->shareWithFamily = $shareWithFamily; return $this; }
}
