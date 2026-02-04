<?php

namespace App\Repository;

use App\Entity\PDFReport;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method PDFReport|null find($id, $lockMode = null, $lockVersion = null)
 * @method PDFReport|null findOneBy(array $criteria, array $orderBy = null)
 * @method PDFReport[]    findAll()
 * @method PDFReport[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PDFReportRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PDFReport::class);
    }

    // /**
    //  * @return PDFReport[] Returns an array of PDFReport objects
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
    public function findOneBySomeField($value): ?PDFReport
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
