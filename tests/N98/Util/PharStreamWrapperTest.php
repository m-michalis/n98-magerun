<?php

declare(strict_types=1);

namespace N98\Util;

use PHPUnit\Framework\TestCase;

/**
 * Class PharStreamWrapperTest
 *
 * @package N98\Util
 */
final class PharStreamWrapperTest extends TestCase
{
    protected function tearDown(): void
    {
        if (!$this->isPharWrapperRegistered()) {
            stream_wrapper_restore('phar');
        }
    }

    public function testRestoresWrapperWhenRunningFromPhar(): void
    {
        stream_wrapper_unregister('phar');

        $this->assertTrue(PharStreamWrapper::restoreIfRunningFromPhar('/usr/local/bin/n98-magerun.phar'));
        $this->assertTrue($this->isPharWrapperRegistered());
    }

    public function testKeepsWrapperDisabledWhenNotRunningFromPhar(): void
    {
        stream_wrapper_unregister('phar');

        $this->assertFalse(PharStreamWrapper::restoreIfRunningFromPhar(''));
        $this->assertFalse($this->isPharWrapperRegistered());
    }

    public function testDoesNothingWhenWrapperIsAlreadyRegistered(): void
    {
        $this->assertTrue($this->isPharWrapperRegistered());

        $this->assertFalse(PharStreamWrapper::restoreIfRunningFromPhar('/usr/local/bin/n98-magerun.phar'));
        $this->assertTrue($this->isPharWrapperRegistered());
    }

    private function isPharWrapperRegistered(): bool
    {
        return in_array('phar', stream_get_wrappers(), true);
    }
}
