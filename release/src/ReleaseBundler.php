<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Release;

use Psr\Log\LoggerInterface;

class ReleaseBundler
{
    private const SUPPORTED_SHOPWARE_VERSIONS = '^6.2||^6.3||^6.4';

    private const SYS_TMP_DIR = '/tmp';
    private const TMP_DIR = self::SYS_TMP_DIR . '/FinSearch';

    private const IGNORED = [
        'FinSearch/.git/\*',
        'FinSearch/.github/\*',
        'FinSearch/.gitignore',
        'FinSearch/.idea/\*',
        'FinSearch/release/\*',
        'FinSearch/composer.lock',
        'FinSearch/phpcs.xml',
        'FinSearch/phpunit.legacy.xml.dist',
        'FinSearch/phpunit.xml.dist',
        'FinSearch/tests/\*',
        '*.zip',
        '*.phar',
    ];

    /** @var LoggerInterface */
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function bundleToZip(): string
    {
        $this->logger->info('Copying files...');
        $this->copyFilesToTempFolder();
        $this->logger->info('Installing dependencies (may remove dev dependencies)...');
        $this->installDependencies();
        $this->logger->info('Adding shopware/core and shopware/storefront to the composer.json.');
        $this->addShopwareCoreAndStorefrontDependencies();
        $this->logger->info('Creating release zip file...');
        $zipFileDest = $this->createZipFile();
        $this->cleanUpTempFolderAndResetDir();

        return $zipFileDest;
    }

    public function resetStateToTagInComposerJson(): void
    {
        $version = $this->getVersionFromComposerJson();
        $this->logger->info(sprintf('Creating a release for version %s', $version));
        $this->logger->info('Running "git stash" to preserve local changes.');
        $this->stashAndCheckoutVersion($version);
    }

    private function getVersionFromComposerJson(): string
    {
        $data = Utils::getComposerJsonData();

        return $data['version'];
    }

    private function stashAndCheckoutVersion(string $version): void
    {
        $this->doGitStash();
        $this->doCheckoutVersion($version);
    }

    private function doGitStash(): void
    {
        exec('git stash');
        exec('git fetch --all --tags --prune');
    }

    private function doCheckoutVersion(string $version): void
    {
        $tag = exec(sprintf('git tag -l %s', $version));

        if (!$tag) {
            $this->logger->error(sprintf('Tag %s not found.', $version));
            exit(1);
        }

        exec(sprintf('git checkout tags/%s', $tag));
    }

    private function copyFilesToTempFolder(): void
    {
        exec(sprintf('cp -rf . %s', self::TMP_DIR));
        chdir(self::TMP_DIR);
    }

    private function installDependencies(): void
    {
        exec('composer install --no-dev --optimize-autoloader --prefer-dist');
    }

    private function addShopwareCoreAndStorefrontDependencies(): void
    {
        $data = Utils::getComposerJsonData();
        $data['require']['shopware/core'] = self::SUPPORTED_SHOPWARE_VERSIONS;
        $data['require']['shopware/storefront'] = self::SUPPORTED_SHOPWARE_VERSIONS;

        file_put_contents(
            self::TMP_DIR . '/composer.json',
            Utils::toJsonString($data)
        );
    }

    private function createZipFile(): string
    {
        chdir(self::SYS_TMP_DIR);

        $zipFileDest = sprintf('%s/FinSearch.zip', __DIR__ . '/../..');
        exec(sprintf('zip -r9 %s FinSearch -x %s', $zipFileDest, implode(' ', self::IGNORED)));

        return $zipFileDest;
    }

    private function cleanUpTempFolderAndResetDir(): void
    {
        exec(sprintf('rm -rf "%s"', self::TMP_DIR));
        chdir(__DIR__ . '/../..');
    }
}
