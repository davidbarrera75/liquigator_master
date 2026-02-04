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

    /**
     * Calcula las semanas exigidas para pensión según género y año
     * Según Sentencia C-197 de 2023, las mujeres tienen requisito diferenciado
     *
     * @param string $genero 'M' para masculino, 'F' para femenino
     * @param int|null $anio Año para el cálculo (default: año actual)
     * @return int Número de semanas exigidas
     */
    public function semanasExigidas(string $genero = 'M', ?int $anio = null): int
    {
        $anio = $anio ?? (int) date('Y');

        // Para hombres siempre son 1,300 semanas
        if ($genero === 'M') {
            return $this->catalogo('SemanasBasicas');
        }

        // Sentencia C-197 de 2023 - Semanas exigidas para mujeres
        $semanasMujeres = [
            2025 => 1300,
            2026 => 1250,
            2027 => 1225,
            2028 => 1200,
            2029 => 1175,
            2030 => 1150,
            2031 => 1125,
            2032 => 1100,
            2033 => 1075,
            2034 => 1050,
            2035 => 1025,
            2036 => 1000,
        ];

        // Si el año es anterior a 2025, aplicar el requisito general
        if ($anio < 2025) {
            return $this->catalogo('SemanasBasicas');
        }

        // Si el año es 2036 o posterior, el mínimo es 1000 semanas
        if ($anio >= 2036) {
            return 1000;
        }

        // Retornar las semanas correspondientes al año
        return $semanasMujeres[$anio] ?? $this->catalogo('SemanasBasicas');
    }

    /**
     * Obtiene información sobre el requisito de semanas según género
     *
     * @param string $genero 'M' para masculino, 'F' para femenino
     * @param int|null $anio Año para el cálculo
     * @return array Información detallada del requisito
     */
    public function infoSemanasExigidas(string $genero = 'M', ?int $anio = null): array
    {
        $anio = $anio ?? (int) date('Y');
        $semanas = $this->semanasExigidas($genero, $anio);

        return [
            'semanas' => $semanas,
            'genero' => $genero === 'F' ? 'Femenino' : 'Masculino',
            'anio' => $anio,
            'sentencia' => $genero === 'F' && $anio >= 2025 ? 'Sentencia C-197 de 2023' : null,
            'nota' => $genero === 'F' && $anio >= 2025
                ? "Según Sentencia C-197 de 2023, las mujeres requieren {$semanas} semanas en {$anio}"
                : null
        ];
    }
}