<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Http\Exceptions\PayloadTooLargeException;
use App\Http\Exceptions\UnsupportedMediaTypeException;
use App\Http\Exceptions\ValidationFailedException;
use App\Services\FileStorage;
use App\Services\FileValidator;
use App\Services\ImageSanitizer;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * T-19.1-07, T-19.1-08: File upload and private storage security tests.
 * Asserts rejection of malicious upload vectors (SVGs, polyglots, MIME-spoofing,
 * path traversals, size limits) and validates anti-traversal storage isolation.
 */
class FileUploadSecurityTest extends TestCase
{
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $f) {
            if (file_exists($f)) {
                @unlink($f);
            }
        }
    }

    private function createTempFile(string $content, string $ext = 'tmp'): string
    {
        $path = tempnam(sys_get_temp_dir(), 'sec_upload_') . '.' . $ext;
        file_put_contents($path, $content);
        $this->tempFiles[] = $path;
        return $path;
    }

    /**
     * T-19.1-07: SVG upload must be rejected to prevent SVG Stored XSS.
     */
    public function testSvgUploadIsRejected(): void
    {
        $svgContent = '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><circle r="10"/></svg>';
        $path = $this->createTempFile($svgContent, 'svg');

        $this->expectException(UnsupportedMediaTypeException::class);
        FileValidator::validateImage([
            'tmp_name' => $path,
            'name'     => 'avatar.svg',
            'size'     => strlen($svgContent),
        ]);
    }

    /**
     * T-19.1-07: PHP script renamed to image extension is rejected via magic byte check.
     */
    public function testPhpFileDisguisedAsJpegIsRejected(): void
    {
        $phpContent = '<?php echo "evil"; system($_GET["c"]); ?>';
        $path = $this->createTempFile($phpContent, 'jpg');

        $this->expectException(UnsupportedMediaTypeException::class);
        FileValidator::validateImage([
            'tmp_name' => $path,
            'name'     => 'exploit.jpg',
            'size'     => strlen($phpContent),
        ]);
    }

    /**
     * T-19.1-07: Path traversal attempt in filename is sanitized.
     */
    public function testPathTraversalInFilenameIsSanitized(): void
    {
        // Valid 10x10 JPEG
        $path = tempnam(sys_get_temp_dir(), 'sec_') . '.jpg';
        $gd = imagecreatetruecolor(10, 10);
        imagejpeg($gd, $path);
        imagedestroy($gd);
        $this->tempFiles[] = $path;

        $validated = FileValidator::validateImage([
            'tmp_name' => $path,
            'name'     => '../../../../../../var/www/shell.php.jpg',
            'size'     => filesize($path),
        ]);

        $this->assertSame('shell.php.jpg', $validated['original_name']);
        $this->assertSame('jpg', $validated['extension']);
        $this->assertStringNotContainsString('..', $validated['original_name']);
    }

    /**
     * T-19.1-07: File exceeding 5MB image limit is rejected with 413.
     */
    public function testOversizedImageIsRejected(): void
    {
        $path = $this->createTempFile(str_repeat('A', 6000000), 'jpg');

        $this->expectException(PayloadTooLargeException::class);
        FileValidator::validateImage([
            'tmp_name' => $path,
            'name'     => 'huge.jpg',
            'size'     => 6000000,
        ]);
    }

    /**
     * T-19.1-07: Polyglot image with appended PHP payload is sterilized by ImageSanitizer.
     */
    public function testPolyglotJpegWithEmbeddedPhpPayloadIsSanitized(): void
    {
        // Create genuine JPEG
        $path = tempnam(sys_get_temp_dir(), 'polyglot_') . '.jpg';
        $gd = imagecreatetruecolor(10, 10);
        imagejpeg($gd, $path);
        imagedestroy($gd);
        $this->tempFiles[] = $path;

        // Append PHP code to the end of the JPEG
        $maliciousPayload = "<?php phpinfo(); /* EVIL EMBED */ ?>";
        file_put_contents($path, $maliciousPayload, FILE_APPEND);

        $this->assertStringContainsString('phpinfo()', file_get_contents($path));

        // Sanitize with ImageSanitizer (GD re-encode)
        ImageSanitizer::sanitize($path, 'image/jpeg');

        $cleanedContent = file_get_contents($path);
        $this->assertStringNotContainsString('phpinfo()', $cleanedContent, 'GD re-encoding must strip appended malicious payload');
        $this->assertStringNotContainsString('EVIL EMBED', $cleanedContent);
    }

    /**
     * T-19.1-08: Path traversal attempts in private storage resolution are detected.
     */
    public function testFileStorageRejectsPathTraversalResolution(): void
    {
        $storage = new FileStorage();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Path traversal attempt detected');
        $storage->resolvePath('../../../../../etc/passwd', 'images');
    }

    /**
     * T-19.1-08: Storage files are assigned random hex paths and stored outside public webroot.
     */
    public function testFilesStoredWithRandomizedHexNames(): void
    {
        $storageDir = sys_get_temp_dir() . '/sharesphere_test_storage_' . bin2hex(random_bytes(4));
        mkdir($storageDir . '/images', 0750, true);

        $path = tempnam(sys_get_temp_dir(), 'test_store_') . '.jpg';
        $gd = imagecreatetruecolor(5, 5);
        imagejpeg($gd, $path);
        imagedestroy($gd);
        $this->tempFiles[] = $path;

        $storage = new FileStorage($storageDir);
        $stored = $storage->store($path, 'images', 'jpg', 'image/jpeg');

        $this->assertMatchesRegularExpression('/^[a-f0-9]{2}\/[a-f0-9]{32}\.jpg$/', $stored['storage_name']);
        $this->assertFileExists($stored['path']);

        // Clean up
        $storage->delete($stored['storage_name'], 'images');
        @rmdir($storageDir . '/images/' . substr($stored['storage_name'], 0, 2));
        @rmdir($storageDir . '/images');
        @rmdir($storageDir);
    }
}
