<?php

namespace thomasvantuycom\craftcloudinary\imagetransforms;

use craft\base\Component;
use craft\base\imagetransforms\ImageTransformerInterface;
use craft\elements\Asset;
use craft\models\ImageTransform;
use thomasvantuycom\craftcloudinary\behaviors\CloudinaryUrlBehavior;

class CloudinaryTransformer extends Component implements ImageTransformerInterface
{
    public function getTransformUrl(Asset $asset, ImageTransform $imageTransform, bool $immediately): string
    {
        $transform = [
            'width' => $imageTransform->width,
            'height' => $imageTransform->height,
            'crop' => $this->_mapModeToCrop($imageTransform->mode, $imageTransform->upscale),
            'gravity' => $this->_mapPositionToGravity($imageTransform->position),
            'flags' => $imageTransform->interlace !== 'none' ? 'progressive' : null,
            'quality' => $imageTransform->quality,
            'background' => $imageTransform->fill,
            'fetch_format' => $imageTransform->format,
        ];

        /**
         * @var CloudinaryUrlBehavior $asset
         */
        return $asset->getCloudinaryUrl($transform);
    }

    public function invalidateAssetTransforms(Asset $asset): void
    {
    }

    private function _mapModeToCrop(string $mode, bool $upscale): string
    {
        return match ($mode) {
            'fit' => $upscale ? 'fit' : 'limit',
            'letterbox' => $upscale ? 'pad' : 'lpad',
            'stretch' => 'scale',
            default => 'fill',
        };
    }

    private function _mapPositionToGravity(string $position): string
    {
        return match ($position) {
            'top-left' => 'north_west',
            'top-center' => 'north',
            'top-right' => 'north_east',
            'center-left' => 'west',
            'center-right' => 'east',
            'bottom-left' => 'south_west',
            'bottom-center' => 'south',
            'bottom-right' => 'south_east',
            default => 'center',
        };
    }
}
