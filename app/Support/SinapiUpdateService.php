<?php

declare(strict_types=1);

namespace IasdCentral\Support;

use IasdCentral\Repositories\SettingsRepository;
use IasdCentral\Repositories\SinapiRepository;
use ZipArchive;

final class SinapiUpdateService
{
    private const API_URL = "https://www.caixa.gov.br/_api/web/lists/getbytitle('Downloads')/Items?%24select=Title%2CModified%2CEncodedAbsUrl%2CCategoria%2FID&%24expand=Categoria&%24filter=Categoria%2FID%20eq%20888%20and%20FSObjType%20eq%200%20and%20OData__ModerationStatus%20eq%200&%24top=100&%24orderby=Modified%20desc";

    public function run(bool $scheduled = false): array
    {
        $settingsRepository = new SettingsRepository();
        $settings = $settingsRepository->all();
        if ($scheduled && ($settings['sinapi_auto_update'] !== '1' || !$this->isUpdateWindow())) {
            return ['status' => 'ignored', 'message' => 'Fora da janela de atualizacao.'];
        }
        if ($scheduled && str_starts_with($settings['sinapi_last_check'], date('Y-m'))) {
            return ['status' => 'ignored', 'message' => 'Verificacao mensal ja realizada.'];
        }

        try {
            $release = $this->latestRelease();
            $current = (new SinapiRepository())->referenceSummary();
            if (($current['competencia'] ?? '') >= $release['competence']) {
                $message = 'Catalogo atualizado na versao ' . $current['competencia'] . '.';
                $this->record($message);
                return ['status' => 'current', 'message' => $message];
            }

            $directory = sys_get_temp_dir() . '/iasd-sinapi-' . bin2hex(random_bytes(6));
            if (!mkdir($directory, 0700, true) && !is_dir($directory)) {
                throw new \RuntimeException('Nao foi possivel criar a area temporaria.');
            }
            try {
                $zipPath = $directory . '/sinapi.zip';
                $this->download($release['url'], $zipPath);
                $xlsxPath = $this->extractReference($zipPath, $directory);
                $csvPath = $directory . '/catalogo.csv';
                $command = sprintf('python3 %s %s %s 2>&1', escapeshellarg(APP_BASE_PATH . '/bin/extract-sinapi.py'), escapeshellarg($xlsxPath), escapeshellarg($csvPath));
                exec($command, $output, $code);
                if ($code !== 0 || !is_file($csvPath)) {
                    throw new \RuntimeException('Falha ao extrair a planilha: ' . implode(' ', array_slice($output, -3)));
                }
                $counts = (new SinapiRepository())->importCsv($csvPath, $release['competence'], $release['url']);
                if (array_sum($counts) < 10000 || $counts['Material'] < 1000 || $counts['Servico'] < 5000 || $counts['Equipamento'] < 100) {
                    throw new \RuntimeException('A nova referencia nao atingiu a quantidade minima de itens esperada.');
                }
            } finally {
                $this->removeDirectory($directory);
            }
            $message = 'SINAPI atualizado para ' . $release['competence'] . '.';
            $this->record($message);
            return ['status' => 'updated', 'message' => $message];
        } catch (\Throwable $exception) {
            $settingsRepository->updateSystem([
                'sinapi_last_check' => date('Y-m-d H:i:s'),
                'sinapi_last_status' => 'Versao atual mantida',
                'sinapi_last_error' => $exception->getMessage(),
            ]);
            return ['status' => 'error', 'message' => $exception->getMessage()];
        }
    }

    private function latestRelease(): array
    {
        $json = $this->request(self::API_URL);
        $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        foreach ($payload['d']['results'] ?? [] as $item) {
            if (preg_match('/SINAPI-(\d{4})-(\d{2})-formato-xlsx/i', (string) ($item['Title'] ?? ''), $match)) {
                return ['competence' => $match[1] . '-' . $match[2], 'url' => str_replace('http://', 'https://', (string) $item['EncodedAbsUrl'])];
            }
        }
        throw new \RuntimeException('A CAIXA nao retornou uma planilha SINAPI valida.');
    }

    private function download(string $url, string $target): void
    {
        $contents = $this->request($url);
        if (strlen($contents) < 1000000 || file_put_contents($target, $contents) === false) {
            throw new \RuntimeException('O arquivo SINAPI recebido e invalido.');
        }
    }

    private function request(string $url): string
    {
        $context = stream_context_create(['http' => ['timeout' => 120, 'follow_location' => 1, 'header' => "User-Agent: Mozilla/5.0\r\nCookie: security=true\r\nAccept: application/json;odata=verbose\r\n"]]);
        $contents = @file_get_contents($url, false, $context);
        if ($contents === false) {
            throw new \RuntimeException('A referencia oficial da CAIXA esta indisponivel.');
        }
        return $contents;
    }

    private function extractReference(string $zipPath, string $directory): string
    {
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) throw new \RuntimeException('O pacote SINAPI nao pode ser aberto.');
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = $zip->getNameIndex($index);
            if (preg_match('/SINAPI_Refer.*\.xlsx$/iu', $name)) {
                $target = $directory . '/referencia.xlsx';
                file_put_contents($target, $zip->getFromIndex($index));
                $zip->close();
                return $target;
            }
        }
        $zip->close();
        throw new \RuntimeException('A planilha de referencia nao foi encontrada no pacote.');
    }

    private function isUpdateWindow(): bool
    {
        $first = new \DateTimeImmutable('first day of this month');
        while (!$this->isBusinessDay($first)) $first = $first->modify('+1 day');
        $today = new \DateTimeImmutable('today');
        return $today >= $first && $this->isBusinessDay($today);
    }

    private function isBusinessDay(\DateTimeImmutable $date): bool
    {
        if ((int) $date->format('N') > 5) return false;
        $fixedHolidays = ['01-01', '04-21', '05-01', '09-07', '10-12', '11-02', '11-15', '11-20', '12-25'];
        if (in_array($date->format('m-d'), $fixedHolidays, true)) return false;
        $easter = (new \DateTimeImmutable($date->format('Y') . '-03-21'))->modify('+' . easter_days((int) $date->format('Y')) . ' days');
        $movableHolidays = [$easter->modify('-2 days')->format('Y-m-d'), $easter->modify('-47 days')->format('Y-m-d'), $easter->modify('+60 days')->format('Y-m-d')];
        return !in_array($date->format('Y-m-d'), $movableHolidays, true);
    }

    private function record(string $message): void
    {
        (new SettingsRepository())->updateSystem(['sinapi_last_check' => date('Y-m-d H:i:s'), 'sinapi_last_status' => $message, 'sinapi_last_error' => '']);
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) return;
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $entry) $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        rmdir($directory);
    }
}
