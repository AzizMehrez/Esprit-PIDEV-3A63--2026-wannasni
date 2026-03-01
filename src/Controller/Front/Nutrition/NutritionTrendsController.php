<?php

namespace App\Controller\Front\Nutrition;

use App\Entity\SuiviRepas;
use App\Service\PythonMLService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/{_locale}/nutrition/trends', requirements: ['_locale' => 'fr|en|ar'])]
#[IsGranted('ROLE_USER')]
class NutritionTrendsController extends AbstractController
{
    #[Route('/', name: 'app_nutrition_trends')]
    public function index(EntityManagerInterface $em, PythonMLService $pythonMLService): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        // Fetch last 30 meals for trend analysis
        $history = $em->getRepository(SuiviRepas::class)->createQueryBuilder('s')
            ->where('s.senior = :user')
            ->setParameter('user', $user)
            ->orderBy('s.dateRepas', 'DESC')
            ->setMaxResults(30)
            ->getQuery()
            ->getResult();

        $historyData = [];
        /** @var SuiviRepas $item */
        foreach ($history as $item) {
            $historyData[] = [
                'date' => $item->getDateRepas()->format('Y-m-d H:i:s'),
                'calories' => $item->getCaloriesCalculees(),
                'conforme' => $item->isEstConforme(),
                'aliments' => $item->getAlimentsIdentifies(),
            ];
        }

        $trends = $pythonMLService->analyzeTrends($historyData);

        return $this->render('front/nutrition/trends.html.twig', [
            'trends' => $trends,
            'history_count' => count($history),
        ]);
    }
}
