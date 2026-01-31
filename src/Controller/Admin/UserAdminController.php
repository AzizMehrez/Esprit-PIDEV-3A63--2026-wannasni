<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/users')]
class UserAdminController extends AbstractController
{
    // Mock users data
    private function getMockUsers(): array
    {
        return [
            ['id' => 1, 'email' => 'marie.dupont@email.com', 'firstName' => 'Marie', 'lastName' => 'Dupont', 'phone' => '+33 6 12 34 56 78', 'status' => 'active', 'roles' => ['ROLE_USER'], 'createdAt' => new \DateTime('-30 days')],
            ['id' => 2, 'email' => 'jean.martin@email.com', 'firstName' => 'Jean', 'lastName' => 'Martin', 'phone' => '+33 6 98 76 54 32', 'status' => 'active', 'roles' => ['ROLE_USER'], 'createdAt' => new \DateTime('-25 days')],
            ['id' => 3, 'email' => 'sophie.bernard@email.com', 'firstName' => 'Sophie', 'lastName' => 'Bernard', 'phone' => '+33 6 11 22 33 44', 'status' => 'inactive', 'roles' => ['ROLE_USER'], 'createdAt' => new \DateTime('-20 days')],
            ['id' => 4, 'email' => 'pierre.durand@email.com', 'firstName' => 'Pierre', 'lastName' => 'Durand', 'phone' => '+33 6 55 66 77 88', 'status' => 'active', 'roles' => ['ROLE_USER', 'ROLE_CAREGIVER'], 'createdAt' => new \DateTime('-15 days')],
            ['id' => 5, 'email' => 'francoise.petit@email.com', 'firstName' => 'Françoise', 'lastName' => 'Petit', 'phone' => '+33 6 99 88 77 66', 'status' => 'suspended', 'roles' => ['ROLE_USER'], 'createdAt' => new \DateTime('-10 days')],
            ['id' => 6, 'email' => 'admin@wannasni.com', 'firstName' => 'Admin', 'lastName' => 'System', 'phone' => '+33 6 00 00 00 00', 'status' => 'active', 'roles' => ['ROLE_ADMIN'], 'createdAt' => new \DateTime('-60 days')],
        ];
    }

    #[Route('/', name: 'admin_users')]
    public function index(): Response
    {
        return $this->render('admin/users/index.html.twig', [
            'users' => $this->getMockUsers(),
        ]);
    }

    #[Route('/{id}', name: 'admin_users_show', requirements: ['id' => '\d+'])]
    public function show(int $id): Response
    {
        $users = $this->getMockUsers();
        $user = null;
        foreach ($users as $u) {
            if ($u['id'] === $id) {
                $user = $u;
                break;
            }
        }

        if (!$user) {
            throw $this->createNotFoundException('User not found');
        }

        return $this->render('admin/users/show.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_users_edit', requirements: ['id' => '\d+'])]
    public function edit(int $id): Response
    {
        $users = $this->getMockUsers();
        $user = null;
        foreach ($users as $u) {
            if ($u['id'] === $id) {
                $user = $u;
                break;
            }
        }

        if (!$user) {
            throw $this->createNotFoundException('User not found');
        }

        return $this->render('admin/users/edit.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/new', name: 'admin_users_new')]
    public function new(): Response
    {
        return $this->render('admin/users/new.html.twig');
    }
}
