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

    /**
     * @param int $year
     * @return PresenceHistory[]
     */
    public function findByYear(int $year)
    {
        $startDate = (new \DateTime())->setDate($year, 1, 1)->setTime(0, 0, 0);
        $endDate = (new \DateTime())->setDate($year, 12, 31)->setTime(23, 59, 59);

        return $this->createQueryBuilder('p')
            ->andWhere('p.date BETWEEN :start AND :end')
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->getQuery()
            ->getResult();
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
