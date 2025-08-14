<?php


namespace App\Service;


use App\Entity\Ipc;
use Doctrine\ORM\EntityManagerInterface;

class IpcService
{
    private $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function calculate(int $year, int $initial = null)
    {
        $em = $this->em;
        $initial = $initial ?: date('Y');
        $start = 0;
        if ($year * 1 === $initial * 1) {
            $start = 1;
        } else {
            if ($initial > $year && $initial >= Ipc::MIN_YEAR) {
                $ipc = $em->getRepository(Ipc::class)->findOneBy(['anio' => $initial]);
                $initial -= 1;
                $start = $ipc->getIpc() * $this->calculate($year, $initial);
            } else {
                return 1;
            }
        }
        return round($start,7);
    }

}