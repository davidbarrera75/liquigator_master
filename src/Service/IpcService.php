<?php


namespace App\Service;


use App\Entity\Catalogo;
use App\Entity\Ipc;
use App\Entity\SalarioMinimo;
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
        return round($start, 7);
    }

    public function catalogo(string $catalogo, $params = false)
    {
        $repository = $this->em->getRepository(Catalogo::class);
        $return = $repository->findOneBy(['slug' => $catalogo]);
        return $params ? $return->getParametros() : $return->getDefaultValue();

    }

    public function salarioMinimo(int $year, $tope = false): float
    {
        $repo = $this->em->getRepository(SalarioMinimo::class)->findOneBy(['anio' => $year]);

        if ($repo === null) {
            throw new \Exception("No se encontró salario mínimo para el año {$year}. Por favor, configure este valor en la base de datos.");
        }

        return $tope ? $repo->getTope() : $repo->getValor();
    }

    //Return the textual representation of a month is SPANISH
    public static function getMonthService($month)
    {
        $months = [
            'January' => 'Enero',
            'February' => 'Febrero',
            'March' => 'Marzo',
            'April' => 'Abril',
            'May' => 'Mayo',
            'June' => 'Junio',
            'July' => 'Julio',
            'August' => 'Agosto',
            'September' => 'Septiembre',
            'October' => 'Octubre',
            'November' => 'Noviembre',
            'December' => 'Diciembre',
        ];
        return $months[$month];
    }
}