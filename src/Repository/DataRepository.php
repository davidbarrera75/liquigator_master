<?php

namespace App\Repository;

use App\Entity\Data;
use App\Entity\Information;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NonUniqueResultException;
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

    public function getInfoListed($_dataId, $anio = null, $_limit = -1)
    {

        $limit = $_limit == -1 ? null: "LIMIT " . $_limit;
        $anio = $anio ? "AND left(d_.period,4) <= " . $anio : null;
        return $this->getEntityManager()->getConnection()
            ->query("
                   SELECT 
                        info.year_ period,
                        info.val,
                        COUNT(info.period) conteo
                    FROM
                        (SELECT 
                           d_.period, d_.val,left(d_.period,4) as year_
                        FROM
                            data as d_
                        WHERE
                            d_.info_id = $_dataId 
                            $anio
                        ORDER BY d_.period DESC , d_.val 
                        $limit) info
                    GROUP BY info.year_ , info.val; 
            ")->fetchAll();
    }

    public function getLast(Information $data_id):?Data
    {
        $query = $this->createQueryBuilder('s')
            ->where('s.info  = :id')
            ->orderBy('s.period', 'DESC')
            ->setMaxResults(1)
            ->setParameter('id', $data_id);
        try {
            return $query->getQuery()->getOneOrNullResult();
        } catch (NonUniqueResultException $e) {
            return null;
        }
    }
}
