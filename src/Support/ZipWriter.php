<?php

namespace AmjadIqbal\FilamentRecordPdf\Support;

use RuntimeException;
use ZipArchive;

class ZipWriter
{
    protected ZipArchive $zip;

    protected string $path;

    public function __construct(?string $path = null)
    {
        $this->path = $path ?? (string) tempnam(sys_get_temp_dir(), 'record-pdf-zip-');
        $this->zip = new ZipArchive;

        if ($this->zip->open($this->path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Could not create the ZIP file at [{$this->path}].");
        }
    }

    public function add(string $name, string $contents): void
    {
        $this->zip->addFromString(basename($name), $contents);
    }

    /**
     * Finish the archive and return its local path.
     */
    public function close(): string
    {
        $this->zip->close();

        return $this->path;
    }
}
