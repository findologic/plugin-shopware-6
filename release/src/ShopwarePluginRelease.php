<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Release;

use Psr\Log\LoggerInterface;

class ShopwarePluginRelease
{
    private LoggerInterface $logger;

    private ReleaseTagger $releaseTagger;

    public function __construct(
        LoggerInterface $logger,
        ?ReleaseTagger $releaseTagger = null
    ) {
        $this->logger = $logger;
        $this->releaseTagger = $releaseTagger ?? new ReleaseTagger($logger);
    }

    /**
     * Uploads tag, which triggers the GitHub Actions release job.
     */
    public function updateVersionAndTriggerReleaseJob(): void
    {
        $message = 'This command creates a new tag, which triggers a GitHub workflow that creates and uploads ';
        $message .= 'the release into the Shopware Store.';
        $this->logger->info($message);

        $version = $this->releaseTagger->askReleaseVersion();

        $this->logger->info(sprintf('Creating release for version %s...', $version));
        $this->releaseTagger->updateVersionInComposerJsonAndUpdateLock($version);
        $this->releaseTagger->pushVersionChange($version);
    }
}
