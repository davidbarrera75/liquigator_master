<?php


namespace App\Service;


use DateInterval;
use DatePeriod;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Smalot\PdfParser\Parser;

class ExtractService
{

    const DIAS_LABORADOS = 360;
    const VALOR_ANIO = self::DIAS_LABORADOS / 7;
    const VALOR_MES = self::VALOR_ANIO / 12;
    private $em, $fondo, $file, $ipcService;

    public function __construct(EntityManagerInterface $em, IpcService $ipcService)
    {
        $this->em = $em;
        $this->ipcService = $ipcService;
    }

    public function setFondo($fondo)
    {
        $this->fondo = $fondo;
        return $this;
    }

    public function setFile($file)
    {
        $this->file = $file;
        return $this;
    }

    public function execute()
    {
        $arr = $this->extract($this->file);
        // Funcion por fondo de pension
        $fondo = $this->fondo;
        if (!method_exists($this, $fondo)) throw new Exception('No hay función para ' . $fondo);
        return $this->$fondo($arr);
    }

    private function extract($file): array
    {
        if (!file_exists($file)) throw new Exception('Archivo no encontrado');
        $parser = new Parser();
        $pdf = $parser->parseFile($file);
        $pages = $pdf->getPages();
        $i = 1;
        $arr = [];
        if ($this->fondo == 'skandia') {
            foreach ($pages as $page) {
                $x = $page->getTextArray();
                $arr = array_merge($arr, $x);
            }
        } elseif ($this->fondo == 'colpensiones') {
            foreach ($pages as $page) {
                $x = preg_split("/\r\n|\n|\r/", $page->getText());
                $arr = array_merge($arr, $x);
            }
        } else {
            foreach ($pages as $page) {
                $x = preg_split("/\r\n|\n|\r/", $page->getText());
                $arr[$i] = [];
                foreach ($x as $str) {
                    $y = preg_split("/\t/", trim($str, " "));
                    $arr[$i][] = $y;
                }
                $i++;
            }
        }
        return $arr;
    }

    private function colpensiones($file)
    {
        $return = [];
        //Trim all values of the array $file
        $file = array_map(function ($item) {
            return trim($item);
        }, $file);
        //Find first position of the array with the value [34]
        $pos_initial = array_search('[45]DíasCot.', $file);
        $pos_final = array_search('[58]DíasCot.', $file);
        //Get the array from the position [34] to [47]
        $file = array_slice($file, $pos_initial, $pos_final - $pos_initial + 1);
        //Get all items of the array $file with contains "$"
        $pos = (array_filter($file, function ($item) {
            return strpos($item, '$') !== false;
        }));
        $data = array_map(function ($item) {
            //Convert every tab to a space
            $item = preg_replace('/\t+/', '|', $item);
            //Split the string by the space
            return explode('|', $item);
        }, $pos);

        $extract = array_map(function ($item) {
            return [
                "fecha" => $item[3],
                "valor" => $item[4],
                "dias" => $item[count($item) - 1]
            ];
        }, $data);
        $info = [];
        $days_array = [];
        $days = 0;
        foreach ($extract as $item) {
            $rangos = $this->__rangos($item['fecha'], $item['fecha'], 'Ym');
            $int = (int)filter_var($item['valor'], FILTER_SANITIZE_NUMBER_INT);

            foreach ($rangos as $value) {
                if ($int === 0) continue;
                if (!isset($info[$value['format']])) {
                    $info[$value['format']] = 0;
                }
                if (!isset($days_array[$value['format']])) {
                    $days_array[$value['format']] = 0;
                }
                $days_array[$value['format']] += $int > 0 ? $item['dias'] : 0;
                $days_array[$value['format']] = min($days_array[$value['format']], 30);
                $info[$value['format']] += $int;
                $info[$value['format']] = min($info[$value['format']], $value['tope']);

            }
        }
        $days = array_sum($days_array);
        if (count($info) < 1) throw new Exception('No hay registros');
        return ['data' => $info, 'semanas' => $days / 7, 'dias' => $days, 'days_array' => $days_array];
    }

    public function __rangos($_start, $_end, $format = 'd/m/Y', $_days = false): array
    {
        $start = (DateTime::createFromFormat($format, $_start));
        $end = (DateTime::createFromFormat($format, $_end));
        //Days between start and end
        $days = $start->diff($end)->days;

        $interval = DateInterval::createFromDateString('1 month');
        $period = new DatePeriod(
            $start->modify('first day of this month'),
            $interval,
            $end->modify('first day of next month'));
        $arr = [];
        foreach ($period as $dt) {
            $arr[] = ['format' => $dt->format("Y-m"),
                'tope' => $this->ipcService->salarioMinimo($dt->format('Y'), true)
            ];
        }
        if ($_days) return ['return' => $arr, 'days' => $days];
        return $arr;
    }

    private function porvenir($file)
    {
        $largo = 4;
        $info = [];
        $start = [];
        foreach ($file as $item => $value) {
            foreach ($value as $piv) {
                if (!isset($piv[1])) continue;
                $array = explode(' ', $piv[1]);
                if (count($array) === $largo) {
                    if ($array[2] == '$') {
                        foreach ($this->__rangos($array[0], $array[1], 'm/Y') as $value) {
                            $int = (int)filter_var($array[3], FILTER_SANITIZE_NUMBER_INT);
                            if ($int === 0) continue;
                            if (!isset($info[$value['format']])) {
                                $info[$value['format']] = 0;
                            }
                            $info[$value['format']] += $int;
                            $info[$value['format']] = $info[$value['format']] >= $value['tope'] ? $value['tope'] : $info[$value['format']];
                            $start[$value['format']] = self::VALOR_MES;
                        }
                    }
                }
            }
        }
        if (count($info) < 1) throw new Exception('No hay registros');
        return ['data' => $info, 'semanas' => array_sum($start)];
    }

    private function porvenir_1($file)
    {
        $largo = 5;
        $info = [];
        $a = 0;
        foreach ($file as $item => $value) {
            foreach ($value as $piv) {
                if (!isset($piv[3])) continue;
                $array = explode(' ', $piv[2]);
                if (count($array) === $largo) {
                    if ($array[2] == '$') {
                        $a += (int)$array[4];
                        foreach ($this->__rangos($array[0], $array[1], 'm/Y') as $value) {
                            $int = (int)filter_var($array[3], FILTER_SANITIZE_NUMBER_INT);
                            if ($int === 0) continue;
                            if (!isset($info[$value['format']])) {
                                $info[$value['format']] = 0;
                            }
                            $info[$value['format']] += $int;
                            $info[$value['format']] = $info[$value['format']] >= $value['tope'] ? $value['tope'] : $info[$value['format']];

                        }
                    }
                }
            }
        }
        $total_anios = ($a / self::DIAS_LABORADOS) * self::VALOR_ANIO;

        if (count($info) < 1) throw new Exception('No hay registros');
        return ['data' => $info, 'semanas' => $total_anios];
    }

    private function colfondos($file)
    {

        $llave = "HISTORIA LABORAL FONDO DE PENSIONES OBLIGATORIAS";
        $llave2 = 'Colfondos Pensiones y Cesantias';
        $llave3 = 'Fondo De Pensiones Skandia';
        $coma = ',';
        $start = [];
        $info = [];
        foreach ($file as $item) {
            if ($item[0][0] != $llave) continue;

            foreach ($item as $position) {
                $t = $position[0];
                $pos = str_contains($t, $llave2);
                $pos2 = str_contains($t, $llave3);

                if ($pos) {
                    $t = trim(str_replace($llave2, '', $t));
                } else if ($pos2) {
                    $t = trim(str_replace($llave3, '', $t));
                } else {
                    continue;
                }
                $a = explode(' ', $t);
                $fecha = substr($a[0], 0, 6);
                $valor = $a[count($a) - 1];
                $perArray = str_split($valor);
                $findComma = array_search($coma, $perArray);
                $valorFinal = $this->__extractNumberColfondos($perArray, $findComma);

                foreach ($this->__rangos($fecha, $fecha, 'Ym') as $value) {
                    $int = $valorFinal;
                    if ($int === 0) continue;
                    if (!isset($info[$value['format']])) {
                        $info[$value['format']] = 0;
                    }
                    $info[$value['format']] += $int;
                    $info[$value['format']] = $info[$value['format']] >= $value['tope'] ? $value['tope'] : $info[$value['format']];
                    $start[$value['format']] = self::VALOR_MES;
                }

            }
        }
        if (count($info) < 1) throw new Exception('No hay registros');
        return ['data' => $info, 'semanas' => array_sum($start)];
    }

    private function __extractNumberColfondos(array $array, $pos)
    {
        if ($array[$pos] === ',') {
            $pos += 4;
            return $this->__extractNumberColfondos($array, $pos);
        }
        $array_num = array_slice($array, 0, $pos);
        $arr_comma = [','];
        $r = array_values(array_diff($array_num, $arr_comma));
        return implode('', $r);
    }

    private function proteccion($file)
    {
        // echo "<pre>";
        $size = 7;
        $_list = [];
        foreach ($file as $v) {
            foreach ($v as $item) {
                if (count($item) !== $size) continue;
                $_list[] = [$item[0], $item[1], $item[3]];
            }
        }
        $info = [];
        $start = [];
        $i = 0;
        foreach ($this->__recursive_array_replace(' ', '', $_list) as $array) {
            $i += $array[2];
            foreach ($this->__rangos($array[0], $array[0], 'Y/m') as $value) {
                $int = (int)filter_var($array[1], FILTER_SANITIZE_NUMBER_INT);
                if ($int === 0) continue;
                if (!isset($info[$value['format']])) {
                    $info[$value['format']] = 0;
                }
                $info[$value['format']] += $int;
                $info[$value['format']] = $info[$value['format']] >= $value['tope'] ? $value['tope'] : $info[$value['format']];
                $start[$value['format']] = self::VALOR_MES;
            }
        }
        $total_anios = ($i / self::DIAS_LABORADOS) * self::VALOR_ANIO;
        if (count($info) < 1) throw new Exception('No hay registros');
        return ['data' => $info, 'semanas' => $total_anios];

    }

    public function __recursive_array_replace($find, $replace, $array)
    {

        if (!is_array($array)) {
            return str_replace($find, $replace, $array);
        }

        $newArray = array();

        foreach ($array as $key => $value) {
            $newArray[$key] = $this->__recursive_array_replace($find, $replace, $value);
        }

        return $newArray;
    }

    private function skandia($file)
    {
        //Find the key with the word "Historia Laboral R�gimen de Ahorro Individual con Solidaridad"
        $llave = "Historia Laboral Regimen de Ahorro Individual con Solidaridad";
        $llave2 = "Resumen Historia Laboral Consolidada Sistema General de Pensiones";
        //Search $llave in $file
        $file = $this->removeAcents($file);
        $inicia = array_search($llave, $file);
        $finaliza = array_search($llave2, $file);
        //If $llave is not found, throw an exception
        if ($inicia === false) throw new Exception('Error en lectura de datos');
        //If $llave2 is not found, throw an exception
        if ($finaliza === false) throw new Exception('Error en lectura de datos');
        //Get the array from $inicia to $finaliza
        $file = array_slice($file, $inicia, $finaliza - $inicia);
        //Search all exact recurrencies of a string with the format "YYYYMM" and start with "20" or "19"
        preg_match_all('/(20|19)[0-9]{2}(0[1-9]|1[012])/', implode(' ', $file), $matches);
        //If there are no matches, throw an exception
        if (count($matches[0]) < 1) throw new Exception('No hay registros');
        //Get the array of matches
        $matches = $matches[0];
        //Get position of the matches in the array
        $posiciones = [];
        foreach ($matches as $match) {
            $posiciones[] = array_search($match, $file);
        }
        $data = [];
        foreach ($posiciones as $posicion) {
            $data[] = [
                'fecha' => (DateTime::createFromFormat('Ym', $file[$posicion]))->format('Y-m'),
                'valor' => (int)str_replace(['.00', ' ', '$', ','], '', $file[$posicion + 5]),
            ];
        }
        $return = [];
        $start = [];
        foreach ($data as $item) {
            foreach ($this->__rangos($item['fecha'], $item['fecha'], 'Y-m') as $value) {
                if (!isset($return[$value['format']])) {
                    $return[$value['format']] = 0;
                }
                $return[$value['format']] += $item['valor'];
                $return[$value['format']] = $return[$value['format']] >= $value['tope'] ? $value['tope'] : $return[$value['format']];
                $start[$value['format']] = self::VALOR_MES;
            }
        }
        return ['data' => $return, 'semanas' => array_sum($start)];
    }

    //Funcion para quitar tildes en un array
    private function removeAcents($array)
    {
        // preg_replace in array
        $array = array_map(function ($item) {
            return utf8_encode($item);
        }, $array);

        $search = array('á', 'é', 'í', 'ó', 'ú', 'Á', 'É', 'Í', 'Ó', 'Ú', 'ñ', 'Ñ', 'é');
        $replace = array('a', 'e', 'i', 'o', 'u', 'A', 'E', 'I', 'O', 'U', 'n', 'N');
        return str_replace($search, $replace, $array);
    }

}
