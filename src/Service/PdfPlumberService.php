<?php

namespace App\Service;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class PdfPlumberService
{
    private $projectDir;
    private $ipcService;

    public function __construct(string $projectDir, IpcService $ipcService)
    {
        $this->projectDir = $projectDir;
        $this->ipcService = $ipcService;
    }

    /**
     * Extrae datos del PDF usando pdfplumber (Python)
     *
     * @param string $pdfPath Ruta absoluta al archivo PDF
     * @param string $fondo Nombre del fondo (colpensiones, skandia, porvenir, colfondos, proteccion)
     * @return array ['success' => bool, 'data' => array, 'semanas' => int, 'error' => string]
     */
    public function extractPdf(string $pdfPath, string $fondo): array
    {
        // Validar que el archivo existe
        if (!file_exists($pdfPath)) {
            return [
                'success' => false,
                'error' => "Archivo no encontrado: {$pdfPath}"
            ];
        }

        // Validar fondo
        $fondosValidos = ['colpensiones', 'skandia', 'porvenir', 'colfondos', 'proteccion'];
        $fondo = strtolower($fondo);
        if (!in_array($fondo, $fondosValidos)) {
            return [
                'success' => false,
                'error' => "Fondo '{$fondo}' no soportado. Opciones: " . implode(', ', $fondosValidos)
            ];
        }

        // Ejecutar script Python
        $scriptPath = $this->projectDir . '/bin/extract_pdf.py';

        if (!file_exists($scriptPath)) {
            return [
                'success' => false,
                'error' => "Script de extracción no encontrado: {$scriptPath}"
            ];
        }

        $process = new Process([
            'python3',
            $scriptPath,
            $pdfPath,
            $fondo
        ]);

        // Timeout de 120 segundos (suficiente para PDFs grandes)
        $process->setTimeout(120);

        try {
            $process->mustRun();
            $output = $process->getOutput();

            // Decodificar JSON
            $result = json_decode($output, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return [
                    'success' => false,
                    'error' => 'Error al decodificar respuesta JSON: ' . json_last_error_msg(),
                    'raw_output' => $output
                ];
            }

            if (!$result['success']) {
                return $result;
            }

            // Transformar datos al formato esperado por MainController
            $transformed = $this->transformToMainControllerFormat($result, $fondo);

            return $transformed;

        } catch (ProcessFailedException $exception) {
            return [
                'success' => false,
                'error' => 'Error al ejecutar script Python: ' . $exception->getMessage()
            ];
        }
    }

    /**
     * Transforma datos de pdfplumber al formato esperado por MainController
     *
     * Entrada: {"periodo": "199711", "salario": 173390, "dias": 30}
     * Salida: {"1997-11": 173390}
     */
    private function transformToMainControllerFormat(array $result, string $fondo): array
    {
        $data = [];
        $days_array = [];
        $original_values = [];  // Nuevo: valores originales del PDF
        $tope_aplicado_array = [];  // Nuevo: flag de tope aplicado

        foreach ($result['data'] as $row) {
            // Convertir periodo de YYYYMM a YYYY-MM
            $periodo = $row['periodo'];
            $year = (int)substr($periodo, 0, 4);
            $month = substr($periodo, 4, 2);
            $formattedPeriod = "{$year}-{$month}";

            // Guardar valor original del PDF (ANTES de aplicar tope)
            $salarioOriginal = (int)$row['salario'];
            $original_values[$formattedPeriod] = $salarioOriginal;

            // Aplicar tope de 25 SMLMV
            $tope = $this->ipcService->salarioMinimo($year, true);
            $salarioConTope = min($salarioOriginal, $tope);

            // Determinar si se aplicó el tope
            $topeAplicado = ($salarioOriginal > $tope) ? 1 : 0;
            $tope_aplicado_array[$formattedPeriod] = $topeAplicado;

            // Guardar dato con tope aplicado
            $data[$formattedPeriod] = $salarioConTope;

            // Guardar días si está presente (colpensiones)
            if (isset($row['dias']) && $row['dias'] > 0) {
                $days_array[$formattedPeriod] = $row['dias'];
            }
        }

        // Usar las semanas y días calculados por Python
        $response = [
            'success' => true,
            'data' => $data,
            'semanas' => $result['semanas'],  // Calculado por Python
            'total_rows' => count($data),
            'fondo' => $fondo,
            'original_values' => $original_values,  // Valores originales del PDF
            'tope_aplicado_array' => $tope_aplicado_array  // Flags de tope aplicado
        ];

        // Agregar días y days_array si existe (colpensiones)
        if (isset($result['dias']) && $result['dias'] > 0) {
            $response['dias'] = $result['dias'];
        }

        if (!empty($days_array)) {
            $response['days_array'] = $days_array;
        }

        return $response;
    }

    /**
     * Verifica si el script Python está disponible
     */
    public function isPdfPlumberAvailable(): bool
    {
        $scriptPath = $this->projectDir . '/bin/extract_pdf.py';

        if (!file_exists($scriptPath)) {
            return false;
        }

        // Verificar que Python 3 está instalado
        $process = new Process(['python3', '--version']);
        try {
            $process->run();
            return $process->isSuccessful();
        } catch (\Exception $e) {
            return false;
        }
    }
}
