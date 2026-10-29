<?php

namespace App\Services;

use thiagoalessio\TesseractOCR\Command;

/**
 * The wrapper's command, without its extra "tesseract --version" process on every run.
 *
 * The wrapper asks for the version each time it builds the command, but only passes it to options;
 * OcrService sets none. On a 0.1 CPU server that call took 0.3 to 1.1 s per document.
 */
class TesseractCommand extends Command
{
    public function getTesseractVersion()
    {
        return $this->options === [] ? '' : parent::getTesseractVersion();
    }
}
