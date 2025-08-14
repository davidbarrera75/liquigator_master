<?php

namespace App\Repository;

use App\Entity\Ipc;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Ipc|null find($id, $lockMode = null, $lockVersion = null)
 * @method Ipc|null findOneBy(array $criteria, array $orderBy = null)
 * @method Ipc[]    findAll()
 * @method Ipc[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class IpcRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Ipc::class);
    }

    // /**
    //  * @return Ipc[] Returns an array of Ipc objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('i.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Ipc
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
