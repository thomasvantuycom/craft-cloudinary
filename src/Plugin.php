<?php

namespace thomasvantuycom\craftcloudinary;

use craft\base\Plugin as BasePlugin;
use craft\elements\Asset;
use craft\events\DefineBehaviorsEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\services\Fs;
use craft\services\ImageTransforms;
use thomasvantuycom\craftcloudinary\behaviors\CloudinaryUrlBehavior;
use thomasvantuycom\craftcloudinary\fs\CloudinaryFs;
use thomasvantuycom\craftcloudinary\imagetransforms\CloudinaryTransformer;
use yii\base\Event;

class Plugin extends BasePlugin
{
    public string $schemaVersion = '2.0.0';

    public function init(): void
    {
        parent::init();

        $this->_registerFilesystemTypes();
        $this->_registerImageTransformers();
        $this->_defineBehaviors();
    }

    private function _registerFilesystemTypes(): void
    {
        Event::on(Fs::class, Fs::EVENT_REGISTER_FILESYSTEM_TYPES, function(RegisterComponentTypesEvent $event) {
            $event->types[] = CloudinaryFs::class;
        });
    }

    private function _registerImageTransformers(): void
    {
        Event::on(ImageTransforms::class, ImageTransforms::EVENT_REGISTER_IMAGE_TRANSFORMERS, function(RegisterComponentTypesEvent $event) {
            $event->types[] = CloudinaryTransformer::class;
        });
    }

    private function _defineBehaviors(): void
    {
        Event::on(Asset::class, Asset::EVENT_DEFINE_BEHAVIORS, function(DefineBehaviorsEvent $event) {
            $volume = $event->sender->getVolume();
            $fs = $volume->getFs();
            $transformFs = $volume->getTransformFs();
            
            if ($fs instanceof CloudinaryFs || $transformFs instanceof CloudinaryFs) {
                $event->behaviors['cloudinary:url'] = CloudinaryUrlBehavior::class;
            }
        });
    }
}
