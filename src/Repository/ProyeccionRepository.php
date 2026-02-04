<?php

namespace App\Repository;

use App\Entity\Proyeccion;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Proyeccion|null find($id, $lockMode = null, $lockVersion = null)
 * @method Proyeccion|null findOneBy(array $criteria, array $orderBy = null)
 * @method Proyeccion[]    findAll()
 * @method Proyeccion[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ProyeccionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Proyeccion::class);
    }

    // /**
    //  * @return Proyeccion[] Returns an array of Proyeccion objects
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
    public function findOneBySomeField($value): ?Proyeccion
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
