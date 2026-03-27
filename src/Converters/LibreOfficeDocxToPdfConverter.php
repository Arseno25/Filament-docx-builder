<?php

namespace Arseno25\DocxBuilder\Converters;

use Arseno25\DocxBuilder\Contracts\DocxToPdfConverterInterface;
use RuntimeException;

class LibreOfficeDocxToPdfConverter implements DocxToPdfConverterInterface
{
    public function convertDocxBytesToPdfBytes(string $docxBytes): string
    {
        $tmpDir =
            rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) .
            DIRECTORY_SEPARATOR .
            'docx_builder_' .
            bin2hex(random_bytes(8));

        if (!@mkdir($tmpDir, 0777, true) && !is_dir($tmpDir)) {
            throw new RuntimeException(
                'Unable to create temporary directory for PDF conversion.',
            );
        }

        $docxPath = $tmpDir . DIRECTORY_SEPARATOR . 'preview.docx';
        file_put_contents($docxPath, $docxBytes);

        $binary = (string) config(
            'docx-builder.preview.layout.soffice_binary',
            'soffice',
        );

        try {
            $this->runWithTimeout(
                [
                    $binary,
                    '--headless',
                    '--nologo',
                    '--nofirststartwizard',
                    '--convert-to',
                    'pdf',
                    '--outdir',
                    $tmpDir,
                    $docxPath,
                ],
                timeoutSeconds: 60,
            );
        } catch (\Throwable $e) {
            $this->cleanup($tmpDir);

            throw new RuntimeException(
                'Unable to run LibreOffice for PDF conversion. ' .
                    'Configure DOCX_BUILDER_LAYOUT_PREVIEW_SOFFICE or install LibreOffice.',
                previous: $e,
            );
        }

        $pdfPath = $tmpDir . DIRECTORY_SEPARATOR . 'preview.pdf';
        if (!is_file($pdfPath)) {
            $this->cleanup($tmpDir);

            throw new RuntimeException(
                'LibreOffice did not produce the expected PDF output.',
            );
        }

        $bytes = file_get_contents($pdfPath);
        $this->cleanup($tmpDir);

        return (string) $bytes;
    }

    private function cleanup(string $tmpDir): void
    {
        foreach (glob($tmpDir . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            @unlink($file);
        }

        @rmdir($tmpDir);
    }

    /**
     * @param  array<int, string>  $command
     */
    private function runWithTimeout(array $command, int $timeoutSeconds): void
    {
        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $cmd = implode(' ', array_map('escapeshellarg', $command));

        $process = proc_open($cmd, $descriptorSpec, $pipes);
        if (!is_resource($process)) {
            throw new RuntimeException('Unable to start conversion process.');
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $stdout = '';
        $stderr = '';

        $start = time();

        while (true) {
            $stdout .= stream_get_contents($pipes[1]) ?: '';
            $stderr .= stream_get_contents($pipes[2]) ?: '';

            $status = proc_get_status($process);
            if (!$status['running']) {
                break;
            }

            if (time() - $start > $timeoutSeconds) {
                proc_terminate($process);
                throw new RuntimeException('Conversion process timed out.');
            }

            usleep(50_000);
        }

        $stdout .= stream_get_contents($pipes[1]) ?: '';
        $stderr .= stream_get_contents($pipes[2]) ?: '';

        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            $output = trim($stdout . "\n" . $stderr);
            throw new RuntimeException(
                'LibreOffice conversion failed.' .
                    ($output !== '' ? "\n\n{$output}" : ''),
            );
        }
    }
}
