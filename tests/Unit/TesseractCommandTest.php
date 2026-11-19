<?php

namespace Tests\Unit;

use App\Services\OcrService;
use App\Services\TesseractCommand;
use PHPUnit\Framework\TestCase;

class TesseractCommandTest extends TestCase
{
    private string $directory;

    private string|false $path;

    protected function setUp(): void
    {
        parent::setUp();

        $this->path = getenv('PATH');
        $this->directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tesseract-path-' . uniqid();
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        putenv('PATH=' . $this->path);
        array_map('unlink', glob($this->directory . DIRECTORY_SEPARATOR . '*'));
        rmdir($this->directory);

        parent::tearDown();
    }

    public function test_without_options_the_command_does_not_ask_tesseract_for_its_version(): void
    {
        $command = new TesseractCommand('scan.png');
        // a program that cannot run: asking it for its version would fail
        $command->executable = $this->directory . DIRECTORY_SEPARATOR . 'missing-tesseract';

        $this->assertSame('', $command->getTesseractVersion());
        $this->assertStringStartsWith('"' . $command->executable . '" "scan.png" ', (string) $command);
    }

    public function test_the_executable_is_found_on_the_path_as_a_full_path(): void
    {
        $name = PHP_OS_FAMILY === 'Windows' ? 'tesseract.exe' : 'tesseract';
        $program = $this->directory . DIRECTORY_SEPARATOR . $name;
        // Windows only counts a file as a program when it starts like one
        file_put_contents($program, 'MZ');
        chmod($program, 0755);

        putenv('PATH=' . $this->directory . PATH_SEPARATOR . $this->path);

        $this->assertSame($program, OcrService::tesseractExecutable());
    }

    public function test_when_it_is_not_on_the_path_the_plain_name_is_used(): void
    {
        putenv('PATH=' . $this->directory);

        $this->assertSame('tesseract', OcrService::tesseractExecutable());
    }
}
