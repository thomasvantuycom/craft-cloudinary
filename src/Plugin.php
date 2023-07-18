<?php

namespace thomasvantuycom\craftcloudinary;

use Craft;
use craft\base\Plugin as BasePlugin;
use craft\elements\Asset;
use craft\events\GenerateTransformEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\services\Fs;
use craft\services\ImageTransforms;
use thomasvantuycom\craftcloudinary\fs\CloudinaryFs;
use thomasvantuycom\craftcloudinary\imagetransforms\CloudinaryTransformer;
use yii\base\Event;

class Plugin extends BasePlugin
{
    public string $schemaVersion = '1.0.0';

    public function init(): void
    {
        parent::init();

        Craft::$app->onInit(function() {
            $this->attachEventHandlers();
        });
    }

    private function attachEventHandlers(): void
    {
        Event::on(Fs::class, Fs::EVENT_REGISTER_FILESYSTEM_TYPES, function(RegisterComponentTypesEvent $event) {
            $event->types[] = CloudinaryFs::class;
        });

        Event::on(ImageTransforms::class, ImageTransforms::EVENT_REGISTER_IMAGE_TRANSFORMERS, function(RegisterComponentTypesEvent $event) {
            $event->types[] = CloudinaryTransformer::class;
        });

        Event::on(Asset::class, Asset::EVENT_BEFORE_GENERATE_TRANSFORM, function(GenerateTransformEvent $event) {
            if ($event->asset->getVolume()->getFs() instanceof CloudinaryFs) {
                $event->transform->setTransformer(CloudinaryTransformer::class);
            }
        });
    }
}
