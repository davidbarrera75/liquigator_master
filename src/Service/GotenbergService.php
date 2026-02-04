<?php

namespace App\Service;

use Psr\Log\LoggerInterface;

class GotenbergService
{
    private string $gotenbergUrl;
    private LoggerInterface $logger;

    public function __construct(
        LoggerInterface $logger,
        string $gotenbergUrl = 'http://localhost:3000'
    ) {
        $this->logger = $logger;
        $this->gotenbergUrl = $gotenbergUrl;
    }

    /**
     * Convierte HTML a PDF usando Gotenberg
     */
    public function convertHtmlToPdf(string $html, array $options = []): string
    {
        try {
            $boundary = uniqid();
            $delimiter = '-------------' . $boundary;

            $postData = "--$delimiter\r\n"
                . "Content-Disposition: form-data; name=\"files\"; filename=\"index.html\"\r\n"
                . "Content-Type: text/html\r\n\r\n"
                . $html . "\r\n"
                . "--$delimiter\r\n"
                . "Content-Disposition: form-data; name=\"marginTop\"\r\n\r\n0\r\n"
                . "--$delimiter\r\n"
                . "Content-Disposition: form-data; name=\"marginBottom\"\r\n\r\n0\r\n"
                . "--$delimiter\r\n"
                . "Content-Disposition: form-data; name=\"marginLeft\"\r\n\r\n0\r\n"
                . "--$delimiter\r\n"
                . "Content-Disposition: form-data; name=\"marginRight\"\r\n\r\n0\r\n"
                . "--$delimiter\r\n"
                . "Content-Disposition: form-data; name=\"paperWidth\"\r\n\r\n8.5\r\n"
                . "--$delimiter\r\n"
                . "Content-Disposition: form-data; name=\"paperHeight\"\r\n\r\n11\r\n"
                . "--$delimiter\r\n"
                . "Content-Disposition: form-data; name=\"preferCssPageSize\"\r\n\r\ntrue\r\n"
                . "--$delimiter\r\n"
                . "Content-Disposition: form-data; name=\"printBackground\"\r\n\r\ntrue\r\n"
                . "--$delimiter--\r\n";

            $ch = curl_init($this->gotenbergUrl . '/forms/chromium/convert/html');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Content-Type: multipart/form-data; boundary=$delimiter",
                "Content-Length: " . strlen($postData)
            ]);

            $pdfContent = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200) {
                throw new \Exception("Gotenberg returned status $httpCode");
            }

            $this->logger->info('PDF generated via Gotenberg', [
                'html_size' => strlen($html),
                'pdf_size' => strlen($pdfContent)
            ]);

            return $pdfContent;

        } catch (\Exception $e) {
            $this->logger->error('Error converting HTML to PDF', ['error' => $e->getMessage()]);
            throw new \Exception('Failed to generate PDF: ' . $e->getMessage());
        }
    }

    /**
     * Verifica si Gotenberg está disponible
     */
    public function isAvailable(): bool
    {
        try {
            $ch = curl_init($this->gotenbergUrl . '/health');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            $result = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            return $httpCode === 200;
        } catch (\Exception $e) {
            return false;
        }
    }
}
