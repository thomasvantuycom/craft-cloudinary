<?php

namespace thomasvantuycom\craftcloudinary\imagetransforms;

use Cloudinary\Cloudinary;
use craft\base\Component;
use craft\base\imagetransforms\ImageTransformerInterface;
use craft\elements\Asset;
use craft\helpers\App;
use craft\models\ImageTransform;
use thomasvantuycom\craftcloudinary\behaviors\CloudinaryBehavior;
use thomasvantuycom\craftcloudinary\fs\CloudinaryFs;

class CloudinaryTransformer extends Component implements ImageTransformerInterface
{
    public function getTransformUrl(Asset $asset, ImageTransform|CloudinaryBehavior $imageTransform, bool $immediately): string
    {
        $fs = $asset->getVolume()->getFs();
        $transformFs = $asset->getVolume()->getTransformFs();
        
        $isCloudinaryFs = $fs instanceof CloudinaryFs;
        $hasBaseFolder = $isCloudinaryFs && $fs->baseFolder;

        $publicId = $asset->getUrl();
        if ($isCloudinaryFs) {
            $publicId = $asset->getPath();
        }
        if ($hasBaseFolder) {
            $publicId = App::parseEnv($fs->baseFolder) . '/' . $publicId;
        }
        
        /** @var CloudinaryFs $transformFs */
        $client = $this->client($transformFs->cloudName, $transformFs->apiKey, $transformFs->apiSecret, $transformFs->url);
        $transform = $client->image($publicId);

        $qualifiers = [
            'angle' => $imageTransform->angle,
            'aspect_ratio' => $imageTransform->aspectRatio,
            'background' => $imageTransform->fill ?? $imageTransform->background,
            'border' => $imageTransform->border,
            'color' => $imageTransform->color,
            'color_space' => $imageTransform->colorSpace,
            'crop' => $imageTransform->crop,
            'default_image' => $imageTransform->defaultImage,
            'delay' => $imageTransform->delay,
            'density' => $imageTransform->density,
            'dpr' => $imageTransform->dpr,
            'effect' => $imageTransform->effect,
            'fetch_format' => $imageTransform->fetchFormat,
            'flags' => $imageTransform->flags,
            'gravity' => $imageTransform->gravity,
            'height' => $imageTransform->height,
            'overlay' => $imageTransform->overlay,
            'opacity' => $imageTransform->opacity,
            'page' => $imageTransform->page,
            'prefix' => $imageTransform->prefix,
            'quality' => $imageTransform->quality,
            'radius' => $imageTransform->radius,
            'transformation' => $imageTransform->transformation,
            'underlay' => $imageTransform->underlay,
            'width' => $imageTransform->width,
            'x' => $imageTransform->x,
            'y' => $imageTransform->y,
            'zoom' => $imageTransform->zoom,
        ];

        if ($qualifiers['crop'] === null) {
            switch ($imageTransform->mode) {
                case 'crop':
                    $mode = 'fill';
                    break;
                case 'fit':
                    $mode = $imageTransform->upscale ? 'fit' : 'limit';
                    break;
                case 'letterbox':
                    $mode = $imageTransform->upscale ? 'pad' : 'lpad';
                    break;
                case 'stretch':
                    $mode = 'scale';
                    break;
            }
            $qualifiers['crop'] = $mode;
        }

        if ($qualifiers['gravity'] === null) {
            switch ($imageTransform->position) {
                case 'top-left':
                    $compassPosition = 'north_west';
                    break;
                case 'top-center':
                    $compassPosition = 'north';
                    break;
                case 'top-right':
                    $compassPosition = 'north_east';
                    break;
                case 'center-left':
                    $compassPosition = 'west';
                    break;
                case 'center-center':
                    $compassPosition = 'center';
                    break;
                case 'center-right':
                    $compassPosition = 'east';
                    break;
                case 'bottom-left':
                    $compassPosition = 'south_west';
                    break;
                case 'bottom-center':
                    $compassPosition = 'south';
                    break;
                case 'bottom-right':
                    $compassPosition = 'south_east';
                    break;
            }
            $qualifiers['gravity'] = $compassPosition;
        }
        
        if ($imageTransform->interlace !== 'none') {
            if ($qualifiers['flags'] !== null) {
                $qualifiers['flags'] .= '.progressive';
            } else {
                $qualifiers['flags'] = 'progressive';
            }
        }

        if ($imageTransform->quality === null || $imageTransform->quality === 0) {
            $qualifiers['quality'] = 'auto';
        }

        if ($qualifiers['background'] === null) {
            if ($imageTransform->fill) {
                $qualifiers['background'] = $imageTransform->fill;
            }
        }

        if ($qualifiers['fetch_format'] === null) {
            if ($imageTransform->format) {
                $qualifiers['fetch_format'] = $imageTransform->format;
            }
        }

        $transform->addActionFromQualifiers($qualifiers);

        if (!$isCloudinaryFs) {
            $transform->deliveryType("fetch");
        }

        return $transform->toUrl();
    }

    public function invalidateAssetTransforms(Asset $asset): void
    {
        // ...
    }

    protected function client($cloudName, $apiKey, $apiSecret, $url): Cloudinary
    {
        $config = [
            'cloud' => [
                'cloud_name' => App::parseEnv($cloudName),
                'api_key' => App::parseEnv($apiKey),
                'api_secret' => App::parseEnv($apiSecret),
            ],
            'url' => [
                'analytics' => false,
                'forceVersion' => false,
            ],
        ];

        if ($url) {
            $hostname = parse_url(App::parseEnv($url), PHP_URL_HOST);

            if ($hostname !== 'res.cloudinary.com') {
                $config['private_cdn'] = true;
                $config['secure_distribution'] = $hostname;
            }
        }

        return new Cloudinary($config);
    }
}
