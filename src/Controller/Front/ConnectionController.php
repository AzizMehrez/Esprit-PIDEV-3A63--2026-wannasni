<?php

namespace App\Controller\Front;

use App\Entity\ConnectionInvite;
use App\Entity\User;
use App\Entity\UserConnection;
use App\Repository\ConnectionInviteRepository;
use App\Repository\UserConnectionRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/{_locale}/networking/connections', requirements: ['_locale' => 'fr|en|ar'])]
class ConnectionController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private ConnectionInviteRepository $inviteRepo,
        private UserConnectionRepository $connectionRepo,
        private UserRepository $userRepo,
    ) {}

    // ─── Send Invite ─────────────────────────────────────────────────
    #[Route('/invite/{id}', name: 'app_networking_invite_send', methods: ['POST'])]
    public function sendInvite(int $id): JsonResponse
    {
        /** @var User $me */
        $me = $this->getUser();
        $target = $this->userRepo->find($id);

        if (!$target || $target->getId() === $me->getId()) {
            return new JsonResponse(['error' => 'Invalid user'], 400);
        }

        // Check if already connected
        if ($this->connectionRepo->areConnected($me, $target)) {
            return new JsonResponse(['error' => 'Already connected'], 400);
        }

        // Check if invite already exists
        $existing = $this->inviteRepo->findExistingInvite($me, $target);
        if ($existing) {
            return new JsonResponse(['error' => 'Invite already pending'], 400);
        }

        $invite = new ConnectionInvite();
        $invite->setSender($me);
        $invite->setReceiver($target);

        $this->em->persist($invite);
        $this->em->flush();

        return new JsonResponse(['success' => true, 'message' => 'Invitation envoyée']);
    }

    // ─── Accept Invite ───────────────────────────────────────────────
    #[Route('/accept/{id}', name: 'app_networking_invite_accept', methods: ['POST'])]
    public function acceptInvite(int $id): JsonResponse
    {
        /** @var User $me */
        $me = $this->getUser();
        $invite = $this->inviteRepo->find($id);

        if (!$invite || $invite->getReceiver()->getId() !== $me->getId()) {
            return new JsonResponse(['error' => 'Invite not found'], 404);
        }

        if (!$invite->isPending()) {
            return new JsonResponse(['error' => 'Invite already processed'], 400);
        }

        // Update invite status
        $invite->setStatus(ConnectionInvite::STATUS_ACCEPTED);
        $invite->setRespondedAt(new \DateTime());

        // Create connection (lower id as userA)
        $ids = [$me->getId(), $invite->getSender()->getId()];
        sort($ids);
        $userA = $this->userRepo->find($ids[0]);
        $userB = $this->userRepo->find($ids[1]);

        $connection = new UserConnection();
        $connection->setUserA($userA);
        $connection->setUserB($userB);

        $this->em->persist($connection);
        $this->em->flush();

        return new JsonResponse([
            'success' => true,
            'message' => 'Invitation acceptée',
            'friend' => [
                'id' => $invite->getSender()->getId(),
                'name' => $invite->getSender()->getFullName(),
                'avatar' => $invite->getSender()->getImageProfil(),
            ],
        ]);
    }

    // ─── Reject Invite ───────────────────────────────────────────────
    #[Route('/reject/{id}', name: 'app_networking_invite_reject', methods: ['POST'])]
    public function rejectInvite(int $id): JsonResponse
    {
        /** @var User $me */
        $me = $this->getUser();
        $invite = $this->inviteRepo->find($id);

        if (!$invite || $invite->getReceiver()->getId() !== $me->getId()) {
            return new JsonResponse(['error' => 'Invite not found'], 404);
        }

        if (!$invite->isPending()) {
            return new JsonResponse(['error' => 'Invite already processed'], 400);
        }

        $invite->setStatus(ConnectionInvite::STATUS_REJECTED);
        $invite->setRespondedAt(new \DateTime());
        $this->em->flush();

        return new JsonResponse(['success' => true, 'message' => 'Invitation refusée']);
    }

    // ─── List Pending Invites ────────────────────────────────────────
    #[Route('/pending', name: 'app_networking_invites_pending', methods: ['GET'])]
    public function pendingInvites(): JsonResponse
    {
        /** @var User $me */
        $me = $this->getUser();
        $invites = $this->inviteRepo->findPendingForUser($me);

        $data = [];
        foreach ($invites as $inv) {
            $data[] = [
                'id' => $inv->getId(),
                'senderId' => $inv->getSender()->getId(),
                'senderName' => $inv->getSender()->getFullName(),
                'senderAvatar' => $inv->getSender()->getImageProfil(),
                'senderEmail' => $inv->getSender()->getEmail(),
                'createdAt' => $inv->getCreatedAt()->format('Y-m-d H:i'),
            ];
        }

        return new JsonResponse(['invites' => $data, 'count' => count($data)]);
    }

    // ─── List Friends ────────────────────────────────────────────────
    #[Route('/friends', name: 'app_networking_friends', methods: ['GET'])]
    public function listFriends(): JsonResponse
    {
        /** @var User $me */
        $me = $this->getUser();
        $connections = $this->connectionRepo->findConnectionsForUser($me);

        $data = [];
        foreach ($connections as $conn) {
            $friend = $conn->getOtherUser($me);
            $data[] = [
                'id' => $friend->getId(),
                'name' => $friend->getFullName(),
                'avatar' => $friend->getImageProfil(),
                'email' => $friend->getEmail(),
                'connectedAt' => $conn->getConnectedAt()->format('Y-m-d H:i'),
            ];
        }

        return new JsonResponse(['friends' => $data]);
    }

    // ─── Remove Friend ──────────────────────────────────────────────
    #[Route('/remove/{id}', name: 'app_networking_friend_remove', methods: ['POST'])]
    public function removeFriend(int $id): JsonResponse
    {
        /** @var User $me */
        $me = $this->getUser();
        $target = $this->userRepo->find($id);

        if (!$target) {
            return new JsonResponse(['error' => 'User not found'], 404);
        }

        $ids = [$me->getId(), $target->getId()];
        sort($ids);

        $connection = $this->connectionRepo->findOneBy([
            'userA' => $ids[0],
            'userB' => $ids[1],
        ]);

        if (!$connection) {
            return new JsonResponse(['error' => 'Not connected'], 400);
        }

        $this->em->remove($connection);
        $this->em->flush();

        return new JsonResponse(['success' => true, 'message' => 'Ami supprimé']);
    }
}
