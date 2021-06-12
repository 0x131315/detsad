<?php

namespace App\Repository;

use App\Entity\PresenceHistory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method PresenceHistory|null find($id, $lockMode = null, $lockVersion = null)
 * @method PresenceHistory|null findOneBy(array $criteria, array $orderBy = null)
 * @method PresenceHistory[]    findAll()
 * @method PresenceHistory[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PresenceHistoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PresenceHistory::class);
    }

    // /**
    //  * @return PresenceHistory[] Returns an array of PresenceHistory objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('p.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?PresenceHistory
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
