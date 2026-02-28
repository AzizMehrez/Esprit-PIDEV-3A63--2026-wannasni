<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Entity\VerificationRequest;
use App\Repository\UserRepository;
use App\Repository\VerificationRequestRepository;
use App\Service\NotificationService;
use App\Service\VerificationAnalyzerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/users')]
class UserAdminController extends AbstractController
{
    public function __construct(
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager,
        private MailerInterface $mailer,
        private UserPasswordHasherInterface $passwordHasher,
        private VerificationRequestRepository $verificationRequestRepo,
        private VerificationAnalyzerService $verificationAnalyzer,
        private NotificationService $notificationService,
    ) {
    }

    #[Route('/', name: 'admin_users')]
    public function index(Request $request): Response
    {
        $filter = $request->query->get('filter', 'all');
        $search = $request->query->get('search', '');

        $queryBuilder = $this->userRepository->createQueryBuilder('u')
            ->orderBy('u.createdAt', 'DESC');

        // Apply filters
        if ($filter === 'active') {
            $queryBuilder->andWhere('u.status = :status')->setParameter('status', 'active');
        } elseif ($filter === 'inactive') {
            $queryBuilder->andWhere('u.status = :status')->setParameter('status', 'inactive');
        } elseif ($filter === 'admins') {
            $queryBuilder->andWhere('u.roles LIKE :role')->setParameter('role', '%ROLE_ADMIN%');
        } elseif ($filter === 'caregivers') {
            $queryBuilder->andWhere('u.roles LIKE :role')->setParameter('role', '%ROLE_CAREGIVER%');
        } elseif ($filter === 'verified') {
            $queryBuilder->andWhere('u.isAccountVerified = :verified')->setParameter('verified', true);
        } elseif ($filter === 'banned') {
            $queryBuilder->andWhere('u.isNetworkingBanned = :banned')->setParameter('banned', true);
        }

        // Apply search
        if (!empty($search)) {
            $queryBuilder->andWhere('u.email LIKE :search OR u.firstName LIKE :search OR u.lastName LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        $users = $queryBuilder->getQuery()->getResult();

        // Verification requests
        $pendingRequests = $this->verificationRequestRepo->findAllPending();
        $pendingRequestCount = count($pendingRequests);

        // Calculate statistics
        $stats = [
            'total' => $this->userRepository->count([]),
            'active' => $this->userRepository->count(['status' => 'active']),
            'inactive' => $this->userRepository->count(['status' => 'inactive']),
            'admins' => count($this->userRepository->findByRole('ROLE_ADMIN')),
            'verified' => $this->userRepository->count(['isAccountVerified' => true]),
            'pending_verifications' => $pendingRequestCount,
        ];

        return $this->render('admin/users/index.html.twig', [
            'users' => $users,
            'stats' => $stats,
            'current_filter' => $filter,
            'search_term' => $search,
            'pendingRequests' => $pendingRequests,
            'pendingRequestCount' => $pendingRequestCount,
        ]);
    }

    #[Route('/new', name: 'admin_users_new', methods: ['GET', 'POST'])]
    public function new(Request $request, UserPasswordHasherInterface $passwordHasher): Response
    {
        if ($request->isMethod('POST')) {
            try {
                $email = $request->request->get('email');
                $password = $request->request->get('password');
                
                // Validation
                if (empty($email) || empty($password)) {
                    $this->addFlash('error', 'Email et mot de passe sont requis.');
                    return $this->render('admin/users/new.html.twig');
                }
                
                if (strlen($password) < 6) {
                    $this->addFlash('error', 'Le mot de passe doit contenir au moins 6 caractères.');
                    return $this->render('admin/users/new.html.twig');
                }
                
                // Check if email exists
                $existingUser = $this->userRepository->findOneBy(['email' => $email]);
                if ($existingUser) {
                    $this->addFlash('error', 'Un utilisateur avec cet email existe déjà.');
                    return $this->render('admin/users/new.html.twig');
                }

                $user = new User();
                $user->setEmail($email);
                $user->setFirstName($request->request->get('firstName'));
                $user->setLastName($request->request->get('lastName'));
                $user->setPhone($request->request->get('phone'));
                $user->setStatus($request->request->get('status', 'active'));

                // Set roles
                $roles = ['ROLE_USER'];
                if ($request->request->get('role_admin')) {
                    $roles[] = 'ROLE_ADMIN';
                }
                if ($request->request->get('role_caregiver')) {
                    $roles[] = 'ROLE_CAREGIVER';
                }
                $user->setRoles($roles);

                // Hash password
                $hashedPassword = $passwordHasher->hashPassword($user, $password);
                $user->setPassword($hashedPassword);

                $this->entityManager->persist($user);
                $this->entityManager->flush();

                $this->addFlash('success', 'Utilisateur créé avec succès.');
                return $this->redirectToRoute('admin_users_show', ['id' => $user->getId()]);
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la création: ' . $e->getMessage());
                return $this->render('admin/users/new.html.twig');
            }
        }

        return $this->render('admin/users/new.html.twig');
    }

    #[Route('/{id}', name: 'admin_users_show', requirements: ['id' => '\d+'])]
    public function show(User $user): Response
    {
        return $this->render('admin/users/show.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_users_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, User $user): Response
    {
        if ($request->isMethod('POST')) {
            // Store original email before any changes
            $originalEmail = $user->getEmail();
            
            $user->setFirstName($request->request->get('firstName'));
            $user->setLastName($request->request->get('lastName'));
            $user->setPhone($request->request->get('phone'));
            $user->setStatus($request->request->get('status'));
            
            // Update user domain
            $userDomain = $request->request->get('userDomain');
            if (!empty($userDomain)) {
                $user->setUserDomain($userDomain);
            } else {
                $user->setUserDomain(null);
            }
            
            // Get current email info
            $currentEmail = $user->getEmail();
            $currentDomain = substr($currentEmail, strpos($currentEmail, '@'));
            $username = substr($currentEmail, 0, strpos($currentEmail, '@'));
            
            // Update roles
            $roles = ['ROLE_USER'];
            $isBecomingAdmin = false;
            $wasAdmin = in_array('ROLE_ADMIN', $user->getRoles());
            
            if ($request->request->get('role_admin')) {
                $roles[] = 'ROLE_ADMIN';
                $isBecomingAdmin = true;
            }
            if ($request->request->get('role_caregiver')) {
                $roles[] = 'ROLE_CAREGIVER';
            }
            $user->setRoles($roles);
            
            // Variables for email notification
            $newAdminEmail = null;
            $newPassword = null;
            
            // If user is becoming admin and doesn't have @wannasni.com, update email
            if ($isBecomingAdmin && !str_ends_with(strtolower($currentEmail), '@wannasni.com')) {
                $newEmail = $username . '@wannasni.com';
                
                // Check if this email already exists
                $existingUser = $this->userRepository->findOneBy(['email' => $newEmail]);
                if ($existingUser && $existingUser->getId() !== $user->getId()) {
                    $this->addFlash('warning', 'L\'email ' . $newEmail . ' existe déjà. Le domaine n\'a pas été modifié.');
                } else {
                    $newAdminEmail = $newEmail;
                    $user->setEmail($newEmail);
                    $this->addFlash('info', 'Email mis à jour vers ' . $newEmail . ' car l\'utilisateur est maintenant administrateur.');
                }
            }
            
            // If user wasn't admin before but is becoming admin now, generate new password and send email
            if (!$wasAdmin && $isBecomingAdmin) {
                // Generate a random password
                $newPassword = $this->generateRandomPassword(12);
                
                // Hash and set the new password
                $hashedPassword = $this->passwordHasher->hashPassword($user, $newPassword);
                $user->setPassword($hashedPassword);
                
                // Send welcome email to the original email address
                try {
                    $this->sendAdminWelcomeEmail(
                        $originalEmail,
                        $user->getFirstName() ?? 'Utilisateur',
                        $newAdminEmail ?? $user->getEmail(),
                        $newPassword
                    );
                    $this->addFlash('success', 'Un email de bienvenue avec les nouvelles informations d\'identification a été envoyé à ' . $originalEmail);
                } catch (\Exception $e) {
                    $this->addFlash('warning', 'L\'utilisateur a été promu administrateur mais l\'email n\'a pas pu être envoyé: ' . $e->getMessage());
                }
            }
            
            // If user is losing admin role and has @wannasni.com, you might want to revert (optional)
            // Uncomment if needed:
            // if ($wasAdmin && !$isBecomingAdmin && str_ends_with(strtolower($currentEmail), '@wannasni.com')) {
            //     // Keep the email or change back - your choice
            // }

            $this->entityManager->flush();

            $this->addFlash('success', 'Utilisateur mis à jour avec succès.');
            return $this->redirectToRoute('admin_users_show', ['id' => $user->getId()]);
        }

        return $this->render('admin/users/edit.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/{id}/toggle-status', name: 'admin_users_toggle_status', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function toggleStatus(User $user): Response
    {
        $user->setStatus($user->getStatus() === 'active' ? 'inactive' : 'active');
        $this->entityManager->flush();

        $this->addFlash('success', 'Statut de l\'utilisateur mis à jour.');
        return $this->redirectToRoute('admin_users');
    }

    #[Route('/{id}/delete', name: 'admin_users_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(User $user): Response
    {
        try {
            $userName = $user->getFirstName() . ' ' . $user->getLastName();
            
            $this->entityManager->remove($user);
            $this->entityManager->flush();

            $this->addFlash('success', 'L\'utilisateur ' . $userName . ' a été supprimé avec succès.');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la suppression : ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_users');
    }

    #[Route('/export/pdf', name: 'admin_users_export_pdf')]
    public function exportPdf(Request $request): Response
    {
        $filter = $request->query->get('filter', 'all');
        $search = $request->query->get('search', '');

        $queryBuilder = $this->userRepository->createQueryBuilder('u')
            ->orderBy('u.createdAt', 'DESC');

        // Apply same filters as index
        if ($filter === 'active') {
            $queryBuilder->andWhere('u.status = :status')->setParameter('status', 'active');
        } elseif ($filter === 'inactive') {
            $queryBuilder->andWhere('u.status = :status')->setParameter('status', 'inactive');
        } elseif ($filter === 'admins') {
            $queryBuilder->andWhere('u.roles LIKE :role')->setParameter('role', '%ROLE_ADMIN%');
        } elseif ($filter === 'caregivers') {
            $queryBuilder->andWhere('u.roles LIKE :role')->setParameter('role', '%ROLE_CAREGIVER%');
        }

        if (!empty($search)) {
            $queryBuilder->andWhere('u.email LIKE :search OR u.firstName LIKE :search OR u.lastName LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        $users = $queryBuilder->getQuery()->getResult();

        // Generate HTML for PDF
        $html = $this->renderView('admin/users/export_pdf.html.twig', [
            'users' => $users,
            'filter' => $filter,
            'search' => $search,
            'date' => new \DateTime(),
        ]);

        // Return HTML response that will be converted to PDF
        return new Response($html, 200, [
            'Content-Type' => 'text/html',
        ]);
    }

    #[Route('/export/excel', name: 'admin_users_export_excel')]
    public function exportExcel(Request $request): StreamedResponse
    {
        $filter = $request->query->get('filter', 'all');
        $search = $request->query->get('search', '');

        $queryBuilder = $this->userRepository->createQueryBuilder('u')
            ->orderBy('u.createdAt', 'DESC');

        // Apply same filters as index
        if ($filter === 'active') {
            $queryBuilder->andWhere('u.status = :status')->setParameter('status', 'active');
        } elseif ($filter === 'inactive') {
            $queryBuilder->andWhere('u.status = :status')->setParameter('status', 'inactive');
        } elseif ($filter === 'admins') {
            $queryBuilder->andWhere('u.roles LIKE :role')->setParameter('role', '%ROLE_ADMIN%');
        } elseif ($filter === 'caregivers') {
            $queryBuilder->andWhere('u.roles LIKE :role')->setParameter('role', '%ROLE_CAREGIVER%');
        }

        if (!empty($search)) {
            $queryBuilder->andWhere('u.email LIKE :search OR u.firstName LIKE :search OR u.lastName LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        $users = $queryBuilder->getQuery()->getResult();

        // Calculate statistics
        $totalUsers = count($users);
        $activeUsers = count(array_filter($users, fn($u) => $u->getStatus() === 'active'));
        $inactiveUsers = count(array_filter($users, fn($u) => $u->getStatus() === 'inactive'));
        $adminUsers = count(array_filter($users, fn($u) => in_array('ROLE_ADMIN', $u->getRoles())));
        $caregiverUsers = count(array_filter($users, fn($u) => in_array('ROLE_CAREGIVER', $u->getRoles())));

        // Create CSV response
        $response = new StreamedResponse(function() use ($users, $totalUsers, $activeUsers, $inactiveUsers, $adminUsers, $caregiverUsers, $filter, $search) {
            $handle = fopen('php://output', 'w');
            
            // Add BOM for Excel UTF-8 support
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));
            
            // Title and export info
            fputcsv($handle, ['WANNASNI - Liste des Utilisateurs'], ';');
            fputcsv($handle, ['Exporté le: ' . date('d/m/Y H:i:s')], ';');
            fputcsv($handle, [''], ';'); // Empty line
            
            // Statistics section
            fputcsv($handle, ['=== STATISTIQUES ==='], ';');
            fputcsv($handle, ['Total Utilisateurs', $totalUsers], ';');
            fputcsv($handle, ['Utilisateurs Actifs', $activeUsers], ';');
            fputcsv($handle, ['Utilisateurs Inactifs', $inactiveUsers], ';');
            fputcsv($handle, ['Administrateurs', $adminUsers], ';');
            fputcsv($handle, ['Aidants', $caregiverUsers], ';');
            fputcsv($handle, [''], ';'); // Empty line
            
            // Filter info if applicable
            if ($filter !== 'all' || !empty($search)) {
                fputcsv($handle, ['=== FILTRES APPLIQUÉS ==='], ';');
                if ($filter !== 'all') {
                    fputcsv($handle, ['Filtre', ucfirst($filter)], ';');
                }
                if (!empty($search)) {
                    fputcsv($handle, ['Recherche', $search], ';');
                }
                fputcsv($handle, [''], ';'); // Empty line
            }
            
            // Data table headers
            fputcsv($handle, ['=== DÉTAILS DES UTILISATEURS ==='], ';');
            fputcsv($handle, ['ID', 'Prénom', 'Nom', 'Email', 'Téléphone', 'Rôle', 'Domaine', 'Statut', 'Date d\'inscription'], ';');
            
            // CSV data
            foreach ($users as $user) {
                $role = 'Utilisateur';
                if (in_array('ROLE_ADMIN', $user->getRoles())) {
                    $role = 'Admin';
                } elseif (in_array('ROLE_CAREGIVER', $user->getRoles())) {
                    $role = 'Aidant';
                }
                
                fputcsv($handle, [
                    $user->getId(),
                    $user->getFirstName(),
                    $user->getLastName(),
                    $user->getEmail(),
                    $user->getPhone() ?? 'N/A',
                    $role,
                    $user->getUserDomain() ?? 'N/A',
                    $user->getStatus() === 'active' ? 'Actif' : 'Inactif',
                    $user->getCreatedAt()->format('d/m/Y H:i'),
                ], ';');
            }
            
            // Footer
            fputcsv($handle, [''], ';'); // Empty line
            fputcsv($handle, ['Document confidentiel - WANNASNI'], ';');
            
            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="WANNASNI_Utilisateurs_' . date('Y-m-d_His') . '.csv"');

        return $response;
    }

    // ─── Verification Request Review ───────────────────────────────
    #[Route('/verification/{id}', name: 'admin_users_verification_review', requirements: ['id' => '\d+'])]
    public function verificationReview(int $id): Response
    {
        $vr = $this->verificationRequestRepo->find($id);
        if (!$vr) {
            $this->addFlash('error', 'Demande de vérification introuvable.');
            return $this->redirectToRoute('admin_users');
        }

        return $this->render('admin/users/verification_review.html.twig', [
            'request' => $vr,
            'user' => $vr->getUser(),
        ]);
    }

    // ─── Run AI Analysis on Verification Request ────────────────────
    #[Route('/verification/{id}/analyze', name: 'admin_users_verification_analyze', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function runAiAnalysis(int $id): JsonResponse
    {
        $vr = $this->verificationRequestRepo->find($id);
        if (!$vr) {
            return new JsonResponse(['success' => false, 'error' => 'Request not found'], 404);
        }

        try {
            $result = $this->verificationAnalyzer->analyze($vr);
        } catch (\Throwable $e) {
            return new JsonResponse(['success' => false, 'error' => 'Erreur lors de l\'analyse: ' . $e->getMessage()]);
        }

        // If AI auto-rejected, send rejection email
        if ($vr->getStatus() === VerificationRequest::STATUS_AI_REJECTED) {
            try {
                $this->sendVerificationEmail($vr->getUser(), false, $result['decision_reason'] ?? 'Votre compte ne remplit pas les critères.');
            } catch (\Throwable $e) {
                // Email failure shouldn't block the response
            }
        }

        return new JsonResponse([
            'success' => true,
            'score' => $result['score'] ?? null,
            'decision' => $result['decision'] ?? 'review',
            'decision_reason' => $result['decision_reason'] ?? '',
            'report' => $result['factors'] ?? [],
            'status' => $vr->getStatus(),
        ]);
    }

    // ─── Approve Verification Request ───────────────────────────────
    #[Route('/verification/{id}/approve', name: 'admin_users_verification_approve', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function approveVerification(int $id): Response
    {
        $vr = $this->verificationRequestRepo->find($id);
        if (!$vr || !$vr->isPending()) {
            $this->addFlash('error', 'Demande non valide ou déjà traitée.');
            return $this->redirectToRoute('admin_users');
        }

        /** @var User $admin */
        $admin = $this->getUser();
        $user = $vr->getUser();

        $vr->setStatus(VerificationRequest::STATUS_APPROVED);
        $vr->setReviewedBy($admin);
        $vr->setReviewedAt(new \DateTime());

        $user->setIsAccountVerified(true);
        $user->setVerifiedAt(new \DateTime());
        $user->setVerificationBadgeType($user->getEffectiveBadge());

        $this->entityManager->flush();

        // Send approval email
        $this->sendVerificationEmail($user, true);

        $this->notificationService->create(
            'verification_approved',
            sprintf('La vérification de %s a été approuvée.', $user->getFullName()),
            $user->getId()
        );

        $this->addFlash('success', 'Utilisateur ' . $user->getFullName() . ' vérifié avec succès ! Email envoyé.');
        return $this->redirectToRoute('admin_users');
    }

    // ─── Reject Verification Request ────────────────────────────────
    #[Route('/verification/{id}/reject', name: 'admin_users_verification_reject', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function rejectVerification(Request $request, int $id): Response
    {
        $vr = $this->verificationRequestRepo->find($id);
        if (!$vr || !$vr->isPending()) {
            $this->addFlash('error', 'Demande non valide ou déjà traitée.');
            return $this->redirectToRoute('admin_users');
        }

        /** @var User $admin */
        $admin = $this->getUser();

        $vr->setStatus(VerificationRequest::STATUS_REJECTED);
        $vr->setReviewedBy($admin);
        $vr->setReviewedAt(new \DateTime());
        $vr->setReviewNote($request->request->get('note', 'Demande refusée par l\'administrateur.'));

        $this->entityManager->flush();

        // Send rejection email
        $this->sendVerificationEmail($vr->getUser(), false, $vr->getReviewNote());

        $this->addFlash('success', 'Demande de vérification refusée. Email envoyé.');
        return $this->redirectToRoute('admin_users');
    }

    // ─── Toggle Networking Ban ──────────────────────────────────────
    #[Route('/{id}/toggle-networking-ban', name: 'admin_users_toggle_networking_ban', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function toggleNetworkingBan(User $user): Response
    {
        $user->setIsNetworkingBanned(!$user->isNetworkingBanned());
        $this->entityManager->flush();

        $status = $user->isNetworkingBanned() ? 'banni du networking' : 'débanni du networking';
        $this->addFlash('success', $user->getFullName() . ' a été ' . $status . '.');
        return $this->redirectToRoute('admin_users');
    }

    /**
     * Send verification result email (approval or rejection).
     */
    private function sendVerificationEmail(User $user, bool $approved, ?string $reason = null): void
    {
        try {
            $template = $approved ? 'emails/verification_approved.html.twig' : 'emails/verification_rejected.html.twig';
            $subject = $approved
                ? '🎉 Félicitations ! Votre compte WANNASNI est vérifié'
                : '❌ Votre demande de vérification WANNASNI';

            $email = (new Email())
                ->from('noreply@wannasni.com')
                ->to($user->getEmail())
                ->subject($subject)
                ->html($this->renderView($template, [
                    'user' => $user,
                    'reason' => $reason,
                ]));

            $this->mailer->send($email);
        } catch (\Exception $e) {
            $this->addFlash('warning', 'L\'email n\'a pas pu être envoyé: ' . $e->getMessage());
        }
    }

    /**
     * Generate a random secure password
     */
    private function generateRandomPassword(int $length = 12): string
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!@#$%^&*()';
        $charactersLength = strlen($characters);
        $randomPassword = '';
        
        for ($i = 0; $i < $length; $i++) {
            $randomPassword .= $characters[random_int(0, $charactersLength - 1)];
        }
        
        return $randomPassword;
    }

    /**
     * Send welcome email to newly promoted admin
     */
    private function sendAdminWelcomeEmail(string $recipientEmail, string $firstName, string $newAdminEmail, string $password): void
    {
        $email = (new Email())
            ->from('noreply@wannasni.com')
            ->to($recipientEmail)
            ->subject('Bienvenue en tant qu\'Administrateur WANNASNI')
            ->html($this->renderView('emails/admin_welcome.html.twig', [
                'firstName' => $firstName,
                'adminEmail' => $newAdminEmail,
                'password' => $password,
            ]));

        $this->mailer->send($email);
    }
}
