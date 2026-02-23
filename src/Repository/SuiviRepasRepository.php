<?php

namespace App\Repository;

use App\Entity\SuiviRepas;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SuiviRepas>
 *
 * @method SuiviRepas|null find($id, $lockMode = null, $lockVersion = null)
 * @method SuiviRepas|null findOneBy(array $criteria, array $orderBy = null)
 * @method SuiviRepas[]    findAll()
 * @method SuiviRepas[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SuiviRepasRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SuiviRepas::class);
    }
}
