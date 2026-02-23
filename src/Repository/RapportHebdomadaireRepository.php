<?php

namespace App\Repository;

use App\Entity\RapportHebdomadaire;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RapportHebdomadaire>
 *
 * @method RapportHebdomadaire|null find($id, $lockMode = null, $lockVersion = null)
 * @method RapportHebdomadaire|null findOneBy(array $criteria, array $orderBy = null)
 * @method RapportHebdomadaire[]    findAll()
 * @method RapportHebdomadaire[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class RapportHebdomadaireRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RapportHebdomadaire::class);
    }
}
