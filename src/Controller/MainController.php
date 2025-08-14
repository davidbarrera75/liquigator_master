<?php

namespace App\Controller;

use App\Entity\Configuration;
use App\Entity\Data;
use App\Entity\Information;
use App\Service\FileUploader;
use App\Service\IpcService;
use DateInterval;
use DatePeriod;
use DateTime;
use Dompdf\Dompdf;
use Dompdf\Options;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route ("/user")
 */
class MainController extends AbstractController
{
    const SEMANAS = 1300;
    const TAZA_REEMPLAZO = 0.655;
    const INCREMENTO_TAZA = 0.015;
    const VARIACION_TAZA = 0.005;
    const ADICIONAL = 50;
    const MAX_PDF_SIZE = 10485760; // 10MB
    private $uploader, $ipcService, $logger;

    public function __construct(FileUploader $uploader, IpcService $ipcService, LoggerInterface $logger)
    {
        $this->uploader = $uploader;
        $this->ipcService = $ipcService;
        $this->logger = $logger;
    }

    /**
     * @Route("/", name="main")
     */
    public function index(): Response
    {
        $em = $this->getDoctrine()->getManager();
        $pensiones = $em->getRepository(Configuration::class)->findOneBy(['name' => 'pensiones']);

        return $this->render('main/index.html.twig', [
            'pensiones' => $pensiones->getConfigValues(),

        ]);
    }

    /**
     * @Route("/datos", name="datos", methods={"POST"})
     */
    public function datos(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $file = $request->files->get('file');
        $name = $request->get('fullname');

        if ($file->getSize() > self::MAX_PDF_SIZE) {
            $this->logger->warning('Archivo PDF excede el tamaño máximo permitido');
            $this->addFlash('error', 'El PDF supera el tamaño máximo permitido (10MB)');
            return $this->redirectToRoute('main');
        }

        $service = $this->uploader;
        $uploaded = $service->getTargetDirectory() . $service->upload($file);

        $getFile = @file_get_contents($uploaded);
        if ($getFile === false) {
            $this->logger->error('No se pudo leer el archivo PDF subido');
            $this->addFlash('error', 'Revisa tu documento e intenta más tarde');
            return $this->redirectToRoute('main');
        }

        $enco = base64_encode($getFile);

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-type: application/json",
                'content' => json_encode(array('type' => $request->get('type'), 'base64' => $enco)),
                'timeout' => 30
            ]
        ]);

        $response = @file_get_contents('http://cotizador.today:33016/pdf2text/api/v1/extract', false, $context);
        if ($response === false) {
            $this->logger->error('No se pudo procesar el PDF en el servicio externo');
            $this->addFlash('error', 'Revisa tu documento e intenta más tarde');
            return $this->redirectToRoute('main');
        }

        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logger->error('Respuesta inválida del servicio de extracción de PDF: '.json_last_error_msg());
            $this->addFlash('error', 'Revisa tu documento e intenta más tarde');
            return $this->redirectToRoute('main');
        }

        $em->getConnection()->beginTransaction();
        try {
            $bog = new \DateTimeZone('America/Bogota');
            $fondo = $request->get('type');
            try {
                $extract = $this->$fondo($data);
            } catch (\Throwable $e) {
                $this->logger->error('Error al procesar el PDF', ['exception' => $e]);
                $this->addFlash('error', 'Revisa tu documento e intenta más tarde');
                return $this->redirectToRoute('main');
            }
            $birthDATE = Datetime::createFromFormat('Y-m-d', $request->get('birthdate'));
            $info = new Information();
            $info->setCreatedAt(new DateTime('now',$bog));
            $info->setIdentification($request->get('identification'));
            $info->setFondo($fondo);
            $info->setFullName($name);
            $info->setUser($this->getUser());
            $info->setTotalWeeks($extract['semanas']);
            $info->setBirthdate($birthDATE);
            $em->persist($info);
            foreach ($extract['data'] as $item => $datum) {
                $data_info = new Data();
                $data_info->setInfo($info);
                $data_info->setPeriod($item);
                $data_info->setVal($datum);
                $em->persist($data_info);

            }
            $em->flush();
            $em->getConnection()->commit();
            return $this->redirectToRoute('client', ['uniqid' => $info->getUniqId()]);

        } catch (Exception $e) {
            $em->getConnection()->rollBack();
            throw $e;
        }


    }

    /**
     * @Route ("/client/{uniqid}", name="client")
     */
    public function client(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $info = $em->getRepository(Information::class)->findOneBy(['uniq_id' => $request->get('uniqid')]);
        $data = $em->getRepository(Data::class)->getInfoListed($info->getId(), 120);
        $table = $this->infoLiquidacion($data);
        $salario = ($em->getRepository(Configuration::class)->findOneBy(['name' => 'SMMLV']))->getDefaultVslue();

        $ad['Semanas Básicas'] = self::SEMANAS;
        $ad['Semanas Adicionales'] = 0;
        $ad['Porcentaje Adicional'] = 0;
        if ($info->getTotalWeeks() > self::SEMANAS) {
            $ad['Semanas Adicionales'] = $info->getTotalWeeks() - self::SEMANAS;
            $ad['Porcentaje Adicional'] = ($ad['Semanas Adicionales'] / self::ADICIONAL) * self::INCREMENTO_TAZA;
        }
        $ad['IBL'] = $table['resume']['total'] / $table['resume']['meses'];
        $ad['S'] = $ad['IBL'] / $salario;
        $ad['R'] = self::TAZA_REEMPLAZO - ($ad['S'] * self::VARIACION_TAZA);
        $ad['X'] = $ad['R'] + $ad['Porcentaje Adicional'];
        $ad['R1'] = $ad['R'] * $ad['IBL'];
        $ad['R2'] = $ad['X'] * $ad['IBL'];


        $ad['IBL'] = '$ ' . number_format($ad['IBL'], 2);
        $ad['S'] = number_format($ad['S'], 2);
        $ad['R1'] = '$ ' . number_format($ad['R1'], 2);
        $ad['R2'] = '$ ' . number_format($ad['R2'], 2);
        $ad['R'] = number_format($ad['R'] * 100, 2) . '%';
        $ad['X'] = number_format($ad['X'] * 100, 2) . '%';
        $ad['Porcentaje Adicional'] = number_format($ad['Porcentaje Adicional'] * 100, 2) . '%';

        $info->setResume($ad);
        $em->persist($info);
        $em->flush();

        return $this->render('main/client.html.twig', [
            'info' => $info,
            'data' => $table,
            'ad' => $ad
        ]);
    }

    private function infoLiquidacion(array $data)
    {
        $ipc = $this->ipc($data[count($data) - 1]['period'], $data[0]['period']);
        $r = [];
        $r['content'] = [];
        $r['resume'] = ['meses' => 0, 'total' => 0];
        foreach ($data as $datum) {
            $act_1 = $datum['val'] * $ipc[$datum['period']];
            $act_2 = $act_1 * $datum['conteo'];
            $r['content'][] = [
                'period' => $datum['period'],
                'val' => $datum['val'],
                'conteo' => $datum['conteo'],
                'ipc' => $ipc[$datum['period']],
                'actualizado' => $act_1,
                'porMeses' => $act_2
            ];
            $r['resume']['total'] += $act_2;
            $r['resume']['meses'] += $datum['conteo'];
        }
        return $r;

    }

    private function ipc($min, $max): array
    {
        $ipc = [];
        for ($i = 1990; $i <= date('Y'); $i++) {
            $ipc[$i] = $this->ipcService->calculate($i);
        }
        return $ipc;
    }

    /**
     * @Route ("/client/{uniqid}/week", name="client_weeks_update", methods={"POST"})
     */
    public function weekUpdate(Request $request)
    {
        if (is_numeric($request->get('semanas_act'))) {
            $em = $this->getDoctrine()->getManager();
            $info = $em->getRepository(Information::class)->findOneBy(['uniq_id' => $request->get('uniqid')]);
            $info->setTotalWeeks($request->get('semanas_act'));
            $em->persist($info);
            $em->flush();
            $this->addFlash('success', 'Semanas Actualizadas');
        } else {
            $this->addFlash('error', 'No se puede actualizar las semanas ya que el valor no es numèrico');
        }


        return $this->redirectToRoute('client', ['uniqid' => $info->getUniqId()]);

    }

    /**
     * @Route("/pdf/{id}", name="report_pdf", methods={"POST"})
     */
    public function report(Information $information, Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $options = new Options();
        $options->set('defaultfont', 'Arial');
        $options->setIsRemoteEnabled(true);
        $salario = ($em->getRepository(Configuration::class)->findOneBy(['name' => 'SMMLV']))->getDefaultVslue();

        $dompdf = new Dompdf($options);
        $html = $this->renderView('main/reportPDF.html.twig', ['info' => $information, 'smmlv' => $salario, 'conclusiones' => $request->get('conclusiones')]);
        $dompdf->loadHtml($html);
//print_r($html);die();
        // (Optional) Setup the paper size and orientation 'portrait' or 'portrait'
        $dompdf->setPaper('Letter', 'portrait');

        // Render the HTML as PDF
        $dompdf->render();

        // Output the generated PDF to Browser (force download)
        $dompdf->stream("InformePension-" . $information->getUniqId() . ".pdf", [
            "Attachment" => false
        ]);
        exit;
    }

    private function skandia($data)
    {
        $extract = [];
        $palabra_clave = "Régimen de Ahorro Individual con Solidaridad";
        $i = 0;
        foreach ($data as $datum) {
            if ($datum['title'] !== $palabra_clave) continue;
            foreach ($datum['rows'] as $row) {
                $extract[] = $row;
            }
        }
        $info = [];
        $valor_mes = (360 / 7) / 12;
        $start = 0;
        foreach ($extract as $item) {
            foreach ($this->dateRange($item[0], $item[0], 'Ym') as $value) {
                $int = round((filter_var($item[3], FILTER_SANITIZE_NUMBER_INT) / 100), 0);
                $info[$value] = $int;
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
            $arr[] = $dt->format("Y-m");
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
            if ($item[7] != "0,00") continue;
            foreach ($this->dateRange($item[2], $item[3]) as $value) {
                $int = (int)filter_var($item[4], FILTER_SANITIZE_NUMBER_INT);
                $info[$value] = $int;
                $start += $valor_mes;
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
                if (count($row) != $size or strlen($row[3])!=7) continue;
                $extract[] = $row;
            }
        }
        $info = [];
        $valor_mes = (360 / 7) / 12;
        $start = 0;
        foreach ($extract as $item) {
            foreach ($this->dateRange($item[3], $item[4], 'm/Y') as $value) {
                $int = (int)filter_var($item[5], FILTER_SANITIZE_NUMBER_INT);
                $info[$value] = $int;
                $start += $valor_mes;
            }


        }
        return ['data' => $info, 'semanas' => $start];
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
                $info[$value] = $int;
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
                $info[$value] = $int;
                $start += $valor_mes;
            }
        }
        return ['data' => $info, 'semanas' => $start];
    }
}
