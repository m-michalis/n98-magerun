<?php

declare(strict_types=1);

namespace N98\Util;

/**
 * Re-enables the phar:// stream wrapper for magerun's own phar.
 *
 * OpenMage 20 unregisters the wrapper in app/bootstrap.php to prevent phar deserialization
 * attacks (magento-lts#5461). When magerun runs as a phar, every class not yet loaded lives
 * behind phar://, so the wrapper must be restored after bootstrapping Magento.
 *
 * @package N98\Util
 */
final class PharStreamWrapper
{
    private const PROTOCOL = 'phar';

    /**
     * @param string $runningPhar path of the running phar, as returned by \Phar::running(false)
     *
     * @return bool true if the wrapper was restored
     */
    public static function restoreIfRunningFromPhar(string $runningPhar): bool
    {
        if ($runningPhar === '' || in_array(self::PROTOCOL, stream_get_wrappers(), true)) {
            return false;
        }

        return stream_wrapper_restore(self::PROTOCOL);
    }
}
