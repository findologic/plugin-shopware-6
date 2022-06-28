<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers;

use FINDOLOGIC\Export\Data\Image;
use FINDOLOGIC\FinSearch\Utils\Utils;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailCollection;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailEntity;
use Shopware\Core\Content\Product\ProductEntity;

trait ImageHelper
{
    /**
     * @return Image[]
     */
    public function getImages(ProductEntity $productEntity): array
    {
        $images = [];
        if (!$productEntity->getMedia() || !$productEntity->getMedia()->count()) {
            $fallbackImage = sprintf(
                '%s/%s',
                getenv('APP_URL'),
                'bundles/storefront/assets/icon/default/placeholder.svg'
            );

            $images[] = new Image($fallbackImage);
            $images[] = new Image($fallbackImage, Image::TYPE_THUMBNAIL);

            return $images;
        }

        $mediaCollection = $productEntity->getMedia();
        $media = $mediaCollection->getMedia();
        $thumbnails = $media->first()->getThumbnails();

        $filteredThumbnails = $this->sortAndFilterThumbnailsByWidth($thumbnails);
        $firstThumbnail = $filteredThumbnails->first();

        $image = $firstThumbnail ?? $media->first();
        $url = $this->getEncodedUrl($image->getUrl());
        $images[] = new Image($url);

        $imageIds = [];
        foreach ($thumbnails as $thumbnail) {
            if (in_array($thumbnail->getMediaId(), $imageIds)) {
                continue;
            }

            $url = $this->getEncodedUrl($thumbnail->getUrl());
            $images[] = new Image($url, Image::TYPE_THUMBNAIL);
            $imageIds[] = $thumbnail->getMediaId();
        }

        return $images;
    }

    private function sortAndFilterThumbnailsByWidth(MediaThumbnailCollection $thumbnails): MediaThumbnailCollection
    {
        $filteredThumbnails = $thumbnails->filter(static function ($thumbnail) {
            return $thumbnail->getWidth() >= 600;
        });

        $filteredThumbnails->sort(function (MediaThumbnailEntity $a, MediaThumbnailEntity $b) {
            return $a->getWidth() <=> $b->getWidth();
        });

        return $filteredThumbnails;
    }

    protected function getEncodedUrl(string $url): string
    {
        $parsedUrl = parse_url($url);
        $urlPath = explode('/', $parsedUrl['path']);
        $encodedPath = array_map('\FINDOLOGIC\FinSearch\Utils\Utils::multiByteRawUrlEncode', $urlPath);
        $parsedUrl['path'] = implode('/', $encodedPath);

        return Utils::buildUrl($parsedUrl);
    }
}
