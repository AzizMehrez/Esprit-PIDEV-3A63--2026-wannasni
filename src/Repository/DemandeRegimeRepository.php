<?php

namespace App\Repository;

use App\Entity\DemandeRegime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DemandeRegime>
 *
 * @method DemandeRegime|null find($id, $lockMode = null, $lockVersion = null)
 * @method DemandeRegime|null findOneBy(array $criteria, array $orderBy = null)
 * @method DemandeRegime[]    findAll()
 * @method DemandeRegime[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DemandeRegimeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DemandeRegime::class);
    }

    /**
     * Sauvegarde une demande de régime
     */
    public function save(DemandeRegime $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Supprime une demande de régime
     */
    public function remove(DemandeRegime $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Trouve les demandes par statut
     */
    public function findByStatut(string $statut): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.statut = :statut')
            ->setParameter('statut', $statut)
            ->orderBy('d.dateDemande', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les demandes en attente
     */
    public function findEnAttente(): array
    {
        return $this->findByStatut(DemandeRegime::STATUT_EN_ATTENTE);
    }

    /**
     * Trouve les demandes acceptées
     */
    public function findAcceptees(): array
    {
        return $this->findByStatut(DemandeRegime::STATUT_ACCEPTE);
    }

    /**
     * Trouve les demandes traitées
     */
    public function findTraitees(): array
    {
        return $this->findByStatut(DemandeRegime::STATUT_TRAITE);
    }

    /**
     * Trouve les demandes récentes (limite par défaut: 10)
     */
    public function findRecent(int $limit = 10): array
    {
        return $this->createQueryBuilder('d')
            ->orderBy('d.dateDemande', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les demandes par senior
     */
    public function findBySeniorId(int $seniorId): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.seniorId = :seniorId')
            ->setParameter('seniorId', $seniorId)
            ->orderBy('d.dateDemande', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les demandes par nutritionniste
     */
    public function findByNutritionnisteId(int $nutritionnisteId): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.nutritionnisteId = :nutritionnisteId')
            ->setParameter('nutritionnisteId', $nutritionnisteId)
            ->orderBy('d.dateDemande', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les demandes par type de régime
     */
    public function findByTypeRegime(string $typeRegime): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.typeRegimeSouhaite = :typeRegime')
            ->setParameter('typeRegime', $typeRegime)
            ->orderBy('d.dateDemande', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte le nombre de demandes par statut
     */
    public function countByStatut(): array
    {
        $results = $this->createQueryBuilder('d')
            ->select('d.statut, COUNT(d.id) as count')
            ->groupBy('d.statut')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($results as $result) {
            $counts[$result['statut']] = $result['count'];
        }

        return $counts;
    }

    /**
     * Trouve les demandes avec date de traitement nulle
     */
    public function findSansTraitement(): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.dateTraitement IS NULL')
            ->orderBy('d.dateDemande', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les demandes traitées dans un intervalle de dates
     */
    public function findTraiteesEntreDates(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.statut = :statut')
            ->andWhere('d.dateTraitement BETWEEN :startDate AND :endDate')
            ->setParameter('statut', DemandeRegime::STATUT_TRAITE)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('d.dateTraitement', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche dans les descriptions
     */
    public function search(string $searchTerm): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.description LIKE :searchTerm')
            ->orWhere('d.typeRegimeSouhaite LIKE :searchTerm')
            ->orWhere('d.objectifPrincipal LIKE :searchTerm')
            ->orWhere('d.allergies LIKE :searchTerm')
            ->orWhere('d.intolerances LIKE :searchTerm')
            ->setParameter('searchTerm', '%' . $searchTerm . '%')
            ->orderBy('d.dateDemande', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Statistiques mensuelles
     */
    public function getMonthlyStatistics(int $year = null): array
    {
        if ($year === null) {
            $year = (int) date('Y');
        }

        $query = $this->createQueryBuilder('d')
            ->select('MONTH(d.dateDemande) as month, COUNT(d.id) as count')
            ->where('YEAR(d.dateDemande) = :year')
            ->setParameter('year', $year)
            ->groupBy('month')
            ->orderBy('month', 'ASC')
            ->getQuery();

        $results = $query->getResult();

        // Formater les résultats
        $stats = array_fill(1, 12, 0);
        foreach ($results as $result) {
            $stats[(int) $result['month']] = (int) $result['count'];
        }

        return $stats;
    }

    /**
     * Trouve les demandes avec un budget supérieur à
     */
    public function findByBudgetSuperieurA(int $budgetMin): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.budgetMensuel >= :budgetMin')
            ->setParameter('budgetMin', $budgetMin)
            ->orderBy('d.budgetMensuel', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les demandes avec allergies spécifiques
     */
    public function findByAllergie(string $allergie): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.allergies LIKE :allergie')
            ->setParameter('allergie', '%' . $allergie . '%')
            ->orderBy('d.dateDemande', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les demandes par objectif
     */
    public function findByObjectif(string $objectif): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.objectifPrincipal = :objectif')
            ->setParameter('objectif', $objectif)
            ->orderBy('d.dateDemande', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les demandes non traitées par senior
     */
    public function findEnAttenteBySeniorId(int $seniorId): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.seniorId = :seniorId')
            ->andWhere('d.statut = :statut')
            ->setParameter('seniorId', $seniorId)
            ->setParameter('statut', DemandeRegime::STATUT_EN_ATTENTE)
            ->orderBy('d.dateDemande', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Statistiques par senior
     */
    public function getStatsBySeniorId(int $seniorId): array
    {
        $results = $this->createQueryBuilder('d')
            ->select('d.statut, COUNT(d.id) as count')
            ->andWhere('d.seniorId = :seniorId')
            ->setParameter('seniorId', $seniorId)
            ->groupBy('d.statut')
            ->getQuery()
            ->getResult();

        $stats = [
            'total' => 0,
            'en_attente' => 0,
            'accepte' => 0,
            'refuse' => 0,
            'traite' => 0
        ];

        foreach ($results as $result) {
            $stats[$result['statut']] = (int) $result['count'];
            $stats['total'] += (int) $result['count'];
        }

        return $stats;
    }
     // NOUVELLE méthode pour le back/admin
    public function findDemandesByNutritionniste(int $nutritionnisteId): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.nutritionnisteId = :nutritionnisteId')
            ->andWhere('d.statut = :statut')
            ->setParameter('nutritionnisteId', $nutritionnisteId)
            ->setParameter('statut', DemandeRegime::STATUT_EN_ATTENTE)
            ->orderBy('d.dateDemande', 'ASC')
            ->getQuery()
            ->getResult();
    }

    // Pour avoir toutes les demandes (admin complet)
    public function findAllDemandes(): array
    {
        return $this->createQueryBuilder('d')
            ->orderBy('d.dateDemande', 'DESC')
            ->getQuery()
            ->getResult();
    }

}