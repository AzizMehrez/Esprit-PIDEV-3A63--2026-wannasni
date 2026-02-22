<?php

namespace App\Controller\Admin;

use App\Entity\BeverageOrder;
use App\Entity\BeverageProduct;
use App\Entity\DemandeRegime;
use App\Entity\RegimePrescrit;
use App\Form\BeverageProductType;
use App\Form\RegimePrescritType;
use App\Repository\BeverageOrderRepository;
use App\Repository\BeverageProductRepository;
use App\Repository\DemandeRegimeRepository;
use App\Repository\RegimePrescritRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Dompdf\Dompdf;
use Dompdf\Options;

class NutritionAdminController extends AbstractController
{
    #[Route('/admin/nutrition', name: 'admin_nutrition')]
    public function index(Request $request, RegimePrescritRepository $repository): Response
    {
        $sort = $request->query->get('sort', 'desc');
        $regimePrescrits = $repository->findBy([], ['dateDebut' => $sort === 'asc' ? 'ASC' : 'DESC']);

        return $this->render('admin/regime_prescrit/index.html.twig', [
            'regime_prescrits' => $regimePrescrits,
            'current_sort' => $sort
        ]);
    }

    #[Route('/admin/nutrition/demandes', name: 'admin_nutrition_demandes')]
    public function demandesATraiter(Request $request, DemandeRegimeRepository $repository): Response
    {
        $sort = $request->query->get('sort', 'date');
        $type = $request->query->get('type', '');
        $query = $request->query->get('q', '');
        
        $queryBuilder = $repository->createQueryBuilder('d')
            ->leftJoin('d.regimesPrescrits', 'r')
            ->addSelect('r');

        // Filtrage par type
        if ($type) {
            $queryBuilder->andWhere('d.typeRegimeSouhaite = :type')
                ->setParameter('type', $type);
        }

        // Recherche textuelle (Senior ID ou Objectif)
        if ($query) {
            $queryBuilder->andWhere('d.seniorId LIKE :q OR d.objectifPrincipal LIKE :q OR d.typeRegimeSouhaite LIKE :q')
                ->setParameter('q', '%' . $query . '%');
        }

        // Tri
        if ($sort === 'status') {
            // Traitées en premier
            $queryBuilder->addSelect('(CASE WHEN r.id IS NOT NULL THEN 1 ELSE 0 END) AS HIDDEN status_sort')
                ->orderBy('status_sort', 'DESC')
                ->addOrderBy('d.dateDemande', 'DESC');
        } else {
            $queryBuilder->orderBy('d.dateDemande', 'DESC');
        }

        $demandes = $queryBuilder->getQuery()->getResult();

        return $this->render('admin/regime_prescrit/demandesatraiter.html.twig', [
            'demandes' => $demandes,
            'current_type' => $type,
            'current_sort' => $sort,
            'current_query' => $query,
        ]);
    }

    #[Route('/admin/nutrition/demande/{id}/delete', name: 'admin_nutrition_demande_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function deleteDemande(int $id, Request $request, DemandeRegimeRepository $repository, EntityManagerInterface $entityManager): Response
    {
        $demande = $repository->find($id);
        if (!$demande) {
            throw $this->createNotFoundException('Demande non trouvée');
        }

        if ($this->isCsrfTokenValid('delete'.$demande->getId(), $request->request->get('_token'))) {
            $entityManager->remove($demande);
            $entityManager->flush();
            $this->addFlash('success', 'Demande supprimée avec succès.');
        }

        return $this->redirectToRoute('admin_nutrition_demandes');
    }

    #[Route('/admin/nutrition/regime/{id}/delete', name: 'admin_nutrition_regime_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function deleteRegime(int $id, Request $request, RegimePrescritRepository $repository, EntityManagerInterface $entityManager): Response
    {
        $regime = $repository->find($id);
        if (!$regime) {
            throw $this->createNotFoundException('Régime non trouvé');
        }

        if ($this->isCsrfTokenValid('delete'.$regime->getId(), $request->request->get('_token'))) {
            // Rétablir le statut de la demande associée à "En attente" si nécessaire
            $demande = $regime->getDemande();
            if ($demande) {
                $demande->setStatut(DemandeRegime::STATUT_EN_ATTENTE);
            }

            $entityManager->remove($regime);
            $entityManager->flush();
            $this->addFlash('success', 'Régime prescrit supprimé avec succès.');
        }

        return $this->redirectToRoute('admin_nutrition');
    }

    #[Route('/admin/nutrition/new/demande/{id}', name: 'admin_nutrition_new', requirements: ['id' => '\d+'])]
    public function new(int $id, Request $request, DemandeRegimeRepository $demandeRepository, EntityManagerInterface $entityManager): Response
    {
        $demande = $demandeRepository->find($id);
        if (!$demande) {
            throw $this->createNotFoundException('Demande non trouvée');
        }

        $regime = new RegimePrescrit();
        $regime->setDemande($demande);
        $regime->setSeniorId($demande->getSeniorId());
        $regime->setNutritionnisteId(2); // Nutritionniste par défaut
        $regime->setTypeRegime($demande->getTypeRegimeSouhaite());
        
        // Set user relationship if available
        if ($demande->getUser()) {
            $regime->setUser($demande->getUser());
        }

        $form = $this->createForm(RegimePrescritType::class, $regime);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $demande->setStatut(DemandeRegime::STATUT_TRAITE);
            $demande->setDateTraitement(new \DateTime());
            
            $entityManager->persist($regime);
            $entityManager->flush();

            $this->addFlash('success', 'Régime prescrit avec succès !');
            return $this->redirectToRoute('admin_nutrition_demandes');
        }

        return $this->render('admin/regime_prescrit/new.html.twig', [
            'demande' => $demande,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/admin/nutrition/{id}', name: 'admin_nutrition_show', requirements: ['id' => '\d+'])]
    public function show(int $id, RegimePrescritRepository $repository): Response
    {
        $regime = $repository->find($id);

        if (!$regime) {
            throw $this->createNotFoundException('Régime prescrit non trouvé');
        }

        return $this->render('admin/regime_prescrit/show.html.twig', [
            'regime_prescrit' => $regime,
        ]);
    }

    #[Route('/admin/nutrition/{id}/edit', name: 'admin_nutrition_edit', requirements: ['id' => '\d+'])]
    public function edit(int $id, Request $request, RegimePrescritRepository $repository, EntityManagerInterface $entityManager): Response
    {
        $regime = $repository->find($id);

        if (!$regime) {
            throw $this->createNotFoundException('Régime prescrit non trouvé');
        }

        $form = $this->createForm(RegimePrescritType::class, $regime);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'Régime modifié avec succès !');
            return $this->redirectToRoute('admin_nutrition');
        }

        return $this->render('admin/regime_prescrit/edit.html.twig', [
            'regime_prescrit' => $regime,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/admin/nutrition/export-pdf/{id}', name: 'admin_nutrition_export_pdf', requirements: ['id' => '\d+'])]
    public function exportPdf(int $id, DemandeRegimeRepository $repository): Response
    {
        $demande = $repository->find($id);
        if (!$demande || $demande->getRegimesPrescrits()->isEmpty()) {
            throw $this->createNotFoundException('Demande non trouvée ou non traitée');
        }

        $regime = $demande->getRegimesPrescrits()->last();

        if (class_exists(Dompdf::class)) {
            $pdfOptions = new Options();
            $pdfOptions->set('defaultFont', 'Arial');
            $pdfOptions->set('isRemoteEnabled', true);

            $dompdf = new Dompdf($pdfOptions);
            $html = $this->renderView('admin/regime_prescrit/pdf_template.html.twig', [
                'demande' => $demande,
                'regime' => $regime,
            ]);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            
            return new Response($dompdf->output(), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="regime_senior_'.$demande->getSeniorId().'.pdf"'
            ]);
        }

        $this->addFlash('warning', 'La librairie PDF n\'est pas installée sur le serveur. Voici la version imprimable.');
        return $this->render('admin/regime_prescrit/pdf_template.html.twig', [
            'demande' => $demande,
            'regime' => $regime,
        ]);
    }

    // ═══════════════════════════════════════════════
    //  MARKETPLACE - GESTION DES PRODUITS (CRUD)
    // ═══════════════════════════════════════════════

    #[Route('/admin/nutrition/marketplace', name: 'admin_nutrition_marketplace')]
    public function marketplace(Request $request, BeverageProductRepository $productRepo): Response
    {
        $category = $request->query->get('category', '');
        $search = $request->query->get('q', '');

        $qb = $productRepo->createQueryBuilder('p')
            ->orderBy('p.category', 'ASC')
            ->addOrderBy('p.name', 'ASC');

        if ($category) {
            $qb->andWhere('p.category = :cat')->setParameter('cat', $category);
        }
        if ($search) {
            $qb->andWhere('p.name LIKE :q OR p.brand LIKE :q')
               ->setParameter('q', '%' . $search . '%');
        }

        $products = $qb->getQuery()->getResult();

        // Stats
        $totalProducts = $productRepo->count([]);
        $activeProducts = $productRepo->count(['isActive' => true]);
        $featuredProducts = $productRepo->count(['isFeatured' => true]);
        $outOfStock = $productRepo->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.stockQuantity = 0')
            ->getQuery()->getSingleScalarResult();

        return $this->render('admin/marketplace/index.html.twig', [
            'products' => $products,
            'total_products' => $totalProducts,
            'active_products' => $activeProducts,
            'featured_products' => $featuredProducts,
            'out_of_stock' => $outOfStock,
            'current_category' => $category,
            'current_search' => $search,
        ]);
    }

    #[Route('/admin/nutrition/marketplace/new', name: 'admin_nutrition_marketplace_new')]
    public function marketplaceNew(Request $request, EntityManagerInterface $em): Response
    {
        $product = new BeverageProduct();
        $product->setIsActive(true);

        $form = $this->createForm(BeverageProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($product);
            $em->flush();
            $this->addFlash('success', 'Produit "' . $product->getName() . '" ajouté avec succès !');
            return $this->redirectToRoute('admin_nutrition_marketplace');
        }

        return $this->render('admin/marketplace/form.html.twig', [
            'form' => $form->createView(),
            'product' => $product,
            'is_edit' => false,
        ]);
    }

    #[Route('/admin/nutrition/marketplace/{id}/edit', name: 'admin_nutrition_marketplace_edit', requirements: ['id' => '\d+'])]
    public function marketplaceEdit(int $id, Request $request, BeverageProductRepository $repo, EntityManagerInterface $em): Response
    {
        $product = $repo->find($id);
        if (!$product) {
            throw $this->createNotFoundException('Produit non trouvé');
        }

        $form = $this->createForm(BeverageProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Produit "' . $product->getName() . '" modifié avec succès !');
            return $this->redirectToRoute('admin_nutrition_marketplace');
        }

        return $this->render('admin/marketplace/form.html.twig', [
            'form' => $form->createView(),
            'product' => $product,
            'is_edit' => true,
        ]);
    }

    #[Route('/admin/nutrition/marketplace/{id}/delete', name: 'admin_nutrition_marketplace_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function marketplaceDelete(int $id, Request $request, BeverageProductRepository $repo, EntityManagerInterface $em): Response
    {
        $product = $repo->find($id);
        if (!$product) {
            throw $this->createNotFoundException('Produit non trouvé');
        }

        if ($this->isCsrfTokenValid('delete' . $product->getId(), $request->request->get('_token'))) {
            $em->remove($product);
            $em->flush();
            $this->addFlash('success', 'Produit supprimé avec succès.');
        }

        return $this->redirectToRoute('admin_nutrition_marketplace');
    }

    #[Route('/admin/nutrition/marketplace/{id}/toggle', name: 'admin_nutrition_marketplace_toggle', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function marketplaceToggle(int $id, BeverageProductRepository $repo, EntityManagerInterface $em): Response
    {
        $product = $repo->find($id);
        if (!$product) {
            throw $this->createNotFoundException('Produit non trouvé');
        }

        $product->setIsActive(!$product->isActive());
        $em->flush();

        $status = $product->isActive() ? 'activé' : 'désactivé';
        $this->addFlash('success', 'Produit "' . $product->getName() . '" ' . $status . '.');

        return $this->redirectToRoute('admin_nutrition_marketplace');
    }

    // ═══════════════════════════════════════════════
    //  MARKETPLACE - COMMANDES
    // ═══════════════════════════════════════════════

    #[Route('/admin/nutrition/marketplace/commandes', name: 'admin_nutrition_marketplace_orders')]
    public function marketplaceOrders(Request $request, BeverageOrderRepository $orderRepo): Response
    {
        $status = $request->query->get('status', '');
        $search = $request->query->get('q', '');

        $qb = $orderRepo->createQueryBuilder('o')
            ->leftJoin('o.user', 'u')
            ->addSelect('u')
            ->leftJoin('o.items', 'i')
            ->addSelect('i')
            ->andWhere('o.status != :cart')
            ->setParameter('cart', BeverageOrder::STATUS_CART)
            ->orderBy('o.createdAt', 'DESC');

        if ($status) {
            $qb->andWhere('o.status = :status')->setParameter('status', $status);
        }
        if ($search) {
            $qb->andWhere('o.orderNumber LIKE :q OR u.firstName LIKE :q OR u.lastName LIKE :q OR u.email LIKE :q')
               ->setParameter('q', '%' . $search . '%');
        }

        $orders = $qb->getQuery()->getResult();

        // Stats
        $totalOrders = $orderRepo->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->where('o.status != :cart')->setParameter('cart', BeverageOrder::STATUS_CART)
            ->getQuery()->getSingleScalarResult();

        $pendingOrders = $orderRepo->count(['status' => BeverageOrder::STATUS_PENDING]);
        $confirmedOrders = $orderRepo->count(['status' => BeverageOrder::STATUS_CONFIRMED]);
        $deliveredOrders = $orderRepo->count(['status' => BeverageOrder::STATUS_DELIVERED]);

        $totalRevenue = $orderRepo->createQueryBuilder('o')
            ->select('SUM(o.totalAmount)')
            ->where('o.status IN (:statuses)')
            ->setParameter('statuses', [BeverageOrder::STATUS_CONFIRMED, BeverageOrder::STATUS_SHIPPED, BeverageOrder::STATUS_DELIVERED])
            ->getQuery()->getSingleScalarResult() ?? 0;

        return $this->render('admin/marketplace/orders.html.twig', [
            'orders' => $orders,
            'total_orders' => $totalOrders,
            'pending_orders' => $pendingOrders,
            'confirmed_orders' => $confirmedOrders,
            'delivered_orders' => $deliveredOrders,
            'total_revenue' => $totalRevenue,
            'current_status' => $status,
            'current_search' => $search,
        ]);
    }

    #[Route('/admin/nutrition/marketplace/commandes/{id}', name: 'admin_nutrition_marketplace_order_show', requirements: ['id' => '\d+'])]
    public function marketplaceOrderShow(int $id, BeverageOrderRepository $orderRepo): Response
    {
        $order = $orderRepo->find($id);
        if (!$order) {
            throw $this->createNotFoundException('Commande non trouvée');
        }

        return $this->render('admin/marketplace/order_show.html.twig', [
            'order' => $order,
        ]);
    }

    #[Route('/admin/nutrition/marketplace/commandes/{id}/status', name: 'admin_nutrition_marketplace_order_status', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function marketplaceOrderStatus(int $id, Request $request, BeverageOrderRepository $orderRepo, EntityManagerInterface $em): Response
    {
        $order = $orderRepo->find($id);
        if (!$order) {
            throw $this->createNotFoundException('Commande non trouvée');
        }

        $newStatus = $request->request->get('status');
        $validStatuses = [
            BeverageOrder::STATUS_PENDING,
            BeverageOrder::STATUS_CONFIRMED,
            BeverageOrder::STATUS_SHIPPED,
            BeverageOrder::STATUS_DELIVERED,
            BeverageOrder::STATUS_CANCELLED,
        ];

        if (in_array($newStatus, $validStatuses)) {
            $order->setStatus($newStatus);

            if ($newStatus === BeverageOrder::STATUS_CONFIRMED) {
                $order->setConfirmedAt(new \DateTime());
            } elseif ($newStatus === BeverageOrder::STATUS_SHIPPED) {
                $order->setShippedAt(new \DateTime());
            } elseif ($newStatus === BeverageOrder::STATUS_DELIVERED) {
                $order->setDeliveredAt(new \DateTime());
            }

            $em->flush();
            $this->addFlash('success', 'Statut de la commande ' . $order->getOrderNumber() . ' mis à jour.');
        }

        return $this->redirectToRoute('admin_nutrition_marketplace_order_show', ['id' => $id]);
    }
}
