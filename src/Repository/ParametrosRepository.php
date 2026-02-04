<?php

namespace App\Repository;

use App\Entity\Parametros;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Parametros|null find($id, $lockMode = null, $lockVersion = null)
 * @method Parametros|null findOneBy(array $criteria, array $orderBy = null)
 * @method Parametros[]    findAll()
 * @method Parametros[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ParametrosRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Parametros::class);
    }

    // /**
    //  * @return Parametros[] Returns an array of Parametros objects
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
    public function findOneBySomeField($value): ?Parametros
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
