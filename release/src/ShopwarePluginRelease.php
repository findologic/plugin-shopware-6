<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Release;

use Bramus\Monolog\Formatter\ColoredLineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
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

    public static function buildLogger(string $name): LoggerInterface
    {
        $logger = new Logger($name);
        $formatter = new ColoredLineFormatter(null, null, null, false, true);
        $logger->pushHandler((new StreamHandler('php://stdout'))->setFormatter($formatter));

        return $logger;
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
        $this->logger->info(
            'This command creates a new tag, which triggers a GitHub workflow that creates and uploads ' .
            'the release into the Shopware Store.'
        );
        $version = $this->releaseTagger->askReleaseVersion();

        $this->logger->info(sprintf('Creating release for version %s...', $version));
        $this->releaseTagger->updateVersionInComposerJsonAndUpdateLock($version);
        $this->releaseTagger->pushVersionChange($version);
    }
}
