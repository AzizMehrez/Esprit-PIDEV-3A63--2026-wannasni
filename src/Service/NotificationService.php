<?php

namespace App\Service;

use App\Entity\Notification;
use Doctrine\ORM\EntityManagerInterface;

class NotificationService
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function create(string $type, string $message, ?int $relatedId = null): Notification
    {
        $notification = new Notification();
        $notification->setType($type);
        $notification->setMessage($message);
        $notification->setRelatedId($relatedId);

        $this->em->persist($notification);
        $this->em->flush();

        return $notification;
    }
}
