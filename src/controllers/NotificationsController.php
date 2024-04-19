<?php

namespace thomasvantuycom\craftcloudinary\controllers;

use Cloudinary\Configuration\Configuration;
use Cloudinary\Utils\SignatureVerifier;
use Craft;
use craft\elements\Asset;
use craft\helpers\App;
use craft\helpers\Assets;
use craft\records\Asset as AssetRecord;
use craft\records\VolumeFolder as VolumeFolderRecord;
use craft\web\Controller;
use thomasvantuycom\craftcloudinary\fs\CloudinaryFs;
use Throwable;
use yii\web\Response;

class NotificationsController extends Controller
{
    public $enableCsrfValidation = false;

    protected array|bool|int $allowAnonymous = true;

    public function actionReceive(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        try {
            // Verify that volume is valid
            $volumeHandle = $this->request->getRequiredQueryParam('volume');
            $volume = Craft::$app->getVolumes()->getVolumeByHandle($volumeHandle);
            $fs = $volume->getFs();

            if (!($fs instanceof CloudinaryFs)) {
                return $this->asFailure();
            }

            // Verify notification signature
            Configuration::instance()->cloud->apiSecret = App::parseEnv($fs->apiSecret);
            
            $body = $this->request->getRawBody();
            $timestamp = $this->request->getHeaders()->get('X-Cld-Timestamp');
            $signature = $this->request->getHeaders()->get('X-Cld-Signature');

            if (SignatureVerifier::verifyNotificationSignature($body, $timestamp, $signature) === false) {
                return $this->asFailure();
            }

            // Verify that notification is triggered by Console UI action
            $triggeredBy = $this->request->getRequiredBodyParam('notification_context.triggered_by.source');
            if ($triggeredBy !== 'ui') {
                return $this->asFailure();
            }

            // Handle notification
            $type = $this->request->getRequiredBodyParam('notification_type');
            
            if ($type === 'create_folder') {
                $volumeId = $volume->id;
                $name = $this->request->getRequiredBodyParam('folder_name');
                $path = $this->request->getRequiredBodyParam('folder_path');

                // Check if folder exists
                $folderRecord = VolumeFolderRecord::findOne([
                    'volumeId' => $volumeId,
                    'path' => $path . '/',
                ]);

                if ($folderRecord !== null) {
                    return $this->asSuccess();
                }

                // Get parent folder ID
                $parentFolderRecord = VolumeFolderRecord::findOne([
                    'volumeId' => $volumeId,
                    'path' => ($name === $path) ? '' : dirname($path) . '/',
                ]);

                $parentId = $parentFolderRecord->id;

                // Store folder
                $newFolderRecord = new VolumeFolderRecord([
                    'parentId' => $parentId,
                    'volumeId' => $volumeId,
                    'name' => $name,
                    'path' => $path . '/',
                ]);
                $newFolderRecord->save();

                return $this->asSuccess();
            }

            if ($type === 'delete_folder') {
                $path = $this->request->getRequiredBodyParam('folder_path');

                // Delete folder
                VolumeFolderRecord::deleteAll([
                    'volumeId' => $volume->id,
                    'path' => $path . '/',
                ]);

                return $this->asSuccess();
            }

            if ($type === 'upload') {
                $volumeId = $volume->id;
                $publicId = $this->request->getRequiredBodyParam('public_id');
                $format = $this->request->getRequiredBodyParam('format');
                $folder = $this->request->getRequiredBodyParam('folder');
                $size = $this->request->getRequiredBodyParam('bytes');

                // Get folder ID
                $folderRecord = VolumeFolderRecord::findOne([
                    'volumeId' => $volumeId,
                    'path' => $folder === '' ? '' : $folder . '/',
                ]);

                // Check if asset exists
                $folderId = $folderRecord->id;
                $filename = basename($publicId) . '.' . $format;

                $assetRecord = AssetRecord::findOne([
                    'volumeId' => $volumeId,
                    'folderId' => $folderId,
                    'filename' => $filename,
                ]);

                if ($assetRecord !== null) {
                    return $this->asSuccess();
                }

                // Store Asset
                $kind = Assets::getFileKindByExtension($filename);

                $asset = new Asset([
                    'volumeId' => $volumeId,
                    'folderId' => $folderId,
                    'filename' => $filename,
                    'kind' => $kind,
                    'size' => $size,
                ]);

                if ($kind === Asset::KIND_IMAGE) {
                    $asset->width = $this->request->getRequiredBodyParam('width');
                    $asset->height = $this->request->getRequiredBodyParam('height');
                }
                
                $asset->setScenario(Asset::SCENARIO_INDEX);
                Craft::$app->getElements()->saveElement($asset);

                return $this->asSuccess();
            }

            if ($type === 'delete') {
                $volumeId = $volume->id;
                $resources = $this->request->getRequiredBodyParam('resources');

                foreach ($resources as $resource) {
                    $resourceType = $resource['resource_type'];
                    $publicId = $resource['public_id'];
                    $folder = $resource['folder'];

                    $filename = basename($publicId);
                    $folderPath = $folder === '' ? '' : $folder . '/';

                    $assetQuery = Asset::find()
                        ->volumeId($volumeId)
                        ->folderPath($folderPath);
                    
                    if ($resourceType === 'raw') {
                        $assetQuery->filename($filename);
                    } else {
                        $assetQuery->filename("$filename.*");
                        if ($resourceType === 'image') {
                            $assetQuery->kind('image');
                        } else {
                            $assetQuery->kind(['video', 'audio']);
                        }
                    }

                    $asset = $assetQuery->one();
                        
                    if($asset !== null) {
                        Craft::$app->getElements()->deleteElement($asset);
                    }
                }
            }

            if ($type === 'rename') {
                $volumeId = $volume->id;
                $resourceType = $this->request->getRequiredBodyParam('resource_type');
                $fromPublicId = $this->request->getRequiredBodyParam('from_public_id');
                $toPublicId = $this->request->getRequiredBodyParam('to_public_id');
                $folder = $this->request->getRequiredBodyParam('folder');
                
                $fromFilename = basename($fromPublicId);
                $fromFolder = dirname($fromPublicId);
                $fromFolderPath = $fromFolder === '.' ? '' : $fromFolder . '/';
                $toFilename = basename($toPublicId);
                $toFolderPath = $folder === '' ? '' : $folder . '/';

                $assetQuery = Asset::find()
                    ->volumeId($volumeId)
                    ->folderPath($fromFolderPath);
                
                if ($resourceType === 'raw') {
                    $assetQuery->filename($fromFilename);
                } else {
                    $assetQuery->filename("$fromFilename.*");
                    if ($resourceType === 'image') {
                        $assetQuery->kind('image');
                    } else {
                        $assetQuery->kind(['video', 'audio']);
                    }
                }

                $asset = $assetQuery->one();

                if ($asset !== null) {
                    if ($fromFolderPath !== $toFolderPath) {
                        $folderRecord = VolumeFolderRecord::findOne([
                            'volumeId' => $volumeId,
                            'path' => $toFolderPath,
                        ]);

                        $asset->folderId = $folderRecord->id;
                    }

                    if ($fromFilename !== $toFilename) {
                        if ($resourceType === 'raw') {
                            $asset->filename = $toFilename;
                        } else {
                            $extension = pathinfo($asset->filename, PATHINFO_EXTENSION);
                            $asset->filename = "$toFilename.$extension";
                        }
                    }

                    Craft::$app->getElements()->saveElement($asset);
                }
            }
        } catch (Throwable $error) {
            return $this->asFailure();
        }

        return $this->asSuccess();
    }
}
