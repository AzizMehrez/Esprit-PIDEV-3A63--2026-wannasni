<?php

namespace App\Controller\Front;

use App\Entity\Conversation;
use App\Entity\Message;
use App\Entity\User;
use App\Repository\ConversationRepository;
use App\Repository\MessageRepository;
use App\Repository\PostRepository;
use App\Repository\UserConnectionRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/{_locale}/networking/messages', requirements: ['_locale' => 'fr|en|ar'])]
class MessagingController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private ConversationRepository $conversationRepo,
        private MessageRepository $messageRepo,
        private UserConnectionRepository $connectionRepo,
        private UserRepository $userRepo,
        private PostRepository $postRepo,
    ) {}

    // ─── List Conversations ──────────────────────────────────────────
    #[Route('', name: 'app_networking_conversations', methods: ['GET'])]
    public function listConversations(): JsonResponse
    {
        /** @var User $me */
        $me = $this->getUser();
        $conversations = $this->conversationRepo->findForUser($me);

        $data = [];
        foreach ($conversations as $conv) {
            $other = $conv->getOtherUser($me);
            // Get last message
            $lastMsg = $this->messageRepo->findOneBy(
                ['conversation' => $conv],
                ['createdAt' => 'DESC']
            );

            $data[] = [
                'id' => $conv->getId(),
                'otherUser' => [
                    'id' => $other->getId(),
                    'name' => $other->getFullName(),
                    'avatar' => $other->getImageProfil(),
                ],
                'lastMessage' => $lastMsg ? [
                    'content' => $lastMsg->getContent(),
                    'type' => $lastMsg->getMessageType(),
                    'createdAt' => $lastMsg->getCreatedAt()->format('Y-m-d H:i'),
                    'isRead' => $lastMsg->isRead(),
                    'isMine' => $lastMsg->getSender()->getId() === $me->getId(),
                ] : null,
                'lastMessageAt' => $conv->getLastMessageAt()?->format('Y-m-d H:i'),
            ];
        }

        return new JsonResponse(['conversations' => $data]);
    }

    // ─── Get Messages in a Conversation ──────────────────────────────
    #[Route('/{id}', name: 'app_networking_messages_get', methods: ['GET'])]
    public function getMessages(int $id): JsonResponse
    {
        /** @var User $me */
        $me = $this->getUser();
        $conv = $this->conversationRepo->find($id);

        if (!$conv || !$conv->involvesUser($me)) {
            return new JsonResponse(['error' => 'Conversation not found'], 404);
        }

        // Mark messages from other user as read
        $this->messageRepo->markAsRead($conv, $me);

        $messages = $this->messageRepo->findByConversation($conv);
        $other = $conv->getOtherUser($me);

        $data = [];
        foreach ($messages as $msg) {
            $item = [
                'id' => $msg->getId(),
                'content' => $msg->getContent(),
                'type' => $msg->getMessageType(),
                'isMine' => $msg->getSender()->getId() === $me->getId(),
                'senderName' => $msg->getSender()->getFullName(),
                'createdAt' => $msg->getCreatedAt()->format('Y-m-d H:i'),
                'attachment' => $msg->getAttachmentPath(),
            ];

            // If it's a post share, include post data
            if ($msg->getMessageType() === Message::TYPE_POST_SHARE && $msg->getSharedPostId()) {
                $post = $this->postRepo->find($msg->getSharedPostId());
                if ($post) {
                    $item['sharedPost'] = [
                        'id' => $post->getId(),
                        'content' => $post->getContent(),
                        'authorName' => $post->getAuthor()->getFullName(),
                    ];
                }
            }

            $data[] = $item;
        }

        return new JsonResponse([
            'messages' => $data,
            'otherUser' => [
                'id' => $other->getId(),
                'name' => $other->getFullName(),
                'avatar' => $other->getImageProfil(),
            ],
        ]);
    }

    // ─── Send Message ────────────────────────────────────────────────
    #[Route('/send/{userId}', name: 'app_networking_message_send', methods: ['POST'])]
    public function sendMessage(Request $request, int $userId): JsonResponse
    {
        /** @var User $me */
        $me = $this->getUser();
        $other = $this->userRepo->find($userId);

        if (!$other || $other->getId() === $me->getId()) {
            return new JsonResponse(['error' => 'Invalid user'], 400);
        }

        // Check if users are connected
        if (!$this->connectionRepo->areConnected($me, $other)) {
            return new JsonResponse(['error' => 'You must be connected to send messages'], 403);
        }

        // Find or create conversation
        $conv = $this->conversationRepo->findBetween($me, $other);
        if (!$conv) {
            $ids = [$me->getId(), $other->getId()];
            sort($ids);
            $conv = new Conversation();
            $conv->setUserA($this->userRepo->find($ids[0]));
            $conv->setUserB($this->userRepo->find($ids[1]));
            $this->em->persist($conv);
        }

        // Determine message type and content
        $messageType = Message::TYPE_TEXT;
        $content = null;
        $attachmentPath = null;
        $sharedPostId = null;

        // Check for file upload (voice message, image, video)
        $file = $request->files->get('attachment');
        if ($file && $file->isValid()) {
            $mime = $file->getMimeType();
            $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/messages';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $newName = uniqid('msg_') . '.' . $file->guessExtension();
            $file->move($uploadDir, $newName);
            $attachmentPath = '/uploads/messages/' . $newName;

            if (str_starts_with($mime, 'audio/')) {
                $messageType = Message::TYPE_VOICE;
            } elseif (str_starts_with($mime, 'image/')) {
                $messageType = Message::TYPE_IMAGE;
            } elseif (str_starts_with($mime, 'video/')) {
                $messageType = Message::TYPE_VIDEO;
            }

            $content = $request->request->get('content');
        } else {
            // Text or post share
            $data = json_decode($request->getContent(), true);
            if ($data) {
                $content = $data['content'] ?? null;
                if (isset($data['sharedPostId'])) {
                    $messageType = Message::TYPE_POST_SHARE;
                    $sharedPostId = (int) $data['sharedPostId'];
                    $content = $data['content'] ?? 'Shared a post';
                }
            } else {
                $content = $request->request->get('content');
            }
        }

        if (!$content && !$attachmentPath) {
            return new JsonResponse(['error' => 'Message cannot be empty'], 400);
        }

        $msg = new Message();
        $msg->setConversation($conv);
        $msg->setSender($me);
        $msg->setContent($content);
        $msg->setMessageType($messageType);
        $msg->setAttachmentPath($attachmentPath);
        $msg->setSharedPostId($sharedPostId);

        $conv->setLastMessageAt(new \DateTime());

        $this->em->persist($msg);
        $this->em->flush();

        return new JsonResponse([
            'success' => true,
            'message' => [
                'id' => $msg->getId(),
                'content' => $msg->getContent(),
                'type' => $msg->getMessageType(),
                'attachment' => $msg->getAttachmentPath(),
                'createdAt' => $msg->getCreatedAt()->format('Y-m-d H:i'),
                'isMine' => true,
            ],
            'conversationId' => $conv->getId(),
        ]);
    }

    // ─── Share Post to Friend ────────────────────────────────────────
    #[Route('/share-post', name: 'app_networking_share_post', methods: ['POST'])]
    public function sharePost(Request $request): JsonResponse
    {
        /** @var User $me */
        $me = $this->getUser();
        $data = json_decode($request->getContent(), true);

        $postId = $data['postId'] ?? null;
        $friendId = $data['friendId'] ?? null;

        if (!$postId || !$friendId) {
            return new JsonResponse(['error' => 'Missing postId or friendId'], 400);
        }

        $friend = $this->userRepo->find($friendId);
        $post = $this->postRepo->find($postId);

        if (!$friend || !$post) {
            return new JsonResponse(['error' => 'Post or friend not found'], 404);
        }

        if (!$this->connectionRepo->areConnected($me, $friend)) {
            return new JsonResponse(['error' => 'Not connected'], 403);
        }

        // Find or create conversation
        $conv = $this->conversationRepo->findBetween($me, $friend);
        if (!$conv) {
            $ids = [$me->getId(), $friend->getId()];
            sort($ids);
            $conv = new Conversation();
            $conv->setUserA($this->userRepo->find($ids[0]));
            $conv->setUserB($this->userRepo->find($ids[1]));
            $this->em->persist($conv);
        }

        $msg = new Message();
        $msg->setConversation($conv);
        $msg->setSender($me);
        $msg->setContent('Shared a post: ' . ($post->getContent() ? substr($post->getContent(), 0, 100) : 'Media post'));
        $msg->setMessageType(Message::TYPE_POST_SHARE);
        $msg->setSharedPostId($post->getId());

        $conv->setLastMessageAt(new \DateTime());

        $this->em->persist($msg);
        $this->em->flush();

        return new JsonResponse(['success' => true, 'message' => 'Post partagé']);
    }
}
