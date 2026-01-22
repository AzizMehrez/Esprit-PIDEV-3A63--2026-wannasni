<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin', name: 'app_admin_')]
class BackOfficeController extends AbstractController
{
    #[Route('/login', name: 'login')]
    public function login(): Response
    {
        return $this->render('back/login.html.twig');
    }

    #[Route('/dashboard', name: 'dashboard')]
    public function dashboard(): Response
    {
        return $this->render('back/dashboard.html.twig');
    }

    // ===== GESTION DES UTILISATEURS =====
    #[Route('/users', name: 'users')]
    public function users(): Response
    {
        return $this->render('back/users/index.html.twig');
    }

    #[Route('/users/add', name: 'users_add')]
    public function usersAdd(): Response
    {
        return $this->render('back/users/add.html.twig');
    }

    #[Route('/users/{id}', name: 'users_view')]
    public function usersView(int $id): Response
    {
        return $this->render('back/users/view.html.twig', ['id' => $id]);
    }

    #[Route('/users/{id}/edit', name: 'users_edit')]
    public function usersEdit(int $id): Response
    {
        return $this->render('back/users/edit.html.twig', ['id' => $id]);
    }

    // ===== GESTION DU SUIVI SANTÉ =====
    #[Route('/health', name: 'health')]
    public function health(): Response
    {
        return $this->render('back/health/index.html.twig');
    }

    #[Route('/health/metrics', name: 'health_metrics')]
    public function healthMetrics(): Response
    {
        return $this->render('back/health/metrics.html.twig');
    }

    #[Route('/health/thresholds', name: 'health_thresholds')]
    public function healthThresholds(): Response
    {
        return $this->render('back/health/thresholds.html.twig');
    }

    // ===== GESTION DES PROCHES ET ALERTES =====
    #[Route('/family', name: 'family')]
    public function family(): Response
    {
        return $this->render('back/family/index.html.twig');
    }

    #[Route('/family/connections', name: 'family_connections')]
    public function familyConnections(): Response
    {
        return $this->render('back/family/connections.html.twig');
    }

    #[Route('/alerts', name: 'alerts')]
    public function alerts(): Response
    {
        return $this->render('back/alerts/index.html.twig');
    }

    #[Route('/alerts/config', name: 'alerts_config')]
    public function alertsConfig(): Response
    {
        return $this->render('back/alerts/config.html.twig');
    }

    // ===== GESTION DES HABITUDES ET RECOMMANDATIONS =====
    #[Route('/habits', name: 'habits')]
    public function habits(): Response
    {
        return $this->render('back/habits/index.html.twig');
    }

    #[Route('/habits/templates', name: 'habits_templates')]
    public function habitsTemplates(): Response
    {
        return $this->render('back/habits/templates.html.twig');
    }

    #[Route('/recommendations', name: 'recommendations')]
    public function recommendations(): Response
    {
        return $this->render('back/recommendations/index.html.twig');
    }

    // ===== GESTION DES MINI-DÉFIS =====
    #[Route('/challenges', name: 'challenges')]
    public function challenges(): Response
    {
        return $this->render('back/challenges/index.html.twig');
    }

    #[Route('/challenges/add', name: 'challenges_add')]
    public function challengesAdd(): Response
    {
        return $this->render('back/challenges/add.html.twig');
    }

    #[Route('/challenges/rewards', name: 'challenges_rewards')]
    public function challengesRewards(): Response
    {
        return $this->render('back/challenges/rewards.html.twig');
    }

    // ===== RAPPORTS ET PARAMÈTRES =====
    #[Route('/reports', name: 'reports')]
    public function reports(): Response
    {
        return $this->render('back/reports.html.twig');
    }

    #[Route('/settings', name: 'settings')]
    public function settings(): Response
    {
        return $this->render('back/settings.html.twig');
    }
}
