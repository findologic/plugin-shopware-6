<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Adapters\Export;

use FINDOLOGIC\Export\Data\Image;
use FINDOLOGIC\Export\XML\XMLItem;
use FINDOLOGIC\FinSearch\Export\Adapters\ImagesAdapter;
use FINDOLOGIC\FinSearch\Export\FindologicProductFactory;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\ImageHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\ProductHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\SalesChannelHelper;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class ImagesAdapterTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelHelper;
    use ProductHelper;
    use ImageHelper;

    /** @var SalesChannelContext */
    protected $salesChannelContext;

    protected function setUp(): void
    {
        parent::setUp();

        $this->salesChannelContext = $this->buildSalesChannelContext();
    }

    public function testImagesContainsTheImagesOfTheProduct(): void
    {
        $adapter = $this->getContainer()->get(ImagesAdapter::class);
        $product = $this->createTestProduct();
        $expectedImages = $this->getImages($product);

        $images = $adapter->adapt($product);

        $this->assertEquals($expectedImages, $images);
    }

    /**
     * @dataProvider thumbnailProvider
     */
    public function testCorrectThumbnailImageIsAdapted(array $thumbnails, array $expectedImages): void
    {
        $productData = ['cover' => ['media' => ['thumbnails' => $thumbnails]]];
        $productEntity = $this->createTestProduct(
            $productData,
            false,
            true
        );

        $adapter = $this->getContainer()->get(ImagesAdapter::class);

        $images = $adapter->adapt($productEntity);

        $actualImages = $this->urlDecodeImages($images);
        $this->assertCount(count($expectedImages), $actualImages);

        foreach ($expectedImages as $key => $expectedImage) {
            $this->assertStringContainsString($expectedImage['url'], $actualImages[$key]->getUrl());
            $this->assertSame($expectedImage['type'], $actualImages[$key]->getType());
        }
    }

    /**
     * @dataProvider widthSizesProvider
     */
    public function testImageThumbnailsAreFilteredAndSortedByWidth(array $widthSizes, array $expected): void
    {
        $thumbnails = $this->generateThumbnailData($widthSizes);
        $productEntity = $this->createTestProduct(['cover' => ['media' => ['thumbnails' => $thumbnails]]], false, true);
        $mediaCollection = $productEntity->getMedia();
        $media = $mediaCollection->getMedia();
        $thumbnailCollection = $media->first()->getThumbnails();

        $width = [];
        $filteredThumbnails = $this->sortAndFilterThumbnailsByWidth($thumbnailCollection);
        foreach ($filteredThumbnails as $filteredThumbnail) {
            $width[] = $filteredThumbnail->getWidth();
        }

        $this->assertSame($expected, $width);
    }

    /**
     * URL decodes images. This avoids having to debug the difference between URL encoded characters.
     *
     * @param Image[] $images
     *
     * @return Image[]
     */
    private function urlDecodeImages(array $images): array
    {
        return array_map(function (Image $image) {
            return new Image(urldecode($image->getUrl()), $image->getType(), $image->getUsergroup());
        }, $images);
    }

    private function generateThumbnailData(array $sizes): array
    {
        $thumbnails = [];
        foreach ($sizes as $width) {
            $thumbnails[] = [
                'width' => $width,
                'height' => 100,
                'highDpi' => false,
                'url' => 'https://via.placeholder.com/100'
            ];
        }

        return $thumbnails;
    }

    public function thumbnailProvider(): array
    {
        return [
            '3 thumbnails 400x400, 600x600 and 1000x100, the image of width 600 is taken' => [
                'thumbnails' => [
                    [
                        'width' => 400,
                        'height' => 400,
                        'highDpi' => false,
                        'url' => 'https://via.placeholder.com/400'
                    ],
                    [
                        'width' => 600,
                        'height' => 600,
                        'highDpi' => false,
                        'url' => 'https://via.placeholder.com/600'
                    ],
                    [
                        'width' => 1000,
                        'height' => 100,
                        'highDpi' => false,
                        'url' => 'https://via.placeholder.com/100'
                    ]
                ],
                'expectedImages' => [
                    [
                        'url' => '600x600',
                        'type' => Image::TYPE_DEFAULT
                    ],
                    [
                        'url' => '600x600',
                        'type' => Image::TYPE_THUMBNAIL
                    ],
                ]
            ],
            '2 thumbnails 800x800 and 2000x200, the image of width 800 is taken' => [
                'thumbnails' => [
                    [
                        'width' => 800,
                        'height' => 800,
                        'highDpi' => false,
                        'url' => 'https://via.placeholder.com/800'
                    ],
                    [
                        'width' => 2000,
                        'height' => 200,
                        'highDpi' => false,
                        'url' => 'https://via.placeholder.com/200'
                    ]
                ],
                'expectedImages' => [
                    [
                        'url' => '800x800',
                        'type' => Image::TYPE_DEFAULT
                    ],
                    [
                        'url' => '800x800',
                        'type' => Image::TYPE_THUMBNAIL
                    ],
                ]
            ],
            '3 thumbnails 100x100, 200x200 and 400x400, the image directly assigned to the product is taken' => [
                'thumbnails' => [
                    [
                        'width' => 100,
                        'height' => 100,
                        'highDpi' => false,
                        'url' => 'https://via.placeholder.com/100'
                    ],
                    [
                        'width' => 200,
                        'height' => 200,
                        'highDpi' => false,
                        'url' => 'https://via.placeholder.com/200'
                    ],
                    [
                        'width' => 400,
                        'height' => 400,
                        'highDpi' => false,
                        'url' => 'https://via.placeholder.com/400'
                    ]
                ],
                'expectedImages' => [
                    [
                        'url' => 'findologic.png',
                        'type' => Image::TYPE_DEFAULT
                    ],
                ]
            ],
            '0 thumbnails, the automatically generated thumbnail is taken' => [
                'thumbnails' => [],
                'expectedImages' => [
                    [
                        'url' => '600x600',
                        'type' => Image::TYPE_DEFAULT
                    ],
                    [
                        'url' => '600x600',
                        'type' => Image::TYPE_THUMBNAIL
                    ],
                ]
            ],
            'Same thumbnail exists in various sizes will only export one size' => [
                'thumbnails' => [
                    [
                        'width' => 800,
                        'height' => 800,
                        'highDpi' => false,
                        'url' => 'https://via.placeholder.com/800'
                    ],
                    [
                        'width' => 1000,
                        'height' => 1000,
                        'highDpi' => false,
                        'url' => 'https://via.placeholder.com/1000'
                    ],
                    [
                        'width' => 1200,
                        'height' => 1200,
                        'highDpi' => false,
                        'url' => 'https://via.placeholder.com/1200'
                    ],
                    [
                        'width' => 1400,
                        'height' => 1400,
                        'highDpi' => false,
                        'url' => 'https://via.placeholder.com/1400'
                    ],
                    [
                        'width' => 1600,
                        'height' => 1600,
                        'highDpi' => false,
                        'url' => 'https://via.placeholder.com/1600'
                    ],
                    [
                        'width' => 1800,
                        'height' => 1800,
                        'highDpi' => false,
                        'url' => 'https://via.placeholder.com/1800'
                    ],
                ],
                'expectedImages' => [
                    [
                        'url' => '800x800',
                        'type' => Image::TYPE_DEFAULT
                    ],
                    [
                        'url' => '800x800',
                        'type' => Image::TYPE_THUMBNAIL
                    ],
                ]
            ]
        ];
    }

    public function widthSizesProvider(): array
    {
        return [
            'Max 600 width is provided' => [
                'widthSizes' => [100, 200, 300, 400, 500, 600],
                'expected' => [600]
            ],
            'Min 600 width is provided' => [
                'widthSizes' => [600, 800, 200, 500],
                'expected' => [600, 800]
            ],
            'Random width are provided' => [
                'widthSizes' => [800, 100, 650, 120, 2000, 1000],
                'expected' => [650, 800, 1000, 2000]
            ],
            'Less than 600 width is provided' => [
                'widthSizes' => [100, 200, 300, 500],
                'expected' => []
            ]
        ];
    }
}
