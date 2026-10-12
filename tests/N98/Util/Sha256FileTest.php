<?php

declare(strict_types=1);

namespace N98\Util;

use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

/**
 * Class Sha256FileTest
 *
 * @package N98\Util
 */
final class Sha256FileTest extends TestCase
{
    private string $file;

    protected function setUp(): void
    {
        $this->file = tempnam(sys_get_temp_dir(), 'n98-sha256-');
        file_put_contents($this->file, 'magerun');
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
    }

    public function testAcceptsMatchingChecksumInSha256sumFormat(): void
    {
        $checksumFile = hash_file('sha256', $this->file) . "  n98-magerun.phar\n";

        Sha256File::verify($this->file, $checksumFile);

        $this->addToAssertionCount(1);
    }

    public function testAcceptsUppercaseChecksumWithoutFilename(): void
    {
        Sha256File::verify($this->file, strtoupper(hash_file('sha256', $this->file)));

        $this->addToAssertionCount(1);
    }

    public function testRejectsMismatchingChecksum(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('checksum mismatch');

        Sha256File::verify($this->file, str_repeat('0', 64) . "  n98-magerun.phar\n");
    }

    public function testRejectsMalformedChecksumFile(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('invalid checksum file');

        Sha256File::verify($this->file, '<html>Not Found</html>');
    }
}
