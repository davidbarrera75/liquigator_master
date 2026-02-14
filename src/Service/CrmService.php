<?php

namespace App\Service;

use App\Entity\Information;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class CrmService
{
    private $httpClient;
    private $logger;
    private $crmApiUrl;
    private $crmApiToken;
    private $crmEnabled;

    public function __construct(
        HttpClientInterface $httpClient,
        LoggerInterface $logger,
        string $crmApiUrl,
        string $crmApiToken,
        bool $crmEnabled
    ) {
        $this->httpClient = $httpClient;
        $this->logger = $logger;
        $this->crmApiUrl = $crmApiUrl;
        $this->crmApiToken = $crmApiToken;
        $this->crmEnabled = $crmEnabled;
    }

    public function enviarInforme(Information $info): bool
    {
        if (!$this->isEnabled()) {
            $this->logger->debug('CRM: Integración desactivada, no se envía informe.');
            return false;
        }

        try {
            $payload = $this->buildPayload($info);
            $url = rtrim($this->crmApiUrl, '/') . '/api/integraciones/liquigator';

            $this->logger->info('CRM: Enviando informe', [
                'url' => $url,
                'liquigator_id' => $info->getUniqId(),
                'cliente' => $info->getFullName(),
            ]);

            $response = $this->httpClient->request('POST', $url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->crmApiToken,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                'json' => $payload,
                'timeout' => 10,
            ]);

            $statusCode = $response->getStatusCode();

            if ($statusCode >= 200 && $statusCode < 300) {
                $this->logger->info('CRM: Informe enviado exitosamente', [
                    'status_code' => $statusCode,
                    'liquigator_id' => $info->getUniqId(),
                ]);
                return true;
            }

            $this->logger->warning('CRM: Respuesta inesperada del servidor', [
                'status_code' => $statusCode,
                'liquigator_id' => $info->getUniqId(),
                'response' => $response->getContent(false),
            ]);
            return false;

        } catch (\Exception $e) {
            $this->logger->warning('CRM: Error al enviar informe', [
                'error' => $e->getMessage(),
                'liquigator_id' => $info->getUniqId(),
            ]);
            return false;
        }
    }

    public function isEnabled(): bool
    {
        return $this->crmEnabled && !empty($this->crmApiUrl);
    }

    public function buildPayload(Information $info): array
    {
        return [
            'cliente' => [
                'nombre_completo' => $info->getFullName(),
                'documento_identidad' => (string) $info->getIdentification(),
                'fecha_nacimiento' => $info->getBirthdate() ? $info->getBirthdate()->format('Y-m-d') : null,
                'genero' => $info->getGenero(),
            ],
            'caso' => [
                'tipo_proceso' => 'laboral',
                'titulo' => 'Liquidación pensional - ' . $info->getFullName(),
                'fondo' => $info->getFondo(),
                'cotizacion_anio' => $info->getCotizacionAnio(),
                'total_semanas' => $info->getTotalWeeks(),
                'total_dias' => $info->getTotalDays() ?? 0,
                'resumen' => $info->getResume(),
            ],
            'origen' => 'liquigator',
            'liquigator_id' => $info->getUniqId(),
            'created_at' => $info->getCreatedAt() ? $info->getCreatedAt()->format('Y-m-d\TH:i:s') : null,
        ];
    }
}
