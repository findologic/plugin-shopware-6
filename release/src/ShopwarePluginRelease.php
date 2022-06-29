<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Release;

use Psr\Log\LoggerInterface;

class ShopwarePluginRelease
{
    /** @var LoggerInterface */
    private $logger;

    /** @var ReleaseBundler */
    private $releaseBundler;

    /** @var ReleaseTagger */
    private $releaseTagger;

    public function __construct(
        LoggerInterface $logger,
        ?ReleaseBundler $releaseBundler = null,
        ?ReleaseTagger $releaseTagger = null
    ) {
        $this->logger = $logger;
        $this->releaseBundler = $releaseBundler ?? new ReleaseBundler($logger);
        $this->releaseTagger = $releaseTagger ?? new ReleaseTagger($logger);
    }

    public function buildReleaseZipFile(): string
    {
        $this->releaseBundler->resetStateToTagInComposerJson();

        return $this->releaseBundler->bundleToZip();
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
