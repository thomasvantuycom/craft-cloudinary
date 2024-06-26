<?php

namespace thomasvantuycom\craftcloudinary;

use Craft;
use craft\base\Plugin as BasePlugin;
use craft\elements\Asset;
use craft\events\DefineAssetUrlEvent;
use craft\events\DefineBehaviorsEvent;
use craft\events\GenerateTransformEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\models\ImageTransform;
use craft\services\Fs;
use craft\services\ImageTransforms;
use thomasvantuycom\craftcloudinary\behaviors\CloudinaryBehavior;
use thomasvantuycom\craftcloudinary\fs\CloudinaryFs;
use thomasvantuycom\craftcloudinary\helpers\AssetHelper;
use thomasvantuycom\craftcloudinary\imagetransforms\CloudinaryTransformer;
use yii\base\Event;

class Plugin extends BasePlugin
{
    public string $schemaVersion = '2.0.0';

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
            if ($event->asset->getVolume()->getTransformFs() instanceof CloudinaryFs) {
                $event->transform->setTransformer(CloudinaryTransformer::class);
            }
        });

        Event::on(ImageTransform::class, ImageTransform::EVENT_DEFINE_BEHAVIORS, function(DefineBehaviorsEvent $event) {
            $event->behaviors['cloudinary'] = CloudinaryBehavior::class;
        });

        Event::on(Asset::class, Asset::EVENT_DEFINE_URL, function(DefineAssetUrlEvent $event) {
            $asset = $event->sender;
            $fs = $asset->getVolume()->getFs();

            if ($fs instanceof CloudinaryFs) {
                $event->url = AssetHelper::url($asset, $event->transform);
            }
        });
    }
}
