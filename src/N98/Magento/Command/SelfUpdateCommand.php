<?php

declare(strict_types=1);

namespace N98\Magento\Command;

use Exception;
use N98\Util\Markdown\VersionFilePrinter;
use N98\Util\Sha256File;
use Phar;
use PharException;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use UnexpectedValueException;
use WpOrg\Requests\Hooks;
use WpOrg\Requests\Requests;

/**
 * Self-update command
 *
 * @package N98\Magento\Command
 *
 * @codeCoverageIgnore
 * @author Igor Wiedler <igor@wiedler.ch>
 * @author Christian Münch <c.muench@netz98.de>
 */
class SelfUpdateCommand extends AbstractMagentoCommand
{
    public const RELEASE_DOWNLOAD_URL = 'https://github.com/m-michalis/n98-magerun/releases/latest/download/';

    public const VERSION_TXT_URL_STABLE = self::RELEASE_DOWNLOAD_URL . 'version.txt';

    public const MAGERUN_DOWNLOAD_URL_STABLE = self::RELEASE_DOWNLOAD_URL . 'n98-magerun.phar';

    public const CHECKSUM_DOWNLOAD_URL_STABLE = self::RELEASE_DOWNLOAD_URL . 'n98-magerun.phar.sha256';

    public const CHANGELOG_DOWNLOAD_URL_STABLE = self::RELEASE_DOWNLOAD_URL . 'CHANGELOG.md';

    protected function configure(): void
    {
        $this
            ->setName('self-update')
            ->setAliases(['selfupdate'])
            ->addOption('unstable', null, InputOption::VALUE_NONE, 'Not supported: unstable builds are not published')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Tests if there is a new version without any update.')
            ->setDescription('Updates n98-magerun.phar to the latest version.');
    }

    public function getHelp(): string
    {
        return <<<HELP
The <info>self-update</info> command checks the GitHub releases of
m-michalis/n98-magerun for a newer version and if found, installs it
after verifying its sha256 checksum.

<info>php n98-magerun.phar self-update</info>

HELP;
    }

    /**
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($input->getOption('unstable')) {
            throw new RuntimeException('Unstable builds are not published. Use a release from ' . self::RELEASE_DOWNLOAD_URL);
        }

        $isDryRun = $input->getOption('dry-run');

        if (!$this->getApplication()->isPharMode()) {
            $isDryRun = true;
            $output->writeln('<warning>Self-update is supported only for phar files.</warning>');
            $output->writeln('Dry-run mode is enabled.');
        }

        $localFilename = realpath($_SERVER['argv'][0]) ?: $_SERVER['argv'][0];
        $tempFilename = dirname($localFilename) . '/' . basename($localFilename, '.phar') . '-temp.phar';

        // check for permissions in local filesystem before start connection process
        if (!is_writable($tempDirectory = dirname($tempFilename))) {
            throw new RuntimeException(
                'n98-magerun update failed: the "' . $tempDirectory .
                '" directory used to download the temp file could not be written',
            );
        }

        if (!is_writable($localFilename)) {
            throw new RuntimeException(
                'n98-magerun update failed: the "' . $localFilename . '" file could not be written',
            );
        }

        $latestVersion = trim($this->download(self::VERSION_TXT_URL_STABLE, 'version'));

        if ($this->isOutdatedVersion($latestVersion)) {
            $output->writeln(sprintf('Updating to version <info>%s</info>.', $latestVersion));

            try {
                $this->downloadNewPhar($output, self::MAGERUN_DOWNLOAD_URL_STABLE, $tempFilename);
                Sha256File::verify($tempFilename, $this->download(self::CHECKSUM_DOWNLOAD_URL_STABLE, 'checksum'));
                $this->checkNewPharFile($tempFilename);

                $changelog = $this->getChangelog();

                if ($isDryRun) {
                    @unlink($tempFilename);
                } else {
                    $this->replaceExistingPharFile($tempFilename, $localFilename);
                }

                $output->writeln('');
                $output->writeln('');
                $output->writeln($changelog);
                $output->writeln('<info>---------------------------------</info>');
                $output->writeln('<info>Successfully updated n98-magerun</info>');
                $output->writeln('<info>---------------------------------</info>');

                $this->_exit(0);
            } catch (Exception $exception) {
                @unlink($tempFilename);
                if (!$exception instanceof UnexpectedValueException && !$exception instanceof PharException) {
                    throw $exception;
                }

                $output->writeln('<error>The download is corrupted (' . $exception->getMessage() . ').</error>');
                $output->writeln('<error>Please re-run the self-update command to try again.</error>');

                return Command::FAILURE;
            }
        } else {
            $output->writeln('<info>You are using the latest n98-magerun version.</info>');
        }

        return Command::SUCCESS;
    }

    /**
     * Stop execution
     *
     * This is a workaround to prevent warning of dispatcher after replacing
     * the phar file.
     */
    protected function _exit(int $statusCode = 0): void
    {
        exit($statusCode);
    }

    private function downloadNewPhar(OutputInterface $output, string $remoteUrl, string $tempFilename): void
    {
        $progressBar = new ProgressBar($output);
        $progressBar->setFormat('[%bar%] %current% of %max% bytes downloaded');

        $hooks = new Hooks();

        $response = Requests::head(
            $remoteUrl,
            [],
            [
                'verify' => true,
                'headers' => [
                    'Accept-Encoding' => 'deflate, gzip, br, zstd',
                ],
            ],
        );

        if (!$response->success) {
            throw new RuntimeException('Cannot download phar file: ' . $response->status_code);
        }

        $filesize = $response->headers['content-length'];

        $hooks->register('curl.after_request', function (&$headers, &$info) use (&$filesize): void {
            $filesize = $info['size_download'];
        });

        $progressBar->setMaxSteps((int) $filesize);

        $hooks->register(
            'request.progress',
            function ($data, $responseBytes, $responseByteLimit) use ($progressBar): void {
                $progressBar->setProgress($responseBytes);
            },
        );

        $response = Requests::get(
            $remoteUrl,
            [],
            [
                'blocking' => true,
                'hooks' => $hooks,
                'verify' => true,
                'headers' => [
                    'Accept-Encoding' => 'deflate, gzip, br, zstd',
                ],
            ],
        );

        if (!$response->success) {
            throw new RuntimeException('Cannot download phar file: ' . $response->status_code);
        }

        file_put_contents($tempFilename, $response->body);

        if (!file_exists($tempFilename)) {
            $output->writeln('<error>The download of the new n98-magerun version failed for an unexpected reason</error>');
        }
    }

    private function checkNewPharFile(string $tempFilename): void
    {
        error_reporting(E_ALL); // suppress notices

        @chmod($tempFilename, 0777 & ~umask());
        // test the phar validity
        $phar = new Phar($tempFilename);
        // free the variable to unlock the file
        unset($phar);
    }

    private function replaceExistingPharFile(string $tempFilename, string $localFilename): void
    {
        if (!@rename($tempFilename, $localFilename)) {
            throw new RuntimeException(
                sprintf('Cannot replace existing phar file "%s". Please check permissions.', $localFilename),
            );
        }
    }

    private function download(string $url, string $what): string
    {
        $response = Requests::get(
            $url,
            [],
            [
                'verify' => true,
            ],
        );

        if (!$response->success) {
            throw new RuntimeException(sprintf('Cannot download %s: %s', $what, $response->status_code));
        }

        return $response->body;
    }

    /**
     * Download changelog
     */
    private function getChangelog(): string
    {
        $changeLogContent = $this->download(self::CHANGELOG_DOWNLOAD_URL_STABLE, 'changelog');
        if (!$changeLogContent) {
            return '';
        }

        $versionFilePrinter = new VersionFilePrinter($changeLogContent);

        return $versionFilePrinter->printFromVersion($this->getApplication()->getVersion()) . "\n";
    }

    private function isOutdatedVersion(string $latest): bool
    {
        return version_compare($latest, $this->getApplication()->getVersion(), '>');
    }
}
