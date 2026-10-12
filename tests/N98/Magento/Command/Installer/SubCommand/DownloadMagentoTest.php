<?php

declare(strict_types=1);

namespace N98\Magento\Command\Installer\SubCommand;

use N98\Magento\Command\SubCommand\ConfigBag;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Class DownloadMagentoTest
 *
 * @package N98\Magento\Command\Installer\SubCommand
 */
final class DownloadMagentoTest extends TestCase
{
    public function testComposerInstallSkipsDevPackages(): void
    {
        $downloadMagentoStub = $this->createSubCommand(['composer']);

        $this->assertStringContainsString('--no-dev', $downloadMagentoStub->getComposerInstallCommandLine());
    }

    public function testComposerInstallFailureStopsInstallation(): void
    {
        $downloadMagentoStub = $this->createSubCommand([PHP_BINARY, '-r', 'exit(1);', '--']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Non-zero exit code for composer install command');

        $downloadMagentoStub->runComposerInstall();
    }

    /**
     * @param list<string> $composerBin
     */
    private function createSubCommand(array $composerBin): DownloadMagentoStub
    {
        $configBag = new ConfigBag();
        $configBag->offsetSet('composer_bin', $composerBin);

        $downloadMagentoStub = new DownloadMagentoStub();
        $downloadMagentoStub->setConfig($configBag);
        $downloadMagentoStub->setOutput(new BufferedOutput());

        return $downloadMagentoStub;
    }
}

/**
 * Exposes the composer install step of DownloadMagento
 */
final class DownloadMagentoStub extends DownloadMagento
{
    public function getComposerInstallCommandLine(): string
    {
        return $this->createComposerInstallProcess()->getCommandLine();
    }

    public function runComposerInstall(): void
    {
        $this->composerInstall();
    }
}
