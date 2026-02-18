<?php

namespace App\Controller;

use App\Entity\Data;
use App\Entity\Information;
use App\Entity\Ipc;
use App\Entity\PDFReport;
use App\Entity\Proyeccion;
use App\Entity\User;
use App\Service\ExtractService;
use App\Service\FileUploader;
use App\Service\IpcService;
use App\Service\ClaudeService;
use App\Service\PdfPlumberService;
use App\Service\CrmService;
use App\Service\GotenbergService;
use DateInterval;
use DatePeriod;
use DateTime;
use DateTimeZone;
use Dompdf\Dompdf;
use Dompdf\Options;
use Exception;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\Shared\Html;
use PhpOffice\PhpWord\TemplateProcessor;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route ("/main")
 */
class MainController extends AbstractController
{
    const SEMANAS = 'SemanasBasicas';
    const TAZA_REEMPLAZO = 'TazaRemplazo';
    const INCREMENTO_TAZA = 'IncrementoTaza';
    const VARIACION_TAZA = 'VariacionTaza';
    const ADICIONAL = 'SemanasAdicionales';
    const FONDOS = 'Fondos';
    private $uploader, $ipcService, $logger, $extractService, $claudeService, $pdfPlumberService, $gotenbergService, $crmService;
    /**
     * @var false|string
     */
    private $year;
    private $projectDir;

    public function __construct(
        FileUploader $uploader,
        IpcService $ipcService,
        LoggerInterface $logger,
        ExtractService $extractService,
        ClaudeService $claudeService,
        PdfPlumberService $pdfPlumberService,
        \App\Service\GotenbergService $gotenbergService,
        CrmService $crmService,
        string $projectDir
    )
    {
        $this->uploader = $uploader;
        $this->ipcService = $ipcService;
        $this->logger = $logger;
        $this->extractService = $extractService;
        $this->claudeService = $claudeService;
        $this->pdfPlumberService = $pdfPlumberService;
        $this->gotenbergService = $gotenbergService;
        $this->crmService = $crmService;
        $this->projectDir = $projectDir;
        $this->year = date('Y');
    }

    /**
     * @Route("/", name="main")
     */
    public function index(): Response
    {

        return $this->render('main/main.html.twig');
    }

    /**
     * @Route("/informes", name="informes")
     */
    public function informes(): Response
    {

        return $this->render('main/informes-aneriores.html.twig');
    }

    /**
     * @Route("/crear-informe", name="crearInforme")
     */
    public function nuevo(): Response
    {
        $pensiones = $this->ipcService->catalogo(self::FONDOS, true);

        return $this->render('main/index.html.twig', [
            'pensiones' => $pensiones,

        ]);
    }

    /**
     * @Route("/datos/", name="datos", methods={"POST"})
     */
    public function datos(Request $request)
    {
        // Validar que hay sesión activa antes de crear registros
        if (!$this->getUser()) {
            $this->addFlash('warning', 'Por seguridad, su sesión se cerró automáticamente. Solo debe iniciar sesión de nuevo y podrá continuar sin problema.');
            return $this->redirectToRoute('app_login');
        }

        $em = $this->getDoctrine()->getManager();

        // Log para debugging
        $this->logger->info("datos() iniciado", [
            'has_file' => $request->files->has('file'),
            'file_count' => $request->files->count(),
            'options' => $request->get('options')
        ]);

        $rawFile = $request->files->get('file');

        $this->logger->info("DEBUG file", [
            'type' => gettype($rawFile),
            'is_object' => is_object($rawFile),
            'class' => is_object($rawFile) ? get_class($rawFile) : 'not object',
            'is_array' => is_array($rawFile),
            'empty' => empty($rawFile),
            'is_null' => is_null($rawFile)
        ]);

        if (is_array($rawFile)) {
            $this->logger->info("Archivo recibido como array", [
                'count' => count($rawFile),
                'keys' => array_keys($rawFile)
            ]);
        }

        $file = $this->normalizeUploadedFile($rawFile);

        if (!$file) {
            $this->logger->error("No se recibió ningún archivo en el request");
            $this->addFlash('warning', 'No se detectó ningún archivo. Por favor seleccione un PDF o Excel e intente de nuevo.');
            return $this->redirectToRoute('crearInforme');
        }

        $name = $request->get('fullname');
        $service = $this->uploader;
        $uploaded = $service->getTargetDirectory() . $service->upload($file);
        $fondo = $request->get('type');
        $skandia = ['skandia', 'skandia_1', 'skandia.1'];
        /* if ($request->get('options') == 'pdf') {
             if (in_array($fondo, $skandia)) {
                 $getFile = file_get_contents($uploaded);
                 $enco = base64_encode($getFile);
                 $response = file_get_contents('http://cotizador.today:33016/pdf2text/api/v1/extract', false, stream_context_create([
                     'http' => [
                         'method' => 'POST',
                         'header' => "Content-type: application/json",
                         'content' => json_encode(array('type' => $request->get('type'), 'base64' => $enco))
                     ]
                 ]));
                 $data = json_decode($response, 1);
             }
 //        print_r(($data));die;
         }*/

        $em->getConnection()->beginTransaction();
        try {
            $bog = new DateTimeZone('America/Bogota');
            if ($request->get('options') == 'pdf') {
                /*if (in_array($fondo, $skandia)) {
                    $fondo = $fondo == "skandia_1" ? "skandia" : $fondo;
                    $extract = $this->$fondo($data);
                } else {
                }*/
                $extract = $this->extract($uploaded, $fondo);
            }
            $birthDATE = Datetime::createFromFormat('Y-m-d', $request->get('birthdate'));
            $genero = $request->get('genero', 'M'); // Default masculino
            $info = new Information();
            $info->setCreatedAt(new DateTime('now', $bog));
            $info->setIdentification($request->get('identification'));
            $info->setFondo($fondo);
            $info->setFullName($name);
            $info->setUser($this->getUser());
            $info->setBirthdate($birthDATE);
            $info->setGenero($genero);
            $flag = 0;
            if ($request->get('options') == 'pdf') {
                // PdfPlumberService devuelve data como array asociativo: ["YYYY-MM" => salario]
                foreach ($extract['data'] as $item => $datum) {
                    $dias = isset($extract['days_array'][$item]) ? $extract['days_array'][$item] : 30;
                    $anio = explode('-', $item)[0];
                    $data_info = new Data();
                    $data_info->setPeriod($item);
                    $data_info->setVal($datum);
                    $data_info->setInfo($info);
                    $data_info->setDaysPeriod($dias);
                    // Guardar valor original del PDF y flag de tope aplicado
                    if (isset($extract['original_values'][$item])) {
                        $data_info->setIbcOriginal($extract['original_values'][$item]);
                    }
                    if (isset($extract['tope_aplicado_array'][$item])) {
                        $data_info->setTopeAplicado($extract['tope_aplicado_array'][$item]);
                    }
                    $em->persist($data_info);
                    $flag = $anio > $flag ? $anio : $flag;
                }
            } elseif ($request->get('options') == 'excel') {
                $extract = $this->excel_import($uploaded, $info);
                $flag = $extract['ultimo'];
            }

            // Si $flag es 0 (no se pudo determinar el año), usar el año actual
            if ($flag == 0) {
                $flag = (int)date('Y');
            }

            $info->setTotalWeeks($extract['semanas']);
            $info->setTotalWeeksOriginal($extract['semanas']);
            $info->setTotalDays($extract['dias'] ?? 0);
            $info->setCotizacionAnio($flag);
            $em->persist($info);
            $em->flush();
            $em->getConnection()->commit();

            // Enviar datos al CRM (no bloquea si falla)
            $this->crmService->enviarInforme($info);

            return $this->redirectToRoute('client', ['uniqid' => $info->getUniqId()]);

        } catch (Exception $e) {
            $em->getConnection()->rollBack();
            $this->logger->error("Error procesando archivo", [
                'error' => $e->getMessage(),
                'file' => $uploaded ?? 'unknown',
                'trace' => $e->getTraceAsString()
            ]);
            $this->addFlash('warning', 'No fue posible procesar el archivo en este momento. Por favor intente de nuevo. Si el problema persiste, verifique que el PDF no esté protegido con contraseña.');
            return $this->redirectToRoute('crearInforme');
        }


    }

    /**
     * @param $webPath
     * @param $fondo
     * @return mixed
     * @throws Exception
     */
    private function extract($webPath, $fondo)
    {
        // PRIORIDAD 1: Intentar con pdfplumber (Python)
        try {
            $this->logger->info("Usando pdfplumber para extracción de PDF", [
                'file' => $webPath,
                'fondo' => $fondo
            ]);

            $result = $this->pdfPlumberService->extractPdf($webPath, $fondo);

            if ($result['success']) {
                $this->logger->info("pdfplumber exitoso", [
                    'total_rows' => $result['total_rows'],
                    'semanas' => $result['semanas']
                ]);
                return $result;
            } else {
                $this->logger->warning("pdfplumber falló: " . $result['error']);
            }
        } catch (Exception $e) {
            $this->logger->warning("pdfplumber exception: " . $e->getMessage());
        }

        // PRIORIDAD 2: Intentar con Claude AI
        try {
            $this->logger->info("Usando Claude AI para extracción de PDF");

            $claudeData = $this->claudeService->extractPdfData($webPath, $fondo);
            return $this->transformClaudeResponse($claudeData, $fondo);

        } catch (\Throwable $e) {
            $this->logger->warning("Claude falló: " . $e->getMessage());
        }

        // PRIORIDAD 3: Usar servicio legacy (ExtractService)
        try {
            $this->logger->info("Usando ExtractService (legacy) para extracción");

            $extract = $this->extractService
                ->setFondo($fondo)
                ->setFile($webPath)
                ->execute();
            return $extract;
        } catch (Exception $e2) {
            $this->addFlash('warning', 'No fue posible leer los datos del PDF. Esto puede ocurrir si el archivo está protegido o tiene un formato diferente al esperado. Por favor intente con otro archivo.');
            throw new Exception($e2->getMessage());
        }
    }

    private function transformClaudeResponse(array $claudeData, string $fondo): array
    {
        $result = [
            'data' => [],
            'semanas' => 0,
            'dias' => 0
        ];

        if (isset($claudeData[0]['rows'])) {
            $rows = $claudeData[0]['rows'];

            switch (strtolower($fondo)) {
                case 'skandia':
                    foreach ($rows as $row) {
                        $dateObj = DateTime::createFromFormat('Ym', $row[0]);
                        if (!$dateObj) continue;

                        $period = $dateObj->format('Y-m');
                        $year = (int)$dateObj->format('Y');
                        $salario = (int)str_replace(['.', ',', ' ', '$'], '', $row[3]);

                        if (!isset($result['data'][$period])) {
                            $result['data'][$period] = 0;
                        }
                        $result['data'][$period] += $salario;

                        // Aplicar tope de 25 SMLMV
                        $tope = $this->ipcService->salarioMinimo($year, true);
                        $result['data'][$period] = min($result['data'][$period], $tope);

                        $result['semanas'] += ExtractService::VALOR_MES;
                    }
                    break;

                case 'colpensiones':
                    foreach ($rows as $row) {
                        // Claude devuelve: [null,null,null,null,null,null,"202501",7981912]
                        // $row[6] = Período en formato YYYYMM (202501)
                        // $row[7] = IBC como número entero (7981912)

                        if (!isset($row[6]) || !isset($row[7])) continue;

                        $periodoYYYYMM = $row[6];
                        $ibc = (int)str_replace(['.', ',', ' ', '$'], '', $row[7]);

                        // Convertir YYYYMM a Y-m (202501 -> 2025-01)
                        $dateObj = DateTime::createFromFormat('Ym', $periodoYYYYMM);
                        if (!$dateObj) continue;

                        $period = $dateObj->format('Y-m');
                        $year = (int)$dateObj->format('Y');

                        if (!isset($result['data'][$period])) {
                            $result['data'][$period] = 0;
                        }
                        $result['data'][$period] += $ibc;

                        // Aplicar tope de 25 SMLMV
                        $tope = $this->ipcService->salarioMinimo($year, true);
                        $result['data'][$period] = min($result['data'][$period], $tope);

                        // Para Colpensiones, asumimos 30 días por mes
                        $result['semanas'] += ExtractService::VALOR_MES;
                    }
                    break;

                case 'porvenir':
                    foreach ($rows as $row) {
                        $periodFrom = DateTime::createFromFormat('m/Y', $row[3]);
                        if ($periodFrom) {
                            $period = $periodFrom->format('Y-m');
                            $year = (int)$periodFrom->format('Y');
                            $salario = (int)str_replace(['.', ',', ' ', '$'], '', $row[5]);

                            if (!isset($result['data'][$period])) {
                                $result['data'][$period] = 0;
                            }
                            $result['data'][$period] += $salario;

                            // Aplicar tope de 25 SMLMV
                            $tope = $this->ipcService->salarioMinimo($year, true);
                            $result['data'][$period] = min($result['data'][$period], $tope);

                            $result['semanas'] += ExtractService::VALOR_MES;
                        }
                    }
                    break;

                case 'colfondos':
                    foreach ($rows as $row) {
                        $dateObj = DateTime::createFromFormat('Ym', $row[1]);
                        if (!$dateObj) continue;

                        $period = $dateObj->format('Y-m');
                        $year = (int)$dateObj->format('Y');
                        $salario = (int)str_replace(['.', ',', ' ', '$'], '', $row[4]);

                        if (!isset($result['data'][$period])) {
                            $result['data'][$period] = 0;
                        }
                        $result['data'][$period] += $salario;

                        // Aplicar tope de 25 SMLMV
                        $tope = $this->ipcService->salarioMinimo($year, true);
                        $result['data'][$period] = min($result['data'][$period], $tope);

                        $result['semanas'] += ExtractService::VALOR_MES;
                    }
                    break;

                case 'proteccion':
                    foreach ($rows as $row) {
                        $dateObj = DateTime::createFromFormat('Y/m', $row[2]);
                        if (!$dateObj) continue;

                        $period = $dateObj->format('Y-m');
                        $year = (int)$dateObj->format('Y');
                        $salario = (int)str_replace(['.', ',', ' ', '$'], '', $row[3]);

                        if (!isset($result['data'][$period])) {
                            $result['data'][$period] = 0;
                        }
                        $result['data'][$period] += $salario;

                        // Aplicar tope de 25 SMLMV
                        $tope = $this->ipcService->salarioMinimo($year, true);
                        $result['data'][$period] = min($result['data'][$period], $tope);

                        $result['semanas'] += ExtractService::VALOR_MES;
                    }
                    break;
            }
        }

        return $result;
    }

    private function excel_import($uploaded, Information $info)
    {
        $em = $this->getDoctrine()->getManager();
        $objPHPExcel = IOFactory::load($uploaded);
        $objWorksheet = $objPHPExcel->setActiveSheetIndex(0);
        $highestRow = $objWorksheet->getHighestRow();
        $highestColumn = $objWorksheet->getHighestColumn();
        $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);
        $headingsArray = $objWorksheet->rangeToArray('A1:' . $highestColumn . '1', null, true, true, true);
        $headingsArray = $headingsArray[1];
        $r = -1;
        $namedDataArray = array();
        for ($row = 2; $row <= $highestRow; ++$row) {
            $dataRow = $objWorksheet->rangeToArray('A' . $row . ':' . $highestColumn . $row, null, true, false, true);
            if ((isset($dataRow[$row]['A'])) && ($dataRow[$row]['A'] > '')) {
                ++$r;
                foreach ($headingsArray as $columnKey => $columnHeading) {
                    $namedDataArray[$r][$columnHeading] = $dataRow[$row][$columnKey];
                } //endforeach
            } //endif
        }
        $semanas_conteo = 0;
        $last_year = 0;
        $data = $info->getData();
        foreach ($data as $datum) {
            $info->removeData($datum);
            $em->persist($datum);
            $em->remove($datum);
        }

        foreach ($namedDataArray as $item) {
            if (!is_int($item['AÑO'])) throw new Exception('El año no es numérico');
            if (!is_int($item['MES'])) throw new Exception('El mes no es numérico');
            if (!is_int($item['SALARIO'])) throw new Exception('El salario no es numérico');
            $set_period = $item['AÑO'] . '-' . str_pad($item['MES'], 2, "0", STR_PAD_LEFT);
            $rango = ($this->extractService->__rangos($set_period, $set_period, 'Y-m'))[0];
            $d = new Data();
            $d->setInfo($info)
                ->setPeriod($rango['format'])
                ->setVal($item['SALARIO'] >= $rango['tope'] ? $rango['tope'] : $item['SALARIO'])
                ->setDaysPeriod(30);

            $em->persist($d);
            $last_year = $last_year > $item['AÑO'] ? $last_year : $item['AÑO'];
            $semanas_conteo += ExtractService::VALOR_MES;
        }

        return [
            'ultimo' => $last_year,
            'semanas' => $semanas_conteo
        ];
    }

    /**
     * @Route ("/{uniqid}/proyeccion", name="proyeccion")
     */
    public function proyeccion(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $info = $em->getRepository(Information::class)->findOneBy(['uniq_id' => $request->get('uniqid')]);
        $salario = $this->ipcService->salarioMinimo($this->year);
        $last = $em->getRepository(Data::class)->getLast($info);

        return $this->render('main/client/proyeccion.html.twig', [
                'info' => $info,
                'anio' => $info->getCotizacionAnio(),
                'salario' => $salario,
                'tope' => $this->ipcService->salarioMinimo($info->getCotizacionAnio(), true),
                'lastRecord' => $last,
                'ad' => $info->getResume(),


            ]
        );
    }

    /**
     * @Route ("/{uniqid}/historico", name="historico")
     */
    public function historico(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $info = $em->getRepository(Information::class)->findOneBy(['uniq_id' => $request->get('uniqid')]);
        return $this->render('main/client/historico.html.twig', [
                'info' => $info,
            ]
        );
    }

    /**
     * @Route ("/{uniqid}/valores-extraidos", name="valores-extraidos")
     */
    public function valoresExtraidos(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $info = $em->getRepository(Information::class)->findOneBy(['uniq_id' => $request->get('uniqid')]);

        // Obtener todos los topes de salario mínimo
        $topes = $em->getRepository(\App\Entity\SalarioMinimo::class)->findAll();
        $topesArray = [];
        foreach ($topes as $tope) {
            $topesArray[$tope->getAnio()] = $tope->getTope();
        }

        return $this->render('main/client/valores-extraidos.html.twig', [
                'info' => $info,
                'topes' => $topesArray,
            ]
        );
    }

    /**
     * @Route ("/{uniqid}/importar-excel", name="importar-excel")
     */
    public function importarExcel(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $info = $em->getRepository(Information::class)->findOneBy(['uniq_id' => $request->get('uniqid')]);
        return $this->render('main/client/importar-excel.html.twig', [
                'info' => $info,
            ]
        );
    }

    /**
     * @Route ("/{uniqid}/resumen-completo", name="resumen-completo")
     */
    public function resumenCompleto(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $info = $em->getRepository(Information::class)->findOneBy(['uniq_id' => $request->get('uniqid')]);
        $data_completo = $em->getRepository(Data::class)->getInfoListed($info->getId(), $info->getCotizacionAnio());
        $table = $this->infoLiquidacion($data_completo, $info->getCotizacionAnio());
        // Sentencia C-197 de 2023: semanas según género (usar año de cotización, no año actual)
        $anioCotizacion = (int)$info->getCotizacionAnio();
        $semanas = $this->ipcService->semanasExigidas($info->getGenero(), $anioCotizacion);
        $salario = $this->ipcService->salarioMinimo($anioCotizacion);
        $last = $em->getRepository(Data::class)->getLast($info);
        $after = DateTime::createFromFormat('Y-m', $last->getPeriod());
        $after->modify('+1 month');
        $ad['Semanas Básicas'] = $semanas;
        $ad['Sentencia C-197'] = ($info->isMujer() && $anioCotizacion >= 2026) ? 'Aplica' : 'No aplica';
        $ad['Semanas Adicionales'] = 0;
        $ad['Porcentaje Adicional'] = 0;
        if ($info->getTotalWeeks() > $semanas) {
            $ad['Semanas Adicionales'] = $info->getTotalWeeks() - $semanas;
            $ad['Porcentaje Adicional'] = ((int)($ad['Semanas Adicionales'] / $this->ipcService->catalogo(self::ADICIONAL))) * $this->ipcService->catalogo(self::INCREMENTO_TAZA);
        }
        $ad['IBL'] = $table['resume']['total'] / $table['resume']['meses'];
        $ad['S'] = $ad['IBL'] / $salario;
        $ad['R'] = $this->ipcService->catalogo(self::TAZA_REEMPLAZO) - ($ad['S'] * $this->ipcService->catalogo(self::VARIACION_TAZA));
        $ad['X'] = $ad['R'] + $ad['Porcentaje Adicional'];
        $ad['R1'] = $ad['R'] * $ad['IBL'];
        $ad['R2'] = $ad['X'] * $ad['IBL'];


        $ad['IBL'] = '$&nbsp;' . number_format($ad['IBL'], 2);
        $ad['S'] = number_format($ad['S'], 2);
        $ad['R1'] = '$&nbsp;' . number_format($ad['R1'], 2);
        $ad['R2'] = '$&nbsp;' . number_format($ad['R2'], 2);
        $ad['R'] = number_format($ad['R'] * 100, 2) . '%';
        $ad['X'] = number_format($ad['X'] * 100, 2) . '%';
        $ad['Porcentaje Adicional'] = number_format($ad['Porcentaje Adicional'] * 100, 2) . '%';

        return $this->render('main/client/resumen-completo.html.twig', [
                'info' => $info,
                'data_complete' => $table,
                'ad' => $ad,
            ]
        );
    }

    private function getIpcSimple(int $year): float
    {
        $em = $this->getDoctrine()->getManager();
        $ipcEntity = $em->getRepository(Ipc::class)->findOneBy(['anio' => $year]);
        return $ipcEntity ? (float)$ipcEntity->getIpc() : 1.0;
    }

    /**
     * Formatea una fecha YYYY-MM a formato legible (Ej: "Febrero 2026")
     */
    private function formatearMesAnio(string $fecha): string
    {
        $meses = [
            '01' => 'Enero', '02' => 'Febrero', '03' => 'Marzo',
            '04' => 'Abril', '05' => 'Mayo', '06' => 'Junio',
            '07' => 'Julio', '08' => 'Agosto', '09' => 'Septiembre',
            '10' => 'Octubre', '11' => 'Noviembre', '12' => 'Diciembre'
        ];
        $partes = explode('-', $fecha);
        if (count($partes) === 2) {
            $anio = $partes[0];
            $mes = $partes[1];
            return ($meses[$mes] ?? $mes) . ' ' . $anio;
        }
        return $fecha;
    }

    private function infoLiquidacion(array $data, $year = null)
    {
        $year = $year == null ? $this->year : $year;
        $ipc = $this->ipc($year);
        $r = [];
        $r['content'] = [];
        $r['resume'] = ['meses' => 0, 'total' => 0];
        foreach ($data as $datum) {
            $period = $datum['period'] > $year ? $year : $datum['period'];
            $act_1 = $datum['val'] * $ipc[$period];
            $act_2 = $act_1 * $datum['conteo'];
            $r['content'][] = [
                'period' => $datum['period'],
                'val' => $datum['val'],
                'conteo' => $datum['conteo'],
                'ipc' => $ipc[$period],
                'ipc_simple' => ($period == $year) ? 1.0 : $this->getIpcSimple($period),
                'actualizado' => $act_1,
                'porMeses' => $act_2
            ];
            $r['resume']['total'] += $act_2;
            $r['resume']['meses'] += $datum['conteo'];
        }
        return $r;

    }

    private function ipc(int $max): array
    {
        $ipc = [];
        for ($i = 1970; $i <= $max; $i++) {
            $ipc[$i] = $this->ipcService->calculate($i, $max);
        }
        return $ipc;
    }

    /**
     * @Route ("/{uniqid}/reporte-mensual", name="reporte-mensual")
     */
    public function reporteMensual(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $info = $em->getRepository(Information::class)->findOneBy(['uniq_id' => $request->get('uniqid')]);

//       Informe_mensual
        $informe_mensual = $em->getRepository(Data::class)->findBy(['info' => $info]);
        //Extract uniques year from $informe_mensual and put it in an array
        $years = array_unique(array_map(function ($item) {
            return $item->getFormattedPeriod()->format('Y');
        }, $informe_mensual));
        $dias = (array_map(function ($item) {
            return $item->getDaysPeriod();
        }, $informe_mensual));
        //Sum all values of $dias
        $dias = array_sum($dias);
        return $this->render('main/client/reporte-mensual.html.twig', [
                'info' => $info,
                'mensual' => $informe_mensual,
                'years' => $years,
                'new_title' => 'REPORTE MENSUAL',
                'dias' => $dias
            ]
        );
    }

    /**
     * @Route ("/{uniqid}/resumen", name="client")
     */
    public function client(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $info = $em->getRepository(Information::class)->findOneBy(['uniq_id' => $request->get('uniqid')]);

        // Validación: verificar que el registro existe
        if (!$info) {
            throw $this->createNotFoundException('Informe no encontrado. Verifique el ID proporcionado.');
        }

        // Validación: verificar que tiene usuario asignado
        if (!$info->getUser()) {
            $this->addFlash('warning', 'Este informe necesita ser generado nuevamente. No se preocupe, solo cargue el PDF de nuevo y quedará listo.');
            return $this->redirectToRoute('crearInforme');
        }

        // Sentencia C-197 de 2023: semanas según género (usar año de cotización, no año actual)
        $anioCotizacion = (int)$info->getCotizacionAnio();
        $semanas = $this->ipcService->semanasExigidas($info->getGenero(), $anioCotizacion);

        $v = $this->verifyUser($info->getUser());
        if ($v) return $v;
        $data = $em->getRepository(Data::class)->getInfoListed($info->getId(), $info->getCotizacionAnio(), 120);
        $table = $this->infoLiquidacion($data, $info->getCotizacionAnio());

        $salario = $this->ipcService->salarioMinimo($info->getCotizacionAnio());
        $last = $em->getRepository(Data::class)->getLast($info);

        if ($last === null) {
            $this->addFlash('warning', 'No se encontraron datos de cotización en este informe. No se preocupe, solo cargue el PDF nuevamente y quedará listo.');
            return $this->redirectToRoute('crearInforme');
        }

        $after = DateTime::createFromFormat('Y-m', $last->getPeriod());
        $after->modify('+1 month');
        $ad['Semanas Básicas'] = $semanas;
        $ad['Sentencia C-197'] = ($info->isMujer() && $anioCotizacion >= 2026) ? 'Aplica' : 'No aplica';
        $ad['Días Calculados'] = $info->getTotalDays();
        $ad['Semanas Adicionales'] = 0;
        $ad['Porcentaje Adicional'] = 0;
        if ($info->getTotalWeeks() > $semanas) {
            $ad['Semanas Adicionales'] = $info->getTotalWeeks() - $semanas;
            $ad['Porcentaje Adicional'] = ((int)($ad['Semanas Adicionales'] / $this->ipcService->catalogo(self::ADICIONAL))) * $this->ipcService->catalogo(self::INCREMENTO_TAZA);
        }
        $ad['IBL'] = $table['resume']['total'] / $table['resume']['meses'];
        $ad['S'] = $ad['IBL'] / $salario;
        $ad['R'] = $this->ipcService->catalogo(self::TAZA_REEMPLAZO) - ($ad['S'] * $this->ipcService->catalogo(self::VARIACION_TAZA));
        $ad['X'] = $ad['R'] + $ad['Porcentaje Adicional'];
        $ad['R1'] = $ad['R'] * $ad['IBL'];
        $ad['R2'] = $ad['X'] * $ad['IBL'];


        $ad['IBL'] = '$&nbsp;' . number_format($ad['IBL'], 2);
        $ad['S'] = number_format($ad['S'], 2);
        $ad['R1'] = '$&nbsp;' . number_format($ad['R1'], 2);
        $ad['R2'] = '$&nbsp;' . number_format($ad['R2'], 2);
        $ad['R'] = number_format($ad['R'] * 100, 2) . '%';
        $ad['X'] = number_format($ad['X'] * 100, 2) . '%';
        $ad['Porcentaje Adicional'] = number_format($ad['Porcentaje Adicional'] * 100, 2) . '%';

        $info->setResume($ad);
        $em->persist($info);
        $em->flush();

        return $this->render('main/client.html.twig', [
            'info' => $info,
            'data' => $table,
            'ad' => $ad,
            'salario' => $salario,
            'tope' => $this->ipcService->salarioMinimo($info->getCotizacionAnio(), true),
            'anio' => $info->getCotizacionAnio(),
            'last' => $after,
            'lastRecord' => $last,
        ]);
    }

    private function verifyUser(User $user)
    {
        $r = false;
        $currentUser = $this->getUser();

        // Verificar si hay un usuario logueado
        if (!$currentUser) {
            $this->addFlash('warning', 'Por seguridad, su sesión se cerró automáticamente. Inicie sesión de nuevo para continuar.');
            $r = $this->redirectToRoute('app_login');
            return $r;
        }

        // Verificar si el usuario logueado tiene permiso
        if ($user->getUsername() !== $currentUser->getUsername()) {
            $this->addFlash('warning', 'Este informe pertenece a otro usuario. Si cree que es un error, por favor contacte al administrador.');
            $r = $this->redirectToRoute('main');
        }
        return $r;
    }

    /**
     * @Route ("/client/{uniqid}/week", name="client_weeks_update", methods={"POST"})
     */
    public function weekUpdate(Request $request)
    {
        if (is_numeric($request->get('value'))) {
            $em = $this->getDoctrine()->getManager();
            $info = $em->getRepository(Information::class)->findOneBy(['uniq_id' => $request->get('uniqid')]);
            $info->setTotalWeeks($request->get('value'));
            $em->persist($info);
            $em->flush();
            $this->addFlash('success', 'Semanas Actualizadas');
        } else {
            $this->addFlash('warning', 'El valor de semanas debe ser un número. Por favor verifique e intente de nuevo.');
        }


        return $this->redirectToRoute('client', ['uniqid' => $info->getUniqId()]);

    }

    /**
     * @Route("/client/{uniqid}/year", name="client_year_update", methods={"POST"})
     */
    public function yearUpdate(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $info = $em->getRepository(Information::class)->findOneBy(['uniq_id' => $request->get('uniqid')]);
        $info->setCotizacionAnio($request->get('value'));
        $em->persist($info);
        $em->flush();
        return $this->redirectToRoute('client', ['uniqid' => $info->getUniqId()]);
    }

    /**
     * @Route("/client/{uniqid}/genero", name="client_genero_update", methods={"POST"})
     */
    public function generoUpdate(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $info = $em->getRepository(Information::class)->findOneBy(['uniq_id' => $request->get('uniqid')]);
        $genero = $request->get('genero');

        if (in_array($genero, ['M', 'F'])) {
            $info->setGenero($genero);
            $em->persist($info);
            $em->flush();

            $semanasExigidas = $this->ipcService->semanasExigidas($genero, (int)$info->getCotizacionAnio());
            $this->addFlash('success', 'Género actualizado. Semanas exigidas: ' . $semanasExigidas);
        } else {
            $this->addFlash('error', 'Género no válido');
        }

        return $this->redirectToRoute('client', ['uniqid' => $info->getUniqId()]);
    }

    /**
     * @Route("/pdf/{id}", name="report_pdf", methods={"POST"})
     */
    public function newReport(Information $information, Request $request)
    {
        $rendererName = Settings::PDF_RENDERER_DOMPDF;
        $rendererLibraryPath = realpath('../vendor/dompdf/dompdf');
        Settings::setPdfRenderer($rendererName, $rendererLibraryPath);
        setlocale(LC_ALL, "es_ES");
        ini_set('max_execution_time', -1);
        $em = $this->getDoctrine()->getManager();
        $em->getConnection()->beginTransaction();
        try {
            $information->setConclusiones($request->get('conclusiones'));
            $em->persist($information);
            $last_record = ($em->getRepository(Data::class)->findOneBy(['info' => $information], ['period' => 'DESC']))->getFormattedPeriod();

            $plantilla = $this->getParameter('kernel.project_dir') . '/public/files/informeLiquigator.docx';
            $tipo = "InformePension";
            if ($request->get('tipo_reporte') == 'preliminar') {
                $plantilla = $this->getParameter('kernel.project_dir') . '/public/files/informeLiquigatorPreliminar.docx';
                $tipo = "InformePreliminar";
            }

            // Verificar que la plantilla existe
            if (!file_exists($plantilla)) {
                throw new Exception("Plantilla no encontrada: $plantilla");
            }

            $this->logger->info("Generando documento Word", [
                'plantilla' => $plantilla,
                'tipo' => $tipo,
                'cliente' => $information->getIdentification()
            ]);

            $templateProcessor = new TemplateProcessor($plantilla);

            $resume = $information->getResume();
            $vars = [
                'nombre' => $information->getFullName(),
                'documento' => $information->getIdentification(),
                'fecha' => $information->getBirthdate()->format('d M Y'),
                'edad' => $information->getEdad(),
                'fondo' => strtoupper($information->getFondo()),
                'cot' => $last_record->format('m/Y'),
                'semanas' => $information->getTotalWeeks(),
                'liquidacion' => $information->getCotizacionAnio(),
                'totalSemanas' => $information->getTotalWeeks(),
                'semanasBasicas' => $resume['Semanas Básicas'],
                'semanasAdicionales' => $resume['Semanas Adicionales'],
                'porcentajeAdicional' => $resume['Porcentaje Adicional'],
                'ibl' => str_replace('&nbsp;', ' ', $resume['IBL']),
                's' => $resume['S'],
                'r' => $resume['R'],
                'totalPorcentaje' => $resume['X'],
                'pensionBasica' => str_replace('&nbsp;', ' ', $resume['R1']),
                'pensionTotal' => str_replace('&nbsp;', ' ', $resume['R2']),
            ];

            $templateProcessor->setValues($vars);

            // Convertir tabla HTML a tabla Word nativa
            $tablaDatos = $request->get('tabla_datos');
            if (empty($tablaDatos)) {
                $this->logger->warning("tabla_datos está vacía");
            }
            $this->htmlTableToWordTable($templateProcessor, 'reporte', $tablaDatos);

            // Convertir HTML a texto plano para conclusiones con saltos de línea de Word
            $conclusiones_limpias = strip_tags(str_replace(['<p>', '</p>', '<br>', '<br/>', '<br />'], "\n", $information->getConclusiones()));
            $conclusiones_limpias = $this->escapeWordText($conclusiones_limpias);
            $templateProcessor->setValue('conclusiones', $conclusiones_limpias);

            $publicDirectory = $this->container->get('parameter_bag')->get('reports');

            // Verificar que el directorio de reportes existe y tiene permisos
            if (!is_dir($publicDirectory)) {
                throw new Exception("Directorio de reportes no existe: $publicDirectory");
            }
            if (!is_writable($publicDirectory)) {
                throw new Exception("Directorio de reportes sin permisos de escritura: $publicDirectory");
            }

            $name = $tipo . '-' . $information->getIdentification() . '-' . uniqid() . '.docx';
            $fullFilePath = $publicDirectory . $name;

            $this->logger->info("Guardando documento Word", ['path' => $fullFilePath]);
            $templateProcessor->saveAs($fullFilePath);

            // Verificar que el archivo se creó correctamente
            if (!file_exists($fullFilePath)) {
                throw new Exception("El documento no se pudo crear: $fullFilePath");
            }

            $fileSize = filesize($fullFilePath);
            if ($fileSize < 1000) {
                $this->logger->warning("Documento Word muy pequeño", ['size' => $fileSize, 'path' => $fullFilePath]);
            }
            $bog = new DateTimeZone('America/Bogota');
            $report = new PDFReport();
            $report->setCreatedAt(new DateTime('now', $bog));
            $report->setName($name);
            $report->setFullPath($fullFilePath);
            $report->setInformation($information);
            $em->persist($report);
            $em->flush();
            $em->getConnection()->commit();
            $this->addFlash('success', 'Documento generado');
        } catch (Exception $e) {
            $em->getConnection()->rollBack();
            $this->addFlash('error', $e->getMessage());
        }
        return $this->redirectToRoute('client', ['uniqid' => $information->getUniqId()]);
    }


    public function report(Information $information, Request $request)
    {
        setlocale(LC_ALL, "es_ES");
        ini_set('max_execution_time', -1);
        $em = $this->getDoctrine()->getManager();
        $em->getConnection()->beginTransaction();
        try {
            $information->setConclusiones($request->get('conclusiones'));
            $em->persist($information);
            $options = new Options();
            $options->set('defaultfont', 'Cambria, Georgia, serif');
            $options->setIsRemoteEnabled(true);
            $salario = $this->ipcService->salarioMinimo($this->year);
            $last_record = ($em->getRepository(Data::class)->findOneBy(['info' => $information], ['period' => 'DESC']))->getFormattedPeriod();

            $header = $this->imageBase64('img/membrete/membretecabezera-02.png');
            $footer = $this->imageBase64('img/membrete/membretepie-02.png');
            $initialImage = $this->imageBase64('img/membrete/foto.jpg');
            $elaborado_por = $this->imageBase64('img/membrete/elaborado_por.jpeg');

            // Usar Gotenberg en lugar de DomPDF
            $view = 'main/reportPDF.html.twig';
            $report_name = 'InformePension';
            if ($request->get('tipo_reporte') == 'preliminar') {
                $report_name = 'InformePreliminar';
                $view = 'main/reportPreliminar.html.twig';
            }
            $html = $this->renderView($view, [
                'info' => $information,
                'smmlv' => $salario,
                'conclusiones' => $request->get('conclusiones'),
                'reporte' => $request->get('tabla_datos'),
                'header' => $header,
                'footer' => $footer,
                'imagen_initial' => $initialImage,
                'last_record' => $last_record,
                'elaborado_por' => $elaborado_por
            ]);
            
            // Generar PDF con Gotenberg
            $output = $this->gotenbergService->convertHtmlToPdf($html);
            $publicDirectory = $this->container->get('parameter_bag')->get('reports');
            $name = $report_name . "-" . uniqid($information->getIdentification()) . ".pdf";
            $pdfFilepath = $publicDirectory . $name;
            file_put_contents($pdfFilepath, $output);

            $bog = new DateTimeZone('America/Bogota');
            $report = new PDFReport();
            $report->setCreatedAt(new DateTime('now', $bog));
            $report->setName($name);
            $report->setFullPath($pdfFilepath);
            $report->setInformation($information);
            $em->persist($report);
            $em->flush();
            $em->getConnection()->commit();
            $this->addFlash('success', 'PDF generado');
        } catch (Exception $e) {
            $em->getConnection()->rollBack();
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('client', ['uniqid' => $information->getUniqId()]);
    }

    private function imageBase64($asset)
    {
        $path = $this->container->get('parameter_bag')->get('kernel.project_dir') . '/public/' . $asset;
        $type = pathinfo($path, PATHINFO_EXTENSION);
        $data = file_get_contents($path);
        return 'data:image/' . $type . ';base64,' . base64_encode($data);
    }

    /**
     * @Route("/proyeccion-pdf/{id}", name="proyeccion_pdf", methods={"GET"})
     */
    public function proyeccionPDF(Request $request, Proyeccion $proyeccion)
    {
        $em = $this->getDoctrine()->getManager();
        setlocale(LC_ALL, "es_ES");
        ini_set('max_execution_time', -1);
        $salario = $this->ipcService->salarioMinimo($this->year);
        $options = new Options();
        $options->setIsRemoteEnabled(true);
        $dompdf = new Dompdf($options);
        $last_record = ($em->getRepository(Data::class)->findOneBy(['info' => $proyeccion->getInformation()], ['period' => 'DESC']))->getFormattedPeriod();

        $html = $this->renderView('main/reportProyecciones.html.twig', [
            'info' => $proyeccion->getInformation(),
            'smmlv' => $salario,
            'reporte' => $proyeccion->getJsonData(),
            'last_record' => $last_record,
            'proyeccion' => $proyeccion
        ]);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('Letter', 'portrait');
        $dompdf->render();
        $dompdf->stream("dompdf_out.pdf", array("Attachment" => false));
        exit;
    }

    /**
     * @Route("/remove/{id}", name="information_remove", methods={"DELETE"})
     */
    public function eliminarReporte(Information $info)
    {
        $em = $this->getDoctrine()->getManager();
        $em->remove($info);
        $em->flush();
        $this->addFlash('success', 'Reporte Eliminado');
        return new JsonResponse(['code' => true, 'message' => 'Reporte Eliminado']);
    }

    /**
     * @Route("/report/{id}", name="report_download")
     */
    public function reportDownload(PDFReport $PDFReport)
    {
        if ($v = $this->verifyUser($PDFReport->getInformation()->getUser())) return $v;
        $response = new BinaryFileResponse($PDFReport->getFullPath());
        // Detectar Content-Type según extensión del archivo
        $ext = strtolower(pathinfo($PDFReport->getName(), PATHINFO_EXTENSION));
        $mimeTypes = [
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'pdf' => 'application/pdf',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ];
        if (isset($mimeTypes[$ext])) {
            $response->headers->set('Content-Type', $mimeTypes[$ext]);
        }
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $PDFReport->getName());
        return $response;
    }

    /**
     * @Route("/client/proyection/", name="proyection_name", methods={"POST"})
     * @throws Exception
     */
    public function proyection(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $req = $request->request->all();
        $ipc = $em->getRepository(Ipc::class)->max();
        $info = $em->getRepository(Information::class)->findOneBy(['uniq_id' => $req['client']]);
        // Sentencia C-197 de 2023: semanas según género (usar año de cotización)
        $anioCotizacion = (int)$info->getCotizacionAnio();
        $semanas = $this->ipcService->semanasExigidas($info->getGenero(), $anioCotizacion);
        $data = [];
        $salario = $this->ipcService->salarioMinimo($anioCotizacion);
        $arr = [];
        $total_meses_proyeccion = 0;

        // Variables para guardar las fechas de la proyección
        $fechaInicialProyeccion = null;
        $fechaFinalProyeccion = null;

        foreach ($req['pos'] as $pos) {
            // Calcular meses desde las fechas si están disponibles
            $meses = isset($pos['meses']) ? (int)$pos['meses'] : 0;

            if (isset($pos['fechaDesde']) && isset($pos['fechaHasta']) && !empty($pos['fechaDesde']) && !empty($pos['fechaHasta'])) {
                $fechaDesde = new DateTime($pos['fechaDesde'] . '-01');
                $fechaHasta = new DateTime($pos['fechaHasta'] . '-01');

                // Calcular meses entre las fechas (Feb 2026 a Feb 2027 = 12 meses)
                $interval = $fechaDesde->diff($fechaHasta);
                $meses = ($interval->y * 12) + $interval->m;
                // Si es el mismo mes, al menos 1 mes
                if ($meses === 0) {
                    $meses = 1;
                }

                // Guardar primera fecha inicial y última fecha final
                if ($fechaInicialProyeccion === null || $fechaDesde < $fechaInicialProyeccion) {
                    $fechaInicialProyeccion = $fechaDesde;
                }
                if ($fechaFinalProyeccion === null || $fechaHasta > $fechaFinalProyeccion) {
                    $fechaFinalProyeccion = $fechaHasta;
                }
            }

            $total_meses_proyeccion += $meses;
            $salariopos = filter_var($pos['salario'], FILTER_SANITIZE_NUMBER_INT);
            $arr[] = [
                'val' => $salariopos,
                'salario' => number_format($salariopos, 0, ',', '.'),
                'period' => $ipc->getAnio(),
                'conteo' => $meses,
                'meses' => $meses,
                'fechaDesde' => isset($pos['fechaDesde']) ? $this->formatearMesAnio($pos['fechaDesde']) : null,
                'fechaHasta' => isset($pos['fechaHasta']) ? $this->formatearMesAnio($pos['fechaHasta']) : null
            ];
        }
        $semanas_ = (360 / 7) / 12;
        $semanas_dif = $total_meses_proyeccion * $semanas_;
        $data = $em->getRepository(Data::class)->getInfoListed(
            $info->getId(),
            $ipc->getAnio(),
            $total_meses_proyeccion > 120 ? 120 : (120 - $total_meses_proyeccion)
        );

        $data = array_merge($data, $arr);
        $table = $this->infoLiquidacion($data, $ipc->getAnio());
        $adicional = 0;
        $porcentaje_adicional = 0;
        $semanas_adicionales = $info->getTotalWeeks() + $semanas_dif;
        if ($semanas_adicionales > $semanas) {
            $adicional = $semanas_adicionales - $semanas;
            $porcentaje_adicional = ((int)($adicional / $this->ipcService->catalogo(self::ADICIONAL))) * $this->ipcService->catalogo(self::INCREMENTO_TAZA);
        }
        $data = [];
        $data['proyeccion'] = $arr;
        $data['meses'] = $total_meses_proyeccion;
        $data['fechaInicial'] = $fechaInicialProyeccion ? $fechaInicialProyeccion->format('Y-m') : null;
        $data['fechaFinal'] = $fechaFinalProyeccion ? $fechaFinalProyeccion->format('Y-m') : null;
        $data['data'] = $table;
        $data['semanas'] = $semanas_adicionales;
        $data['smmlv'] = $salario;
        $data['ibl'] = $table['resume']['total'] / $table['resume']['meses'];
        // $data['ibl'] = $salariopos;
        $data['s'] = $data['ibl'] / $salario;
        $data['r'] = $this->ipcService->catalogo(self::TAZA_REEMPLAZO) - ($data['s'] * $this->ipcService->catalogo(self::VARIACION_TAZA));
        $data['x'] = $data['r'] + $porcentaje_adicional;
        $data['r1'] = $data['r'] * $data['ibl'];
        $data['r2'] = $data['x'] * $data['ibl'];


        $proyeccion = new Proyeccion();
        $proyeccion->setInformation($info)
            ->setSalario($salariopos)
            ->setTitulo($req['titulo'])
            ->setFechaInicial($fechaInicialProyeccion ?? new DateTime())
            ->setFechaFinal($fechaFinalProyeccion ?? new DateTime())
            ->setJsonData($data);
        $em->persist($proyeccion);
        $em->flush();
        $this->addFlash('success', 'Proyección Creada');
        return $this->redirectToRoute('proyeccion', ['uniqid' => $info->getUniqId()]);
    }

    /**
     * @Route ("/client/proyection/add", name="proyection_add", methods={"POST"})
     */
    public function addProyection(Request $request)
    {
        $pos = $request->get('pos');
        $render = $this->renderView('main/proyeccion.html.twig', ['pos' => $pos]);
        return new Response($render);
    }

    /**
     * @Route ("/client/proyection/delete/{id}", name="proyection_delete", methods={"GET"})
     */
    public function deleteProyection(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $proyeccion = $em->getRepository(Proyeccion::class)->find($request->get('id'));
        $em->remove($proyeccion);
        $em->flush();
        $this->addFlash('success', 'Proyección Eliminada');
        return $this->redirectToRoute('client', ['uniqid' => $proyeccion->getInformation()->getUniqId()]);
    }

    /**
     * @Route("/client/carga/{id}", name="client_carga", methods={"POST"})
     */
    public function carga(Information $info, Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $em->getConnection()->beginTransaction();
        try {
            $file = $request->files->get('file');

            // Manejar el caso donde file puede ser un array
            if (is_array($file)) {
                $file = $file[0] ?? null;
            }

            if (!$file) {
                throw new Exception('No se ha subido ningún archivo');
            }

            $service = $this->uploader;
            $uploaded = $service->getTargetDirectory() . $service->upload($file);
            $this->excel_import($uploaded, $info);
            $em->flush();
            $em->getConnection()->commit();
            $this->addFlash('success', 'Datos cargados corrextamente');
        } catch (Exception $ex) {
            $em->getConnection()->rollBack();
            $this->addFlash('error', $ex->getMessage());
        }
        return $this->redirectToRoute('client', ['uniqid' => $info->getUniqId()]);
    }

    /**
     * @Route("/client/comentario/{id}", name="cliente_comentario", methods={"POST"})
     */
    public function saveComment(Information $info, Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        // $info = $em->getRepository(Information::class)->find($request->get('id'));
        $info->setConclusiones($request->get('comentario'));
        $em->persist($info);
        $em->flush();
        return new JsonResponse(["message" => "Guardado satisfactoriamente"]);
    }

    /**
     * @Route("/client/actualizar_dias", name="client_actualizar_dias", methods={"POST"})
     * @param Request $request
     * @return JsonResponse
     * @throws Exception
     * Function to update the days of the Data Entity
     */
    public function actualizarDias(Request $request): JsonResponse
    {
        $data = $request->request->all();
        $pk = $data['pk'];
        $value = $data['value'];
        $em = $this->getDoctrine()->getManager();
        $dataEntity = $em->getRepository(Data::class)->find($pk);
        $dataEntity->setDaysPeriod($value);
        $em->persist($dataEntity);
        $em->flush();
        return new JsonResponse(['message' => 'Dias actualizados'], Response::HTTP_OK);
    }

    /**
     * @Route("/client/actualizar_salario", name="client_actualizar_salario", methods={"POST"})
     * @param Request $request
     * @return JsonResponse
     * @throws Exception
     * Function to update the days of the Data Entity
     */
    public function actualizarSalario(Request $request): JsonResponse
    {
        $data = $request->request->all();
        $pk = $data['pk'];
        $value = $data['value'];
        $em = $this->getDoctrine()->getManager();
        $dataEntity = $em->getRepository(Data::class)->find($pk);
        $dataEntity->setVal($value);
        $em->persist($dataEntity);
        $em->flush();
        return new JsonResponse(['message' => 'Salario actualizado'], Response::HTTP_OK);
    }

    private function skandia($data)
    {
        $extract = [];
        $palabra_clave = ["Régimen de Ahorro Individual con Solidaridad", "Historia Laboral Régimen de Ahorro Individual con Solidaridad"];
        $i = 0;
        foreach ($data as $datum) {
            if (!in_array($datum['title'], $palabra_clave)) continue;
            foreach ($datum['rows'] as $row) {
                $extract[] = $row;
            }
        }
        $info = [];
        $valor_mes = (360 / 7) / 12;
        $start = 0;
        foreach ($extract as $item) {
            if (!is_numeric($item[0])) continue;
            foreach ($this->dateRange($item[0], $item[0], 'Ym') as $value) {
                $int = round((filter_var($item[3], FILTER_SANITIZE_NUMBER_INT) / 100), 0);
                if (!isset($info[$value['format']])) $info[$value['format']] = 0;
                $info[$value['format']] += $int;
                $info[$value['format']] = $info[$value['format']] >= $value['tope'] ? $value['tope'] : $info[$value['format']];

                $start += $valor_mes;
            }
        }
        return ['data' => $info, 'semanas' => $start];
    }

    private function dateRange($_start, $_end, $format = 'd/m/Y'): array
    {
        $start = (DateTime::createFromFormat($format, $_start))->modify('first day of this month');
        $end = (DateTime::createFromFormat($format, $_end))->modify('first day of next month');
        $interval = DateInterval::createFromDateString('1 month');
        $period = new DatePeriod($start, $interval, $end);
        $arr = [];
        foreach ($period as $dt) {
            $arr[] = ['format' => $dt->format("Y-m"),
                'tope' => $this->ipcService->salarioMinimo($dt->format('Y'), true)
            ];
        }
        return $arr;
    }

    private function colpensiones($data)
    {
        //Colpensiones
        $extract = [];
        $palabra_clave = "[3] Desde";
        foreach ($data as $datum) {
            if (!in_array($palabra_clave, $datum['head'])) break;
            foreach ($datum['rows'] as $row) {
                $extract[] = $row;
            }
        }

        $info = [];
        $valor_mes = (360 / 7) / 12;
        $start = 0;
        foreach ($extract as $item) {
            $x = str_replace(',', '.', $item[8]);
            $start += $x;
            foreach ($this->dateRange($item[2], $item[3]) as $value) {
                $int = (int)filter_var($item[4], FILTER_SANITIZE_NUMBER_INT);
                if ($int === 0) continue;
                if (!isset($info[$value['format']])) {
                    $info[$value['format']] = 0;
                }
                $info[$value['format']] += $int;
                $info[$value['format']] = $info[$value['format']] >= $value['tope'] ? $value['tope'] : $info[$value['format']];
            }
        }
        return ['data' => $info, 'semanas' => $start];
    }

    private function porvenir($data)
    {
        $extract = [];
        $size = 6;
        foreach ($data as $datum) {
            foreach ($datum['rows'] as $row) {
                if (count($row) != $size || strlen($row[3]) != 7) continue;
                $extract[] = $row;
            }
        }
        $info = [];
        $valor_mes = (360 / 7) / 12;
        $start = [];
        foreach ($extract as $item) {
            foreach ($this->dateRange($item[3], $item[4], 'm/Y') as $value) {
                $int = (int)filter_var($item[5], FILTER_SANITIZE_NUMBER_INT);
                if ($int === 0) continue;
                if (!isset($info[$value['format']])) $info[$value['format']] = 0;
                $info[$value['format']] += $int;
                $info[$value['format']] = $info[$value['format']] >= $value['tope'] ? $value['tope'] : $info[$value['format']];
                $start[$value['format']] = $valor_mes;
            }
        }

        return ['data' => $info, 'semanas' => array_sum($start)];
    }

    private function colfondos($data)
    {
        $extract = [];
        $size = 6;
        foreach ($data as $datum) {
            foreach ($datum['rows'] as $row) {
                if (count($row) != $size) continue;
                $extract[] = $row;
            }
        }
        $info = [];
        $valor_mes = (360 / 7) / 12;
        $start = 0;
        foreach ($extract as $item) {
            foreach ($this->dateRange($item[1], $item[1], 'Ym') as $value) {
                $int = (int)filter_var($item[4], FILTER_SANITIZE_NUMBER_INT);
                if ($int === 0) continue;
                if (!isset($info[$value['format']])) $info[$value['format']] = 0;
                $info[$value['format']] += $int;
                $info[$value['format']] = $info[$value['format']] >= $value['tope'] ? $value['tope'] : $info[$value['format']];

                $start += $valor_mes;
            }
        }
        return ['data' => $info, 'semanas' => $start];

    }

    private function proteccion($data)
    {
        $extract = [];
        $size = 8;
        foreach ($data as $datum) {
            foreach ($datum['rows'] as $row) {
                if (count($row) != $size) continue;
                $extract[] = $row;
            }
        }
        $info = [];
        $valor_mes = (360 / 7) / 12;
        $start = 0;
        foreach ($extract as $item) {
            foreach ($this->dateRange($item[2], $item[2], 'Y/m') as $value) {
                $int = (int)filter_var($item[3], FILTER_SANITIZE_NUMBER_INT);
                if ($int === 0) continue;
                if (!isset($info[$value['format']])) $info[$value['format']] = 0;
                $info[$value['format']] += $int;
                $info[$value['format']] = $info[$value['format']] >= $value['tope'] ? $value['tope'] : $info[$value['format']];

                $start += $valor_mes;
            }
        }
        return ['data' => $info, 'semanas' => $start];
    }

    private function normalizeUploadedFile($file): ?UploadedFile
    {
        if ($file instanceof UploadedFile) {
            return $file;
        }

        if (is_array($file)) {
            $expected = ['name', 'type', 'tmp_name', 'error', 'size'];
            $hasStructure = count(array_intersect($expected, array_keys($file))) === count($expected);

            if ($hasStructure) {
                $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
                if ($error === UPLOAD_ERR_OK && !empty($file['tmp_name'])) {
                    return new UploadedFile(
                        $file['tmp_name'],
                        $file['name'] ?? 'upload',
                        $file['type'] ?? null,
                        $error,
                        true
                    );
                }

                return null;
            }

            foreach ($file as $nested) {
                $normalized = $this->normalizeUploadedFile($nested);
                if ($normalized instanceof UploadedFile) {
                    return $normalized;
                }
            }
        }

        return null;
    }


    /**
     * @Route("/client/{uniqid}/chat", name="client_chat", methods={"POST"})
     */
    public function chat(Request $request, ClaudeService $claudeService)
    {
        $uniqid = $request->get('uniqid');
        $userMessage = $request->request->get('message');
        $history = json_decode($request->request->get('history', '[]'), true);
        $fullHistory = $request->request->get('full_history', false);

        $em = $this->getDoctrine()->getManager();
        $info = $em->getRepository(Information::class)->findOneBy(['uniq_id' => $uniqid]);

        if (!$info) {
            return $this->json(['success' => false, 'error' => 'Informe no encontrado']);
        }

        // Obtener datos de cotización
        $dataRepo = $em->getRepository(Data::class);
        $allData = $dataRepo->findBy(['info' => $info], ['period' => 'ASC']);
        
        // Construir datos de cotización según el modo
        $datosCotizacion = [];
        $totalRegistros = count($allData);
        
        if ($fullHistory && $totalRegistros > 40) {
            // Modo historia completa: primeros 20 + últimos 20 + resumen estadístico
            $primeros20 = array_slice($allData, 0, 20);
            $ultimos20 = array_slice($allData, -20);
            
            foreach ($primeros20 as $data) {
                $datosCotizacion[] = [
                    'periodo' => $data->getPeriod(),
                    'salario' => $data->getVal(),
                    'seccion' => 'inicio'
                ];
            }
            
            // Generar resumen estadístico del medio
            $medioData = array_slice($allData, 20, $totalRegistros - 40);
            $resumenPorAnio = [];
            
            foreach ($medioData as $data) {
                $anio = substr($data->getPeriod(), 0, 4);
                if (!isset($resumenPorAnio[$anio])) {
                    $resumenPorAnio[$anio] = [
                        'registros' => 0,
                        'suma_salarios' => 0,
                        'min_salario' => PHP_INT_MAX,
                        'max_salario' => 0
                    ];
                }
                $resumenPorAnio[$anio]['registros']++;
                $resumenPorAnio[$anio]['suma_salarios'] += $data->getVal();
                $resumenPorAnio[$anio]['min_salario'] = min($resumenPorAnio[$anio]['min_salario'], $data->getVal());
                $resumenPorAnio[$anio]['max_salario'] = max($resumenPorAnio[$anio]['max_salario'], $data->getVal());
            }
            
            $datosCotizacion[] = [
                'seccion' => 'resumen_medio',
                'nota' => 'Resumen estadístico de ' . count($medioData) . ' registros adicionales',
                'resumen_por_anio' => $resumenPorAnio
            ];
            
            foreach ($ultimos20 as $data) {
                $datosCotizacion[] = [
                    'periodo' => $data->getPeriod(),
                    'salario' => $data->getVal(),
                    'seccion' => 'final'
                ];
            }
        } else {
            // Modo normal: todos los datos
            foreach ($allData as $data) {
                $datosCotizacion[] = [
                    'periodo' => $data->getPeriod(),
                    'salario' => $data->getVal()
                ];
            }
        }

        // Obtener datos de liquidación - Sentencia C-197 de 2023 (usar año de cotización)
        $anioCotizacion = (int)$info->getCotizacionAnio();
        $semanas = $this->ipcService->semanasExigidas($info->getGenero(), $anioCotizacion);
        $ad = [];
        $ad['Semanas Básicas'] = $semanas;
        $ad['Género'] = $info->getGeneroTexto();
        $ad['Sentencia C-197'] = ($info->isMujer() && $anioCotizacion >= 2026) ? 'Aplica' : 'No aplica';
        $ad['Días Calculados'] = $info->getTotalDays();
        $ad['Semanas Adicionales'] = 0;
        $ad['Porcentaje Adicional'] = 0;
        
        if ($info->getTotalWeeks() > $semanas) {
            $ad['Semanas Adicionales'] = $info->getTotalWeeks() - $semanas;
            $ad['Porcentaje Adicional'] = ((int)($ad['Semanas Adicionales'] / $this->ipcService->catalogo(self::ADICIONAL))) * $this->ipcService->catalogo(self::INCREMENTO_TAZA);
        }

        // Obtener datos de liquidación según el modo
        if ($fullHistory) {
            $data = $dataRepo->getInfoListed($info->getId(), $info->getCotizacionAnio());
        } else {
            $data = $dataRepo->getInfoListed($info->getId(), $info->getCotizacionAnio(), 120);
        }
        
        $table = $this->infoLiquidacion($data, $info->getCotizacionAnio());
        $salario = $this->ipcService->salarioMinimo($info->getCotizacionAnio());

        // Preparar contexto para Claude
        $context = [
            'informacion' => [
                'nombre' => $info->getFullName(),
                'identificacion' => $info->getIdentification(),
                'fecha_nacimiento' => $info->getBirthdate()->format('Y-m-d'),
                'edad' => $info->getEdad(),
                'fondo' => $info->getFondo(),
                'semanas_totales' => $info->getTotalWeeks(),
                'anio_liquidacion' => $info->getCotizacionAnio()
            ],
            'datos_cotizacion' => $datosCotizacion,
            'total_registros' => $totalRegistros,
            'modo_historia_completa' => $fullHistory ? true : false,
            'liquidacion' => [
                'semanas_basicas' => $ad['Semanas Básicas'],
                'semanas_adicionales' => $ad['Semanas Adicionales'],
                'porcentaje_adicional' => $ad['Porcentaje Adicional'],
                'ibl' => strip_tags($table['IBL']),
                'salario_minimo' => number_format($salario, 0, ',', '.'),
                'pension_basica' => strip_tags($table['R1']),
                'pension_total' => strip_tags($table['R2'])
            ]
        ];

        // Enviar mensaje a Claude
        $response = $claudeService->sendMessage($userMessage, $context, $history);

        return $this->json($response);
    }

    /**
     * Escapa texto para insertar en Word con saltos de línea correctos
     * Usa el carácter de salto suave que Word interpreta correctamente
     */
    private function escapeWordText(string $text): string
    {
        // Normalizar saltos de línea
        $text = str_replace("\r\n", "\n", $text);
        $text = str_replace("\r", "\n", $text);
        // Escapar caracteres especiales XML
        $text = htmlspecialchars($text, ENT_XML1, 'UTF-8');
        // Convertir \n a salto de línea Word XML (PHPWord 0.18 no lo hace automáticamente)
        $text = str_replace("\n", '</w:t><w:br/><w:t xml:space="preserve">', $text);
        return $text;
    }

    /**
     * Convierte una tabla HTML a tabla Word nativa
     */
    private function htmlTableToWordTable($templateProcessor, $placeholder, $htmlTable)
    {
        if (empty($htmlTable)) {
            $templateProcessor->setValue($placeholder, 'No hay datos');
            return;
        }

        // Parsear HTML
        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $htmlTable);

        $textTable = '';
        $columnWidths = [];

        // Procesar encabezados para calcular anchos
        $thead = $dom->getElementsByTagName('thead')->item(0);
        $headers = [];
        if ($thead) {
            $headerRow = $thead->getElementsByTagName('tr')->item(0);
            if ($headerRow) {
                $ths = $headerRow->getElementsByTagName('th');
                foreach ($ths as $index => $th) {
                    $text = trim($th->textContent);
                    $headers[] = $text;
                    $columnWidths[$index] = strlen($text);
                }
            }
        }

        // Recopilar todas las filas para calcular anchos máximos
        $allRows = [];
        $tbody = $dom->getElementsByTagName('tbody')->item(0);
        if ($tbody) {
            $rows = $tbody->getElementsByTagName('tr');
            foreach ($rows as $row) {
                $tds = $row->getElementsByTagName('td');
                $cells = [];
                foreach ($tds as $index => $td) {
                    $text = trim($td->textContent);
                    $cells[] = $text;
                    if (!isset($columnWidths[$index]) || strlen($text) > $columnWidths[$index]) {
                        $columnWidths[$index] = strlen($text);
                    }
                }
                $allRows[] = $cells;
            }
        }

        // Limitar ancho de columnas a 30 caracteres para evitar líneas muy largas
        foreach ($columnWidths as &$width) {
            if ($width > 30) $width = 30;
            if ($width < 10) $width = 10; // Ancho mínimo
        }

        // Salto de línea Word XML (PHPWord 0.18 no convierte \n automáticamente)
        $br = '</w:t><w:br/><w:t xml:space="preserve">';

        // Construir encabezados
        if (!empty($headers)) {
            foreach ($headers as $index => $header) {
                $width = $columnWidths[$index];
                $textTable .= str_pad(substr($header, 0, $width), $width) . ' | ';
            }
            $textTable = rtrim($textTable) . $br;

            // Línea separadora
            foreach ($columnWidths as $width) {
                $textTable .= str_repeat('-', $width) . '-+-';
            }
            $textTable = rtrim($textTable, '-+') . $br;
        }

        // Construir filas de datos
        foreach ($allRows as $cells) {
            foreach ($cells as $index => $cell) {
                $width = isset($columnWidths[$index]) ? $columnWidths[$index] : 20;
                // Escapar caracteres especiales XML en el contenido de celdas
                $escapedCell = htmlspecialchars($cell, ENT_XML1, 'UTF-8');
                $textTable .= str_pad(substr($escapedCell, 0, $width), $width) . ' | ';
            }
            $textTable = rtrim($textTable) . $br;
        }

        // Procesar footer si existe
        $tfoot = $dom->getElementsByTagName('tfoot')->item(0);
        if ($tfoot) {
            $footerRow = $tfoot->getElementsByTagName('tr')->item(0);
            if ($footerRow) {
                // Línea separadora antes del footer
                foreach ($columnWidths as $width) {
                    $textTable .= str_repeat('=', $width) . '=+=';
                }
                $textTable = rtrim($textTable, '=+') . $br;

                $ths = $footerRow->getElementsByTagName('th');
                foreach ($ths as $index => $th) {
                    $text = trim($th->textContent);
                    $width = isset($columnWidths[$index]) ? $columnWidths[$index] : 20;
                    $textTable .= str_pad(substr($text, 0, $width), $width) . ' | ';
                }
                $textTable = rtrim($textTable) . $br;
            }
        }

        $templateProcessor->setValue($placeholder, $textTable);
    }


}

