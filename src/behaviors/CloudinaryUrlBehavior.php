<?php

namespace thomasvantuycom\craftcloudinary\behaviors;

use Cloudinary\Asset\AssetType;
use craft\elements\Asset;
use craft\events\DefineAssetUrlEvent;
use craft\events\GenerateTransformEvent;
use thomasvantuycom\craftcloudinary\fs\CloudinaryFs;
use thomasvantuycom\craftcloudinary\helpers\ImageTransforms;
use thomasvantuycom\craftcloudinary\imagetransforms\CloudinaryTransformer;
use yii\base\Behavior;

class CloudinaryUrlBehavior extends Behavior
{
    /**
     * @var Asset
     */
    public $owner;

    public function events(): array
    {
        $volume = $this->owner->getVolume();
        $fs = $volume->getFs();
        $transformFs = $volume->getTransformFs();
        
        $events = [];

        if ($transformFs instanceof CloudinaryFs) {
            $events[Asset::EVENT_BEFORE_DEFINE_URL] = 'beforeDefineUrl';
            $events[Asset::EVENT_BEFORE_GENERATE_TRANSFORM] = 'beforeGenerateTransform';
        }

        if ($fs instanceof CloudinaryFs) {
            $events[Asset::EVENT_DEFINE_URL] = 'defineUrl';
        }

        return $events;
    }
    
    public function beforeDefineUrl(DefineAssetUrlEvent $event): void
    {
        $transform = $event->transform;

        if ($transform === null || ImageTransforms::isNativeTransform($transform)) {
            return;
        }

        $event->url = $this->getCloudinaryUrl($transform);
    }

    public function beforeGenerateTransform(GenerateTransformEvent $event): void
    {
        $event->transform->setTransformer(CloudinaryTransformer::class);
    }

    public function defineUrl(DefineAssetUrlEvent $event): void
    {
        if ($event->url === null) {
            $event->url = $this->getCloudinaryUrl();
        }
    }

    public function getCloudinaryUrl(?array $transform = null): string
    {
        $asset = $this->owner;

        $volume = $asset->getVolume();
        $fs = $volume->getFs();
        $transformFs = $volume->getTransformFs();

        $hasCloudinaryFs = $fs instanceof CloudinaryFs;
        $fsWithConfig = $hasCloudinaryFs ? $fs : $transformFs;

        $publicId = $hasCloudinaryFs ? $volume->getSubPath() . $asset->getPath() : $asset->getUrl();
        $resourceType = $this->_getResourceType();
    
        /**
         * @var CloudinaryFs $fsWithConfig
         */
        $client = $fsWithConfig->getClient();
        $resource = $client->{$resourceType}($publicId);

        if ($transform !== null) {
            $resource->addTransformation($transform);

            if (isset($transform['format'])) {
                $resource->extension($transform['format']);
            }
        }


        if (!$hasCloudinaryFs) {
            $resource->deliveryType("fetch");
        }

        return $resource->toUrl();
    }

    private function _getResourceType(): string
    {
        $mimeType = $this->owner->getMimeType();

        if ($mimeType === null) {
            return AssetType::RAW;
        }

        if (str_starts_with($mimeType, 'image/') || $mimeType === 'application/pdf') {
            return AssetType::IMAGE;
        }
        
        if (str_starts_with($mimeType, "video/") || str_starts_with($mimeType, "audio/")) {
            return AssetType::VIDEO;
        }

        return AssetType::RAW;
    }
}
