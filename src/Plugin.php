<?php

namespace thomasvantuycom\craftcloudinary;

use Craft;
use craft\base\Plugin as BasePlugin;
use craft\controllers\AssetsController;
use craft\elements\Asset;
use craft\elements\db\AssetQuery;
use craft\events\DefineBehaviorsEvent;
use craft\events\DefineRulesEvent;
use craft\events\PopulateElementEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\helpers\Db;
use craft\services\Fs;
use craft\services\ImageTransforms;
use craft\validators\AssetLocationValidator;
use thomasvantuycom\craftcloudinary\behaviors\CloudinaryUrlBehavior;
use thomasvantuycom\craftcloudinary\fs\CloudinaryFs;
use thomasvantuycom\craftcloudinary\imagetransforms\CloudinaryTransformer;
use yii\base\ActionEvent;
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

        // Workaround to reconcile dynamic folders with Craft
        $this->_beforeAssetsControllerAction();
        $this->_defineAssetRules();
        $this->_afterPopulateAsset();
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

    private function _beforeAssetsControllerAction(): void
    {
        Event::on(AssetsController::class, AssetsController::EVENT_BEFORE_ACTION, function(ActionEvent $event) {
            if ($event->action->id === 'move-asset') {
                $assetId = $event->sender->request->getBodyParam('assetId');

                if ($assetId === null) {
                    return;
                }

                $asset = Craft::$app->getAssets()->getAssetById($assetId);

                if ($asset === null) {
                    return;
                }

                $fs = $asset->getVolume()->getFs();

                if (!$fs instanceof CloudinaryFs || !$fs->dynamicFolders) {
                    return;
                }

                $bodyParams = $event->sender->request->getBodyParams();
                $bodyParams['force'] = false;

                $event->sender->request->setBodyParams($bodyParams);
            }

            if ($event->action->id === 'replace-file') {
                $assetId = $event->sender->request->getBodyParam('assetId');
                $sourceAssetId = $event->sender->request->getBodyParam('sourceAssetId');
                $targetFilename = $event->sender->request->getBodyParam('targetFilename');

                if ($assetId !== null || $sourceAssetId === null || $targetFilename === null) {
                    return;
                }

                $sourceAsset = Craft::$app->getAssets()->getAssetById($sourceAssetId);

                if ($sourceAsset === null) {
                    return;
                }

                $volume = $sourceAsset->getVolume();
                $fs = $volume->getFs();

                if (!$fs instanceof CloudinaryFs || !$fs->dynamicFolders) {
                    return;
                }

                $assetId = Asset::find()
                    ->select(['id'])
                    ->volume($volume)
                    ->filename(Db::escapeParam($targetFilename))
                    ->scalar();

                if (!$assetId) {
                    return;
                }

                $bodyParams = $event->sender->request->getBodyParams();
                $bodyParams['assetId'] = $assetId;
                $bodyParams['sourceFolderId'] = $sourceAsset->folderId;
                
                $event->sender->request->setBodyParams($bodyParams);
            }
        });
    }

    private function _defineAssetRules(): void
    {
        Event::on(Asset::class, Asset::EVENT_DEFINE_RULES, function(DefineRulesEvent $event) {
            if (
                Craft::$app->controller instanceof AssetsController &&
                in_array(Craft::$app->controller->action?->id, ['replace-file', 'move-asset'])
            ) {
                foreach ($event->rules as $key => $rule) {
                    if ($rule[1] === AssetLocationValidator::class) {
                        unset($event->rules[$key]);
                    }
                }
            }
        });
    }

    private function _afterPopulateAsset(): void
    {
        Event::on(AssetQuery::class, AssetQuery::EVENT_AFTER_POPULATE_ELEMENT, function(PopulateElementEvent $event) {
            if (
                Craft::$app->controller instanceof AssetsController &&
                Craft::$app->controller->action?->id === 'replace-file'
            ) {
                $sourceFolderId = Craft::$app->getRequest()->getBodyParam('sourceFolderId');

                if ($sourceFolderId === null) {
                    return;
                }

                /** @var Asset $element */
                $element = $event->element;
                $element->newFolderId = $sourceFolderId;

                $event->element = $element;
            }
        });
    }
}
