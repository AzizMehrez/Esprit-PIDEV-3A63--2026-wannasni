<?php

namespace App\Controller\Admin;

use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class NotificationController extends AbstractController
{
    private NotificationRepository $repo;

    public function __construct(NotificationRepository $repo)
    {
        $this->repo = $repo;
    }

    // Render fragment used in admin header
    public function menu(): Response
    {
        $notifications = $this->repo->findUnread(10);

        return $this->render('admin/_notifications.html.twig', [
            'notifications' => $notifications,
        ]);
    }

    #[Route('/admin/notifications/{id}/mark-read', name: 'admin_notifications_mark_read', methods: ['POST'])]
    public function markRead(int $id, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('notif_mark_read'.$id, $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirect($request->headers->get('referer') ?? $this->generateUrl('admin_dashboard'));
        }

        $n = $this->repo->find($id);
        if ($n) {
            $n->setIsRead(true);
            $em->flush();
            $this->addFlash('success', 'Notification marquée comme lue.');
        }

        return $this->redirect($request->headers->get('referer') ?? $this->generateUrl('admin_dashboard'));
    }

    #[Route('/admin/notifications/{id}/mark-unread', name: 'admin_notifications_mark_unread', methods: ['POST'])]
    public function markUnread(int $id, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('notif_mark_unread'.$id, $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirect($request->headers->get('referer') ?? $this->generateUrl('admin_dashboard'));
        }

        $n = $this->repo->find($id);
        if ($n) {
            $n->setIsRead(false);
            $em->flush();
            $this->addFlash('success', 'Notification marquée comme non lue.');
        }

        return $this->redirect($request->headers->get('referer') ?? $this->generateUrl('admin_dashboard'));
    }

    #[Route('/admin/notifications/{id}/delete', name: 'admin_notifications_delete', methods: ['POST'])]
    public function delete(int $id, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('notif_delete'.$id, $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirect($request->headers->get('referer') ?? $this->generateUrl('admin_dashboard'));
        }

        $n = $this->repo->find($id);
        if ($n) {
            $em->remove($n);
            $em->flush();
            $this->addFlash('success', 'Notification supprimée.');
        }

        return $this->redirect($request->headers->get('referer') ?? $this->generateUrl('admin_dashboard'));
    }

    #[Route('/admin/notifications/mark-all-read', name: 'admin_notifications_mark_all_read', methods: ['POST'])]
    public function markAllRead(Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('notif_mark_all_read', $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirect($request->headers->get('referer') ?? $this->generateUrl('admin_dashboard'));
        }

        $notifications = $this->repo->findUnread(1000);
        foreach ($notifications as $n) {
            $n->setIsRead(true);
        }
        $em->flush();

        $this->addFlash('success', 'Toutes les notifications ont été marquées comme lues.');
        return $this->redirect($request->headers->get('referer') ?? $this->generateUrl('admin_dashboard'));
    }
}
