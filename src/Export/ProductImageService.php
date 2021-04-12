<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Export;

use FINDOLOGIC\Export\Data\Image;
use FINDOLOGIC\FinSearch\Utils\Utils;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailCollection;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailEntity;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaCollection;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouterInterface;

class ProductImageService
{
    /** @var RouterInterface */
    private $router;

    public function __construct(RouterInterface $router)
    {
        $this->router = $router;
    }

    /**
     * @return Image[]
     */
    public function getProductImages(ProductEntity $product): array
    {
        $productHasImages = $this->productHasImages($product);
        $childrenHaveImages = $this->hasChildrenWithImages($product);
        if (!$productHasImages && !$childrenHaveImages) {
            return $this->getFallbackImages();
        }

        $images = [];
        if ($productHasImages) {
            $images = $this->getSortedProductImages($product);
        } elseif ($childrenHaveImages) {
            $images = $this->getSortedVariantImages($product);
        }

        return $this->buildImageUrls($images);
    }

    public function productHasImages(ProductEntity $product): bool
    {
        return $product->getMedia() && $product->getMedia()->count() > 0;
    }

    public function getFirstVariantWithImages(ProductEntity $product): ?ProductEntity
    {
        return $product->getChildren()->filter(function (ProductEntity $variant) {
            return $this->productHasImages($variant);
        })->first();
    }

    protected function buildFallbackImage(RequestContext $requestContext): string
    {
        $schemaAuthority = $requestContext->getScheme() . '://' . $requestContext->getHost();
        if ($requestContext->getHttpPort() !== 80) {
            $schemaAuthority .= ':' . $requestContext->getHttpPort();
        } elseif ($requestContext->getHttpsPort() !== 443) {
            $schemaAuthority .= ':' . $requestContext->getHttpsPort();
        }

        return sprintf(
            '%s/%s',
            $schemaAuthority,
            'bundles/storefront/assets/icon/default/placeholder.svg'
        );
    }

    protected function getSortedProductImages(ProductEntity $product): ProductMediaCollection
    {
        $images = $product->getMedia();
        $coverImageId = $product->getCoverId();
        $coverImage = $images->get($coverImageId);

        if (!$coverImage || $images->count() === 1) {
            return $images;
        }

        $images->remove($coverImageId);
        $images->insert(0, $coverImage);

        return $images;
    }

    protected function getSortedVariantImages(ProductEntity $product): ProductMediaCollection
    {
        $variantWithImages = $this->getFirstVariantWithImages($product);

        return $this->getSortedProductImages($variantWithImages);
    }

    /**
     * @param MediaThumbnailEntity|MediaEntity $mediaEntity
     */
    protected function buildImage(Entity $mediaEntity, string $type = Image::TYPE_DEFAULT): Image
    {
        $encodedUrl = $this->getEncodedUrl($mediaEntity->getUrl());

        return new Image($encodedUrl, $type);
    }

    /**
     * Takes invalid URLs that contain special characters such as umlauts, or special UTF-8 characters and
     * encodes them.
     */
    protected function getEncodedUrl(string $url): string
    {
        $parsedUrl = parse_url($url);
        $urlPath = explode('/', $parsedUrl['path']);
        $encodedPath = array_map('\FINDOLOGIC\FinSearch\Utils\Utils::multiByteRawUrlEncode', $urlPath);
        $parsedUrl['path'] = implode('/', $encodedPath);

        return Utils::buildUrl($parsedUrl);
    }

    protected function sortAndFilterThumbnailsByWidth(MediaThumbnailCollection $thumbnails): MediaThumbnailCollection
    {
        $filteredThumbnails = $thumbnails->filter(static function ($thumbnail) {
            return $thumbnail->getWidth() >= 600;
        });

        $filteredThumbnails->sort(function (MediaThumbnailEntity $a, MediaThumbnailEntity $b) {
            return $a->getWidth() <=> $b->getWidth();
        });

        return $filteredThumbnails;
    }

    /**
     * Go through all given thumbnails and only add one thumbnail image. This avoids exporting thumbnails in
     * all various sizes.
     */
    protected function addThumbnailImages(array &$images, MediaThumbnailCollection $thumbnails): void
    {
        $imageIds = [];
        foreach ($thumbnails as $thumbnailEntity) {
            if (in_array($thumbnailEntity->getMediaId(), $imageIds)) {
                continue;
            }

            $images[] = $this->buildImage($thumbnailEntity, Image::TYPE_THUMBNAIL);
            $imageIds[] = $thumbnailEntity->getMediaId();
        }
    }

    protected function buildImageUrls(ProductMediaCollection $collection): array
    {
        $images = [];
        foreach ($collection as $productMedia) {
            $media = $productMedia->getMedia();

            if (!$this->hasMediaUrl($media)) {
                continue;
            }

            if (!$this->hasThumbnails($media)) {
                $images[] = $this->buildImage($media);

                continue;
            }

            $filteredThumbnails = $this->sortAndFilterThumbnailsByWidth($media->getThumbnails());
            // Use the thumbnail as the main image if available, otherwise fallback to the directly assigned image.
            $image = $filteredThumbnails->first() ?? $media;
            if ($image) {
                $images[] = $this->buildImage($image);
            }

            $this->addThumbnailImages($images, $filteredThumbnails);
        }

        return $images;
    }

    protected function hasChildrenWithImages(ProductEntity $product): bool
    {
        $children = $product->getChildren();

        foreach ($children as $variant) {
            if ($this->productHasImages($variant)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return Image[]
     */
    protected function getFallbackImages(): array
    {
        $fallbackImage = $this->buildFallbackImage($this->router->getContext());

        return [
            new Image($fallbackImage),
            new Image($fallbackImage, Image::TYPE_THUMBNAIL)
        ];
    }

    protected function hasMediaUrl(MediaEntity $media): bool
    {
        return $media && $media->getUrl();
    }

    protected function hasThumbnails(MediaEntity $media): bool
    {
        return $media->getThumbnails() && $media->getThumbnails()->count() > 0;
    }
}
