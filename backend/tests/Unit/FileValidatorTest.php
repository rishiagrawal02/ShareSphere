<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Http\Exceptions\PayloadTooLargeException;
use App\Http\Exceptions\UnsupportedMediaTypeException;
use App\Http\Exceptions\ValidationFailedException;
use App\Services\FileValidator;
use PHPUnit\Framework\TestCase;

class FileValidatorTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/ss_test_' . bin2hex(random_bytes(4));
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0777, true);
        }
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            $files = glob($this->tempDir . '/*');
            foreach ($files as $f) {
                if (is_file($f)) {
                    unlink($f);
                }
            }
            rmdir($this->tempDir);
        }
    }

    public function testValidJpegImagePassesValidation(): void
    {
        $filePath = $this->tempDir . '/sample.jpg';
        // Create 10x10 dummy JPEG using GD
        $img = imagecreatetruecolor(10, 10);
        imagejpeg($img, $filePath);
        imagedestroy($img);

        $fileArray = [
            'name'     => 'sample.jpg',
            'type'     => 'image/jpeg',
            'tmp_name' => $filePath,
            'size'     => filesize($filePath),
            'error'    => 0,
        ];

        $res = FileValidator::validateImage($fileArray);
        $this->assertSame('image/jpeg', $res['mime']);
        $this->assertSame('jpg', $res['extension']);
    }

    public function testValidPdfPassesDocumentValidation(): void
    {
        $filePath = $this->tempDir . '/doc.pdf';
        file_put_contents($filePath, "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF");

        $fileArray = [
            'name'     => 'doc.pdf',
            'type'     => 'application/pdf',
            'tmp_name' => $filePath,
            'size'     => filesize($filePath),
            'error'    => 0,
        ];

        $res = FileValidator::validateDocument($fileArray);
        $this->assertSame('application/pdf', $res['mime']);
        $this->assertSame('pdf', $res['extension']);
    }

    public function testExecutableRenamedToJpgIsRejected(): void
    {
        $filePath = $this->tempDir . '/virus.jpg';
        file_put_contents($filePath, "<?php echo 'malicious';");

        $fileArray = [
            'name'     => 'virus.jpg',
            'type'     => 'image/jpeg',
            'tmp_name' => $filePath,
            'size'     => filesize($filePath),
            'error'    => 0,
        ];

        $this->expectException(UnsupportedMediaTypeException::class);
        FileValidator::validateImage($fileArray);
    }

    public function testOversizeFileIsRejected(): void
    {
        $filePath = $this->tempDir . '/huge.jpg';
        // Create 6MB file
        $fp = fopen($filePath, 'wb');
        fwrite($fp, "\xFF\xD8\xFF\xE0" . str_repeat("\x00", 6 * 1024 * 1024));
        fclose($fp);

        $fileArray = [
            'name'     => 'huge.jpg',
            'type'     => 'image/jpeg',
            'tmp_name' => $filePath,
            'size'     => filesize($filePath),
            'error'    => 0,
        ];

        $this->expectException(PayloadTooLargeException::class);
        FileValidator::validateImage($fileArray);
    }

    public function testZeroByteFileIsRejected(): void
    {
        $filePath = $this->tempDir . '/empty.png';
        file_put_contents($filePath, '');

        $fileArray = [
            'name'     => 'empty.png',
            'type'     => 'image/png',
            'tmp_name' => $filePath,
            'size'     => 0,
            'error'    => 0,
        ];

        $this->expectException(ValidationFailedException::class);
        FileValidator::validateImage($fileArray);
    }
}
