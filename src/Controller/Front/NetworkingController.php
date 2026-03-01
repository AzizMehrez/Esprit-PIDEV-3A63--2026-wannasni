<?php

namespace App\Controller\Front;

use App\Entity\Post;
use App\Entity\PostComment;
use App\Entity\PostLike;
use App\Entity\PostMedia;
use App\Entity\User;
use App\Entity\VerificationRequest;
use App\Repository\ConnectionInviteRepository;
use App\Repository\ConversationRepository;
use App\Repository\PostCommentRepository;
use App\Repository\PostLikeRepository;
use App\Repository\PostRepository;
use App\Repository\UserConnectionRepository;
use App\Repository\UserRepository;
use App\Repository\VerificationRequestRepository;
use App\Service\ImageModerationService;
use App\Service\NotificationService;
use App\Service\TextModerationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/{_locale}/networking', requirements: ['_locale' => 'fr|en|ar'])]
class NetworkingController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private PostRepository $postRepo,
        private PostLikeRepository $likeRepo,
        private PostCommentRepository $commentRepo,
        private UserConnectionRepository $connectionRepo,
        private ConnectionInviteRepository $inviteRepo,
        private ConversationRepository $conversationRepo,
        private UserRepository $userRepo,
        private ImageModerationService $moderationService,
        private TextModerationService $textModerationService,
        private VerificationRequestRepository $verificationRequestRepo,
        private NotificationService $notificationService,
    ) {}

    // ─── Main Feed Page ───────────────────────────────────────────────
    #[Route('', name: 'app_networking', methods: ['GET'])]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $friendIds = $this->connectionRepo->findFriendIds($user);
        $posts = $this->postRepo->findFeedPosts($user, $friendIds);
        $pendingCount = $this->inviteRepo->countPendingForUser($user);
        $unreadMessages = $this->conversationRepo->countUnreadForUser($user);

        // Determine which posts the current user has liked - single batch query
        $postIds = array_map(fn(Post $p) => $p->getId(), $posts);
        $likedPostIds = $this->likeRepo->findLikedPostIds($user, $postIds);

        // Discover users to connect with (not already friends, not self)
        $excludeIds = array_merge($friendIds, [$user->getId()]);
        $suggestedUsers = $this->userRepo->createQueryBuilder('u')
            ->where('u.id NOT IN (:excludeIds)')
            ->andWhere('u.status = :active')
            ->setParameter('excludeIds', $excludeIds)
            ->setParameter('active', 'active')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        return $this->render('front/networking/index.html.twig', [
            'user' => $user,
            'posts' => $posts,
            'likedPostIds' => $likedPostIds,
            'pendingInvitesCount' => $pendingCount,
            'unreadMessagesCount' => $unreadMessages,
            'suggestedUsers' => $suggestedUsers,
            'friendIds' => $friendIds,
            'hasActiveVerificationRequest' => $this->verificationRequestRepo->hasActiveRequest($user),
        ]);
    }

    // ─── Submit Verification Request (AJAX) ──────────────────────────
    #[Route('/verification/request', name: 'app_networking_verification_request', methods: ['POST'])]
    public function submitVerificationRequest(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        // Already verified
        if ($user->isAccountVerified()) {
            return new JsonResponse(['error' => 'Vous êtes déjà vérifié.'], 400);
        }

        // Already has active request
        if ($this->verificationRequestRepo->hasActiveRequest($user)) {
            return new JsonResponse(['error' => 'Vous avez déjà une demande en cours.'], 400);
        }

        // Eligibility: profile complete
        if (!$user->isProfileComplete()) {
            return new JsonResponse(['error' => 'Votre profil doit être complet (nom, prénom, photo).'], 400);
        }

        // Eligibility: at least one post
        $postCount = $this->postRepo->count(['author' => $user]);
        if ($postCount === 0) {
            return new JsonResponse(['error' => 'Vous devez avoir au moins un post pour demander la vérification.'], 400);
        }

        $data = json_decode($request->getContent(), true);
        $reason = trim($data['reason'] ?? '');

        if (empty($reason) || strlen($reason) < 10) {
            return new JsonResponse(['error' => 'Veuillez expliquer pourquoi vous souhaitez être vérifié (10 caractères min).'], 400);
        }

        $vr = new VerificationRequest();
        $vr->setUser($user);
        $vr->setReason($reason);

        $this->em->persist($vr);
        $this->em->flush();

        // Notify admins
        $this->notificationService->create(
            'verification_request',
            sprintf('%s a demandé la vérification de son compte.', $user->getFullName()),
            $vr->getId()
        );

        return new JsonResponse([
            'success' => true,
            'message' => 'Votre demande de vérification a été soumise ! Nous l\'examinerons bientôt.',
        ]);
    }

    // ─── Reels Page ───────────────────────────────────────────────────
    #[Route('/reels', name: 'app_networking_reels', methods: ['GET'])]
    public function reels(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $friendIds = $this->connectionRepo->findFriendIds($user);
        $reels = $this->postRepo->findReels($user, $friendIds);

        return $this->render('front/networking/reels.html.twig', [
            'user' => $user,
            'reels' => $reels,
        ]);
    }

    // ─── User Profile View ───────────────────────────────────────────
    #[Route('/user/{id}', name: 'app_networking_user_profile', methods: ['GET'])]
    public function userProfile(int $id): Response
    {
        /** @var User $me */
        $me = $this->getUser();
        $target = $this->userRepo->find($id);

        if (!$target) {
            throw $this->createNotFoundException('User not found');
        }

        $isConnected = $this->connectionRepo->areConnected($me, $target);
        $isMe = $me->getId() === $target->getId();

        // Only show posts if: own profile, connected, or public profile
        $posts = [];
        if ($isMe || $isConnected || $target->isProfilePublic()) {
            $posts = $this->postRepo->findByAuthor($target);
        }

        $likedPostIds = [];
        foreach ($posts as $post) {
            if ($this->likeRepo->findByUserAndPost($me, $post)) {
                $likedPostIds[] = $post->getId();
            }
        }

        // Check pending invite status
        $pendingInvite = $this->inviteRepo->findExistingInvite($me, $target);

        return $this->render('front/networking/user_profile.html.twig', [
            'user' => $me,
            'target' => $target,
            'posts' => $posts,
            'likedPostIds' => $likedPostIds,
            'isConnected' => $isConnected,
            'isMe' => $isMe,
            'pendingInvite' => $pendingInvite,
        ]);
    }

    // ─── Create Post (AJAX) ──────────────────────────────────────────
    #[Route('/post/create', name: 'app_networking_post_create', methods: ['POST'])]
    public function createPost(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($user->isNetworkingBanned()) {
            return new JsonResponse(['error' => '⛔ Votre accès networking est en lecture seule. Vous ne pouvez pas publier.'], 403);
        }

        $content = $request->request->get('content', '');
        $type = $request->request->get('type', Post::TYPE_POST);

        if (!$content && !$request->files->get('media')) {
            return new JsonResponse(['error' => 'Post must have content or media'], 400);
        }

        // ── Text moderation: reject toxic content BEFORE saving ──
        if ($content) {
            $textModResult = $this->textModerationService->checkText($content);
            if (!($textModResult['safe'] ?? true)) {
                // Increment user's strike counter
                $user->incrementNetworkingStrikes();
                $this->em->flush();

                $locale = $request->getLocale();
                $message = $this->getTextModerationMessage($locale, $user->getNetworkingStrikes());

                return new JsonResponse([
                    'error'      => $message,
                    'categories' => $textModResult['categories'] ?? [],
                    'strikes'    => $user->getNetworkingStrikes(),
                ], 422);
            }
        }

        $post = new Post();
        $post->setAuthor($user);
        $post->setContent($content);
        $post->setType($type);

        // Handle media uploads
        $files = $request->files->get('media');
        if ($files) {
            if (!is_array($files)) {
                $files = [$files];
            }

            $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/networking';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $order = 0;
            foreach ($files as $file) {
                if (!$file || !$file->isValid()) continue;

                $mime = $file->getMimeType();
                if (str_starts_with($mime, 'image/')) {
                    $mediaType = PostMedia::TYPE_IMAGE;
                } elseif (str_starts_with($mime, 'video/')) {
                    $mediaType = PostMedia::TYPE_VIDEO;
                } elseif (str_starts_with($mime, 'audio/')) {
                    $mediaType = PostMedia::TYPE_VOICE;
                } else {
                    continue; // skip unsupported types
                }

                // ── Image moderation: reject sensitive photos BEFORE saving ──
                if ($mediaType === PostMedia::TYPE_IMAGE) {
                    $modResult = $this->moderationService->checkImage($file->getRealPath());
                    if (!($modResult['safe'] ?? true)) {
                        // Only give a strike when the AI is high-confidence (>= 0.85).
                        // Low-confidence rejections are likely false positives: the image
                        // is still blocked for safety, but the user is not penalised.
                        // Never strike for service-unavailable errors.
                        $confidence    = (float)($modResult['confidence'] ?? 0.0);
                        $isUnavailable = in_array('moderation_unavailable', $modResult['categories'] ?? [], true);
                        if (!$isUnavailable && $confidence >= 0.85) {
                            $user->incrementNetworkingStrikes();
                            $this->em->flush();
                        }

                        $locale  = $request->getLocale();
                        $message = $this->getImageModerationMessage($locale, $modResult);

                        return new JsonResponse([
                            'error'      => $message,
                            'categories' => $modResult['categories'] ?? [],
                            'strikes'    => $user->getNetworkingStrikes(),
                        ], 422);
                    }
                }

                $newName = uniqid('net_') . '.' . $file->guessExtension();
                $originalName = $file->getClientOriginalName();
                $fileSize = $file->getSize(); // capture BEFORE move() deletes the tmp file
                $file->move($uploadDir, $newName);

                $media = new PostMedia();
                $media->setFilePath('/uploads/networking/' . $newName);
                $media->setMediaType($mediaType);
                $media->setOriginalName($originalName);
                $media->setFileSize($fileSize);
                $media->setSortOrder($order++);
                $post->addMedia($media);
            }
        }

        $this->em->persist($post);
        $this->em->flush();

        return new JsonResponse([
            'success' => true,
            'postId' => $post->getId(),
            'message' => 'Post created successfully',
        ]);
    }

    // ─── Delete Post (AJAX) ──────────────────────────────────────────
    #[Route('/post/{id}/delete', name: 'app_networking_post_delete', methods: ['POST'])]
    public function deletePost(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $post = $this->postRepo->find($id);

        if (!$post || $post->getAuthor()->getId() !== $user->getId()) {
            return new JsonResponse(['error' => 'Not authorized'], 403);
        }

        $this->em->remove($post);
        $this->em->flush();

        return new JsonResponse(['success' => true]);
    }

    // ─── Like / Unlike (AJAX) ────────────────────────────────────────
    #[Route('/post/{id}/like', name: 'app_networking_post_like', methods: ['POST'])]
    public function toggleLike(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($user->isNetworkingBanned()) {
            return new JsonResponse(['error' => '⛔ Votre accès networking est en lecture seule.'], 403);
        }

        $post = $this->postRepo->find($id);

        if (!$post) {
            return new JsonResponse(['error' => 'Post not found'], 404);
        }

        $existing = $this->likeRepo->findByUserAndPost($user, $post);

        if ($existing) {
            $this->em->remove($existing);
            $this->em->flush();
            $liked = false;
        } else {
            $like = new PostLike();
            $like->setUser($user);
            $like->setPost($post);
            $this->em->persist($like);
            $this->em->flush();
            $liked = true;
        }

        return new JsonResponse([
            'success' => true,
            'liked' => $liked,
            'count' => $this->likeRepo->countByPost($post),
        ]);
    }

    // ─── Add Comment (AJAX) ──────────────────────────────────────────
    #[Route('/post/{id}/comment', name: 'app_networking_post_comment', methods: ['POST'])]
    public function addComment(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($user->isNetworkingBanned()) {
            return new JsonResponse(['error' => '⛔ Votre accès networking est en lecture seule.'], 403);
        }

        $post = $this->postRepo->find($id);

        if (!$post) {
            return new JsonResponse(['error' => 'Post not found'], 404);
        }

        $data = json_decode($request->getContent(), true);
        $content = $data['content'] ?? '';

        if (!$content) {
            return new JsonResponse(['error' => 'Comment cannot be empty'], 400);
        }

        // ── Text moderation: reject toxic comments BEFORE saving ──
        $textModResult = $this->textModerationService->checkText($content);
        if (!($textModResult['safe'] ?? true)) {
            // Increment user's strike counter
            $user->incrementNetworkingStrikes();
            $this->em->flush();

            $locale = $request->getLocale();
            $message = $this->getTextModerationMessage($locale, $user->getNetworkingStrikes());

            return new JsonResponse([
                'error'      => $message,
                'categories' => $textModResult['categories'] ?? [],
                'strikes'    => $user->getNetworkingStrikes(),
            ], 422);
        }

        $comment = new PostComment();
        $comment->setPost($post);
        $comment->setUser($user);
        $comment->setContent($content);

        $this->em->persist($comment);
        $this->em->flush();

        return new JsonResponse([
            'success' => true,
            'comment' => [
                'id' => $comment->getId(),
                'content' => $comment->getContent(),
                'userName' => $user->getFullName(),
                'userAvatar' => $user->getImageProfil(),
                'createdAt' => $comment->getCreatedAt()->format('Y-m-d H:i'),
            ],
        ]);
    }

    // ─── Get Comments (AJAX) ─────────────────────────────────────────
    #[Route('/post/{id}/comments', name: 'app_networking_post_comments', methods: ['GET'])]
    public function getComments(int $id): JsonResponse
    {
        $post = $this->postRepo->find($id);

        if (!$post) {
            return new JsonResponse(['error' => 'Post not found'], 404);
        }

        $comments = $this->commentRepo->findByPost($post);

        $data = [];
        foreach ($comments as $c) {
            $data[] = [
                'id' => $c->getId(),
                'content' => $c->getContent(),
                'userName' => $c->getUser()->getFullName(),
                'userAvatar' => $c->getUser()->getImageProfil(),
                'userId' => $c->getUser()->getId(),
                'createdAt' => $c->getCreatedAt()->format('Y-m-d H:i'),
            ];
        }

        return new JsonResponse(['comments' => $data]);
    }

    // ─── Search Users (AJAX) ─────────────────────────────────────────
    #[Route('/search', name: 'app_networking_search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        /** @var User $me */
        $me = $this->getUser();
        $q = $request->query->get('q', '');

        if (strlen($q) < 2) {
            return new JsonResponse(['users' => []]);
        }

        $users = $this->userRepo->createQueryBuilder('u')
            ->where('u.id != :me')
            ->andWhere('u.status = :active')
            ->andWhere('u.firstName LIKE :q OR u.lastName LIKE :q OR u.email LIKE :q')
            ->setParameter('me', $me->getId())
            ->setParameter('active', 'active')
            ->setParameter('q', '%' . $q . '%')
            ->setMaxResults(20)
            ->getQuery()
            ->getResult();

        $friendIds = $this->connectionRepo->findFriendIds($me);

        $data = [];
        foreach ($users as $u) {
            $data[] = [
                'id' => $u->getId(),
                'name' => $u->getFullName(),
                'email' => $u->getEmail(),
                'avatar' => $u->getImageProfil(),
                'isConnected' => in_array($u->getId(), $friendIds),
                'isPublic' => $u->isProfilePublic(),
            ];
        }

        return new JsonResponse(['users' => $data]);
    }

    // ─── Toggle Privacy (AJAX) ───────────────────────────────────────
    #[Route('/privacy/toggle', name: 'app_networking_privacy_toggle', methods: ['POST'])]
    public function togglePrivacy(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $user->setProfilePublic(!$user->isProfilePublic());
        $this->em->flush();

        return new JsonResponse([
            'success' => true,
            'isPublic' => $user->isProfilePublic(),
        ]);
    }

    // ─── Text Moderation: Localized rejection message ────────────────
    private function getTextModerationMessage(string $locale, int $strikes): string
    {
        $messages = [
            'fr' => "🚫 Votre message contient des mots inappropriés et a été rejeté.\n⚠️ Cela affecte votre réputation sur le réseau. (Avertissements : {strikes})",
            'en' => "🚫 Your message contains inappropriate language and has been rejected.\n⚠️ This affects your networking reputation. (Warnings: {strikes})",
            'ar' => "🚫 رسالتك تحتوي على كلمات غير لائقة وقد تم رفضها.\n⚠️ هذا يؤثر على سمعتك في الشبكة. (تحذيرات: {strikes})",
        ];

        $template = $messages[$locale] ?? $messages['fr'];
        return str_replace('{strikes}', (string) $strikes, $template);
    }

    // ─── Image Moderation: Localized rejection message ───────────────
    private function getImageModerationMessage(string $locale, array $modResult): string
    {
        $categories = $modResult['categories'] ?? [];
        $isUnavailable = in_array('moderation_unavailable', $categories, true);

        if ($isUnavailable) {
            $messages = [
                'fr' => "⚠️ Impossible de vérifier cette image pour le moment. Par mesure de sécurité, elle ne sera pas publiée. Veuillez réessayer plus tard.",
                'en' => "⚠️ Unable to verify this image at the moment. For safety reasons, it will not be published. Please try again later.",
                'ar' => "⚠️ تعذر التحقق من هذه الصورة حاليًا. لأسباب تتعلق بالسلامة، لن يتم نشرها. يرجى المحاولة لاحقًا.",
            ];
            return $messages[$locale] ?? $messages['fr'];
        }

        // Map category codes to localized labels
        $categoryLabels = [
            'fr' => [
                'violence'       => 'violence / contenu graphique',
                'weapons'        => 'armes',
                'nsfw'           => 'contenu explicite',
                'political'      => 'extrémisme / symboles haineux',
                'drugs'          => 'drogues',
                'discrimination' => 'discrimination / abus',
            ],
            'en' => [
                'violence'       => 'violence / graphic content',
                'weapons'        => 'weapons',
                'nsfw'           => 'explicit content',
                'political'      => 'extremism / hate symbols',
                'drugs'          => 'drugs',
                'discrimination' => 'discrimination / abuse',
            ],
            'ar' => [
                'violence'       => 'عنف / محتوى صادم',
                'weapons'        => 'أسلحة',
                'nsfw'           => 'محتوى فاضح',
                'political'      => 'تطرف / رموز كراهية',
                'drugs'          => 'مخدرات',
                'discrimination' => 'تمييز / إساءة',
            ],
        ];

        $labels = $categoryLabels[$locale] ?? $categoryLabels['fr'];
        $detectedLabels = [];
        foreach ($categories as $cat) {
            if (isset($labels[$cat])) {
                $detectedLabels[] = $labels[$cat];
            }
        }
        $categoryText = $detectedLabels ? implode(', ', $detectedLabels) : '';

        $messages = [
            'fr' => "🚫 Cette photo contient du contenu sensible{detail} et ne sera pas publiée.\nVeuillez partager uniquement du contenu approprié et respectueux.",
            'en' => "🚫 This photo contains sensitive content{detail} and will not be shared.\nPlease share only appropriate, respectful content.",
            'ar' => "🚫 تحتوي هذه الصورة على محتوى حساس{detail} ولن يتم نشرها.\nيرجى مشاركة محتوى مناسب ومحترم فقط.",
        ];

        $template = $messages[$locale] ?? $messages['fr'];
        $detail = $categoryText ? " ($categoryText)" : '';
        return str_replace('{detail}', $detail, $template);
    }
}
