<?php

namespace App\Repository;

use App\Entity\Data;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Data|null find($id, $lockMode = null, $lockVersion = null)
 * @method Data|null findOneBy(array $criteria, array $orderBy = null)
 * @method Data[]    findAll()
 * @method Data[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DataRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Data::class);
    }

    // /**
    //  * @return Data[] Returns an array of Data objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('d.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Data
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */


    public function getInfoListed($_dataId, $_limit = null)
    {
        $limit = $_limit ? "LIMIT " . $_limit : null;
        return $this->getEntityManager()->getConnection()
            ->query("
                   SELECT 
                        LEFT(info.period, 4) period,
                        info.val,
                        COUNT(info.period) conteo
                    FROM
                        (SELECT 
                           d_.period, d_.val
                        FROM
                            data as d_
                        WHERE
                            d_.info_id = $_dataId
                        ORDER BY d_.period DESC , d_.val 
                        $limit) info
                    GROUP BY LEFT(info.period, 4) , info.val; 
            ")->fetchAll();
    }

}
