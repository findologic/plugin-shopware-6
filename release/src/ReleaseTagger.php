<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Release;

use Psr\Log\LoggerInterface;

class ReleaseTagger
{
    /** @var LoggerInterface */
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function askReleaseVersion(): string
    {
        $this->logger->info('Please enter the version that should be released:');
        $version = $this->getReleaseVersionFromUser();
        $this->askForConfirmation($version);

        return $version;
    }

    public function updateVersionInComposerJsonAndUpdateLock(string $version): void
    {
        $this->logger->info('Updating composer.json...');
        $this->updateComposerJson($version);
        $this->logger->info('Updating composer.lock...');
        $this->updateComposerLock();
    }

    public function pushVersionChange(string $version): void
    {
        $this->logger->info('Committing and pushing the version change...');
        $this->commitVersionChange($version);
        $this->logger->info('Creating and committing tag...');
        $this->createAndCommitTag($version);
    }

    private function updateComposerJson(string $version): void
    {
        $data = Utils::getComposerJsonData();
        $data['version'] = $version;

        Utils::storeComposerJsonData($data);
    }

    private function updateComposerLock(): void
    {
        exec('composer update --lock');
    }

    private function commitVersionChange(string $version): void
    {
        exec('git reset HEAD -- .');
        exec('git add composer.json composer.lock');
        exec(sprintf('git commit -m "Bump version %s"', $version));
        exec('git push');
    }

    private function createAndCommitTag(string $version): void
    {
        exec(sprintf('git tag %s', $version));
        exec(sprintf('git push origin %s', $version));
    }

    private function getReleaseVersionFromUser(): string
    {
        $version = readline('Version number (e.g. 1.2.3 or 1.2.3-RC3): ');
        if (!is_string($version)) {
            $this->logger->error('Could not parse version number. Please try again.');
            exit(1);
        }

        return $version;
    }

    private function askForConfirmation(string $version): void
    {
        $value = mb_strtolower(readline(sprintf(
            'Entered version is "%s". Proceed? (y/N): ',
            $version
        )));

        if ($value !== 'y') {
            $this->logger->error('Release creation aborted by user.');
            exit(1);
        }
    }
}
