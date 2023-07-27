<?php

namespace thomasvantuycom\craftcloudinary\imagetransforms;

use Cloudinary\Cloudinary;
use Cloudinary\Transformation\Gravity;
use Cloudinary\Transformation\Resize;
use Craft;
use craft\base\Component;
use craft\base\imagetransforms\ImageTransformerInterface;
use craft\elements\Asset;
use craft\models\ImageTransform;
use thomasvantuycom\craftcloudinary\fs\CloudinaryFs;

class CloudinaryTransformer extends Component implements ImageTransformerInterface
{
    protected array $MODE_MAP = [
        'crop' => 'crop',
        'fit' => 'fit',
        'letterbox' => 'pad',
        'stretch' => 'scale',
    ];

    protected array $POSITION_MAP = [
        'top-left' => 'north_west',
        'top-center' => 'north',
        'top-right' => 'north_east',
        'center-left' => 'west',
        'center-center' => 'center',
        'center-right' => 'east',
        'bottom-left' => 'south_west',
        'bottom-center' => 'south',
        'bottom-right' => 'south_east',
    ];

    public function getTransformUrl(Asset $asset, ImageTransform $imageTransform, bool $immediately): string
    {
        $fs = $asset->getVolume()->getFs();
        $transformFs = $asset->getVolume()->getTransformFs();

        $isCloudinaryFs = $fs instanceof CloudinaryFs;

        $client = $this->client($transformFs->cloudName, $transformFs->apiKey, $transformFs->apiSecret);

        $transform = $client->image($isCloudinaryFs ? $asset->getPath() : $asset->getUrl());

        if (!$isCloudinaryFs) {
            $transform->deliveryType("fetch");
        }

        $mode = $this->MODE_MAP[$imageTransform->mode];
        $width = $imageTransform->width;
        $height = $imageTransform->height;
        $background = $imageTransform->fill;

        $gravity = $this->POSITION_MAP[$imageTransform->position];

        $quality = $imageTransform->quality ? $imageTransform->quality : 'auto';

        $format = $imageTransform->format ? $imageTransform->format : 'auto';
        
        $progressive = $imageTransform->interlace === 'none' ? false : true;

        $transform->resize(Resize::$mode($width, $height)->gravity(Gravity::compass($gravity)))->quality($quality)->format($format);

        if ($mode === 'pad') {
            $transform->background($background);
        }

        if ($progressive) {
            $transform->addFlag('progressive');
        }

        return $transform->toUrl();
    }

    public function invalidateAssetTransforms(Asset $asset): void
    {
        // ...
    }

    protected function client($cloudName, $apiKey, $apiSecret): Cloudinary
    {
        $config = [
            'cloud' => [
                'cloud_name' => Craft::parseEnv($cloudName),
                'api_key' => Craft::parseEnv($apiKey),
                'api_secret' => Craft::parseEnv($apiSecret),
            ],
        ];

        return new Cloudinary($config);
    }
}
