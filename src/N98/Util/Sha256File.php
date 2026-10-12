<?php

declare(strict_types=1);

namespace N98\Util;

use UnexpectedValueException;

/**
 * Verifies a file against a sha256sum-style checksum file ("<hash>  <filename>").
 *
 * @package N98\Util
 */
final class Sha256File
{
    /**
     * @throws UnexpectedValueException if the checksum file is malformed or the hash does not match
     */
    public static function verify(string $file, string $checksumFileContents): void
    {
        if (!preg_match('/^\s*([a-f0-9]{64})\b/i', $checksumFileContents, $matches)) {
            throw new UnexpectedValueException('invalid checksum file');
        }

        $actual = hash_file('sha256', $file);
        if ($actual === false || !hash_equals(strtolower($matches[1]), $actual)) {
            throw new UnexpectedValueException(sprintf('checksum mismatch for "%s"', basename($file)));
        }
    }
}
