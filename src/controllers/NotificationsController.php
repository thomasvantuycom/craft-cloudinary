<?php

namespace thomasvantuycom\craftcloudinary\controllers;

use Cloudinary\Configuration\Configuration;
use Cloudinary\Utils\SignatureVerifier;
use Craft;
use craft\db\Query;
use craft\db\Table;
use craft\elements\Asset;
use craft\helpers\App;
use craft\helpers\Assets;
use craft\records\VolumeFolder as VolumeFolderRecord;
use craft\web\Controller;
use InvalidArgumentException;
use thomasvantuycom\craftcloudinary\fs\CloudinaryFs;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class NotificationsController extends Controller
{
    public $enableCsrfValidation = false;

    protected array|bool|int $allowAnonymous = ['process'];

    public function actionProcess(): Response
    {
        $this->requirePostRequest();

        // Verify volume
        $volumeId = $this->request->getRequiredQueryParam('volume');

        $volume = Craft::$app->getVolumes()->getVolumeById($volumeId);

        if ($volume === null) {
            throw new NotFoundHttpException('Volume not found');
        }

        $fs = $volume->getFs();

        if (!$fs instanceof CloudinaryFs) {
            throw new BadRequestHttpException('Invalid volume');
        }

        // Verify signature
        Configuration::instance()->cloud->apiSecret = App::parseEnv($fs->apiSecret);

        $body = $this->request->getRawBody();
        $timestamp = $this->request->getHeaders()->get('X-Cld-Timestamp');
        $signature = $this->request->getHeaders()->get('X-Cld-Signature');

        try {
            if (SignatureVerifier::verifyNotificationSignature($body, $timestamp, $signature) === false) {
                throw new BadRequestHttpException('Invalid signature');
            }
        } catch (InvalidArgumentException $error) {
            throw new BadRequestHttpException($error->getMessage(), 0, $error);
        }

        // Process notification
        $notificationType = $this->request->getRequiredBodyParam('notification_type');
        $subpath = $volume->getSubpath(false);

        switch ($notificationType) {
            case 'create_folder':
                return $this->_processCreateFolder($volumeId, $subpath);
            case 'delete_folder':
                return $this->_processDeleteFolder($volumeId, $subpath);
            case 'upload':
                return $this->_processUpload($volumeId, $subpath);
            case 'delete':
                return $this->_processDelete($volumeId, $subpath);
            case 'rename':
                return $this->_processRename($volumeId, $subpath);
            default:
                return $this->asSuccess();
        }
    }

    private function _processCreateFolder($volumeId, $subpath): Response
    {
        $name = $this->request->getRequiredBodyParam('folder_name');
        $path = $this->request->getRequiredBodyParam('folder_path');

        if (!empty($subpath)) {
            if (!str_starts_with($path, $subpath . '/')) {
                return $this->asSuccess();
            }

            $path = substr($path, strlen($subpath) + 1);
        }

        // Check if folder exists
        $existingFolderQuery = (new Query())
            ->from([Table::VOLUMEFOLDERS])
            ->where([
                'volumeId' => $volumeId,
                'path' => $path . '/',
            ]);
        
        if ($existingFolderQuery->exists()) {
            return $this->asSuccess();
        }
       
        // Get parent folder ID
        $parentId = (new Query())
            ->select('id')
            ->from(Table::VOLUMEFOLDERS)
            ->where([
                'volumeId' => $volumeId,
                'path' => ($name === $path) ? '' : dirname($path) . '/',
            ])
            ->scalar();

        // Store folder
        $record = new VolumeFolderRecord([
            'parentId' => $parentId,
            'volumeId' => $volumeId,
            'name' => $name,
            'path' => $path . '/',
        ]);
        $record->save();

        return $this->asSuccess();
    }

    private function _processDeleteFolder($volumeId, $subpath): Response
    {
        $path = $this->request->getRequiredBodyParam('folder_path');

        if (!empty($subpath)) {
            if (!str_starts_with($path, $subpath . '/')) {
                return $this->asSuccess();
            }

            $path = substr($path, strlen($subpath) + 1);
        }

        // Delete folder
        VolumeFolderRecord::deleteAll([
            'volumeId' => $volumeId,
            'path' => $path . '/',
        ]);

        return $this->asSuccess();
    }

    private function _processUpload($volumeId, $subpath): Response
    {
        $publicId = $this->request->getRequiredBodyParam('public_id');
        $folder = $this->request->getRequiredBodyParam('folder');
        $size = $this->request->getRequiredBodyParam('bytes');

        if (!empty($subpath)) {
            if ($folder !== $subpath && !str_starts_with($folder, $subpath . '/')) {
                return $this->asSuccess();
            }

            $folder = substr($folder, strlen($subpath) + 1);
        }

        // Get folder ID
        $folderId = (new Query())
            ->select('id')
            ->from(Table::VOLUMEFOLDERS)
            ->where([
                'volumeId' => $volumeId,
                'path' => $folder === '' ? '' : $folder . '/',
            ])
            ->scalar();

        // Check if asset exists
        $filename = basename($publicId);

        $resourceType = $this->request->getRequiredBodyParam('resource_type');
        
        if ($resourceType !== 'raw') {
            $format = $this->request->getRequiredBodyParam('format');

            $filename = $filename . '.' . $format;
        }

        $existingAssetQuery = (new Query())
            ->from(['assets' => Table::ASSETS])
            ->innerJoin(['elements' => Table::ELEMENTS], '[[elements.id]] = [[assets.id]]')
            ->where([
                'assets.volumeId' => $volumeId,
                'assets.folderId' => $folderId,
                'assets.filename' => $filename,
                'elements.dateDeleted' => null,
            ]);

        if ($existingAssetQuery->exists()) {
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

    private function _processDelete($volumeId, $subpath): Response
    {
        $resources = $this->request->getRequiredBodyParam('resources');

        foreach ($resources as $resource) {
            $resourceType = $resource['resource_type'];
            $publicId = $resource['public_id'];
            $folder = $resource['folder'];

            if (!empty($subpath)) {
                if ($folder !== $subpath && !str_starts_with($folder, $subpath . '/')) {
                    return $this->asSuccess();
                }
    
                $folder = substr($folder, strlen($subpath) + 1);
            }

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
                
            if ($asset !== null) {
                Craft::$app->getElements()->deleteElement($asset);
            }
        }

        return $this->asSuccess();
    }

    private function _processRename($volumeId, $subpath): Response
    {
        $resourceType = $this->request->getRequiredBodyParam('resource_type');
        $fromPublicId = $this->request->getRequiredBodyParam('from_public_id');
        $toPublicId = $this->request->getRequiredBodyParam('to_public_id');
        $folder = $this->request->getRequiredBodyParam('folder');
        
        $fromFilename = basename($fromPublicId);
        $fromFolder = dirname($fromPublicId);
        $fromFolderPath = $fromFolder === '.' ? '' : $fromFolder . '/';
        $toFilename = basename($toPublicId);
        $toFolderPath = $folder === '' ? '' : $folder . '/';

        if (!empty($subpath)) {
            if ($fromFolder !== $subpath && !str_starts_with($fromFolder, $subpath . '/')) {
                return $this->asSuccess();
            }

            if ($folder !== $subpath && !str_starts_with($folder, $subpath . '/')) {
                return $this->asSuccess();
            }

            $fromFolderPath = substr($fromFolderPath, strlen($subpath) + 1);
            $toFolderPath = substr($toFolderPath, strlen($subpath) + 1);
        }

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

        return $this->asSuccess();
    }
}
