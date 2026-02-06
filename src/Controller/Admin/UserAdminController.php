<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/users')]
class UserAdminController extends AbstractController
{
    public function __construct(
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager
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
        }

        // Apply search
        if (!empty($search)) {
            $queryBuilder->andWhere('u.email LIKE :search OR u.firstName LIKE :search OR u.lastName LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        $users = $queryBuilder->getQuery()->getResult();

        // Calculate statistics
        $stats = [
            'total' => $this->userRepository->count([]),
            'active' => $this->userRepository->count(['status' => 'active']),
            'inactive' => $this->userRepository->count(['status' => 'inactive']),
            'admins' => count($this->userRepository->findByRole('ROLE_ADMIN')),
        ];

        return $this->render('admin/users/index.html.twig', [
            'users' => $users,
            'stats' => $stats,
            'current_filter' => $filter,
            'search_term' => $search,
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
            $user->setFirstName($request->request->get('firstName'));
            $user->setLastName($request->request->get('lastName'));
            $user->setPhone($request->request->get('phone'));
            $user->setStatus($request->request->get('status'));
            
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
            
            // If user is becoming admin and doesn't have @wannasni.com, update email
            if ($isBecomingAdmin && !str_ends_with(strtolower($currentEmail), '@wannasni.com')) {
                $newEmail = $username . '@wannasni.com';
                
                // Check if this email already exists
                $existingUser = $this->userRepository->findOneBy(['email' => $newEmail]);
                if ($existingUser && $existingUser->getId() !== $user->getId()) {
                    $this->addFlash('warning', 'L\'email ' . $newEmail . ' existe déjà. Le domaine n\'a pas été modifié.');
                } else {
                    $user->setEmail($newEmail);
                    $this->addFlash('info', 'Email mis à jour vers ' . $newEmail . ' car l\'utilisateur est maintenant administrateur.');
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
            fputcsv($handle, ['ID', 'Prénom', 'Nom', 'Email', 'Téléphone', 'Rôle', 'Statut', 'Date d\'inscription'], ';');
            
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
}
