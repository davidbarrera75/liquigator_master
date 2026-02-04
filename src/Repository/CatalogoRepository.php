<?php

namespace App\Repository;

use App\Entity\Catalogo;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Catalogo|null find($id, $lockMode = null, $lockVersion = null)
 * @method Catalogo|null findOneBy(array $criteria, array $orderBy = null)
 * @method Catalogo[]    findAll()
 * @method Catalogo[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CatalogoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Catalogo::class);
    }

    // /**
    //  * @return Catalogo[] Returns an array of Catalogo objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('c.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Catalogo
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
