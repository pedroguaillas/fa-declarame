<?php

namespace App\Services;

use RuntimeException;

/**
 * Genera el manifiesto de actualización del agente SRI de escritorio a partir
 * de los archivos fuente: la versión sale de AGENT_VERSION en server.py y el
 * sha256 del contenido de los archivos que el agente descarga. Así cualquier
 * cambio desplegado en esos archivos se detecta aunque no se suba la versión.
 */
class SriAgentManifestService
{
    /**
     * Archivos que el agente descarga al actualizarse, en el orden en que se hashean.
     * Debe coincidir con AGENT_FILES en scripts/sri-agent/server.py.
     *
     * @var list<string>
     */
    public const AGENT_FILES = ['server.py', 'test-scraper.py'];

    public function __construct(private ?string $agentDirectory = null)
    {
        $this->agentDirectory ??= base_path('scripts/sri-agent');
    }

    /**
     * @return array{version: string, sha256: string, files: list<string>}
     */
    public function manifest(): array
    {
        return [
            'version' => $this->version(),
            'sha256' => $this->contentHash(),
            'files' => self::AGENT_FILES,
        ];
    }

    public function version(): string
    {
        $source = $this->read('server.py');

        if (! preg_match('/^AGENT_VERSION\s*=\s*["\']([0-9]+(?:\.[0-9]+)*)["\']/m', $source, $matches)) {
            throw new RuntimeException('No se encontró AGENT_VERSION en server.py del agente.');
        }

        return $matches[1];
    }

    /**
     * sha256 de la concatenación de los bytes de cada archivo del agente.
     */
    public function contentHash(): string
    {
        $context = hash_init('sha256');

        foreach (self::AGENT_FILES as $file) {
            hash_update($context, $this->read($file));
        }

        return hash_final($context);
    }

    private function read(string $file): string
    {
        $path = $this->agentDirectory.DIRECTORY_SEPARATOR.$file;
        $contents = is_file($path) ? file_get_contents($path) : false;

        if ($contents === false) {
            throw new RuntimeException("No se pudo leer el archivo del agente: {$file}");
        }

        return $contents;
    }
}
