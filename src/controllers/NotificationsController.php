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
use craft\models\VolumeFolder;
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
        $hasDynamicFolders = $fs->dynamicFolders;

        switch ($notificationType) {
            case 'create_folder':
                return $this->_processCreateFolder($volumeId, $subpath);
            case 'move_or_rename_asset_folder':
                return $this->_processMoveOrRenameAssetFolder($volumeId, $subpath);
            case 'delete_folder':
                return $this->_processDeleteFolder($volumeId, $subpath);
            case 'upload':
                return $this->_processUpload($volumeId, $subpath, $hasDynamicFolders);
            case 'delete':
                return $this->_processDelete($volumeId, $subpath, $hasDynamicFolders);
            case 'rename':
                return $this->_processRename($volumeId, $subpath, $hasDynamicFolders);
            case 'move':
                return $this->_processMove($volumeId, $subpath);
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

    private function _processMoveOrRenameAssetFolder($volumeId, $subpath): Response
    {
        $fromPath = $this->request->getRequiredBodyParam('from_path');
        $toPath = $this->request->getRequiredBodyParam('to_path');

        if (!empty($subpath)) {
            if (!str_starts_with($fromPath, $subpath . '/')) {
                return $this->asSuccess();
            }

            if (!str_starts_with($toPath, $subpath . '/')) {
                return $this->asSuccess();
            }

            $fromPath = substr($fromPath, strlen($subpath) + 1);
            $toPath = substr($fromPath, strlen($subpath) + 1);
        }

        $fromName = basename($fromPath);
        $toName = basename($toPath);

        $fromParentPath = $fromName === $fromPath ? '' : dirname($fromPath) . '/';
        $toParentPath = $toName === $toPath ? '' : dirname($toPath) . '/';

        $folderQueryResult = (new Query())
            ->select(['id', 'parentId', 'volumeId', 'name', 'path', 'uid'])
            ->from(Table::VOLUMEFOLDERS)
            ->where([
                'volumeId' => $volumeId,
                'path' => $fromPath === '' ? '' : $fromPath . '/',
            ])
            ->one();
            
        if (!$folderQueryResult) {
            return $this->asSuccess();
        }

        $assetsService = Craft::$app->getAssets();
        
        $folder = new VolumeFolder($folderQueryResult);
        $descendantFolders = $assetsService->getAllDescendantFolders($folder);

        // Rename folder and update descendants
        foreach ($descendantFolders as $descendantFolder) {
            $descendantFolder->path = preg_replace('#^' . $fromPath . '/' . '#', $toPath . '/', $descendantFolder->path);
            $assetsService->storeFolderRecord($descendantFolder);
        }

        $folder->name = $toName;
        $folder->path = $toPath . '/';

        if ($fromParentPath !== $toParentPath) {
            $parentId = (new Query())
                ->select('id')
                ->from(Table::VOLUMEFOLDERS)
                ->where([
                    'volumeId' => $volumeId,
                    'path' => $toParentPath,
                ])
                ->scalar();
            
            $folder->parentId = $parentId;
        }

        $assetsService->storeFolderRecord($folder);

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

    private function _processUpload($volumeId, $subpath, $hasDynamicFolders): Response
    {
        $publicId = $this->request->getRequiredBodyParam('public_id');
        $folder = $hasDynamicFolders
            ? $this->request->getRequiredBodyParam('asset_folder')
            : $this->request->getRequiredBodyParam('folder');
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

    private function _processDelete($volumeId, $subpath, $hasDynamicFolders): Response
    {
        $resources = $this->request->getRequiredBodyParam('resources');

        foreach ($resources as $resource) {
            $resourceType = $resource['resource_type'];
            $publicId = $resource['public_id'];
            $folder = $hasDynamicFolders
                ? $resource['asset_folder']
                : $resource['folder'];

            if (!empty($subpath)) {
                if ($folder !== $subpath && !str_starts_with($folder, $subpath . '/')) {
                    continue;
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

    private function _processRename($volumeId, $subpath, $hasDynamicFolders): Response
    {
        $resourceType = $this->request->getRequiredBodyParam('resource_type');
        $fromPublicId = $this->request->getRequiredBodyParam('from_public_id');
        $toPublicId = $this->request->getRequiredBodyParam('to_public_id');
        $folder = $hasDynamicFolders
            ? $this->request->getRequiredBodyParam('asset_folder')
            : $this->request->getRequiredBodyParam('folder');
        
        $fromFilename = basename($fromPublicId);
        $fromFolder = $hasDynamicFolders ? $folder : dirname($fromPublicId);
        $fromFolderPath = in_array($fromFolder, ['.', '']) ? '' : $fromFolder . '/';
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

    private function _processMove($volumeId, $subpath): Response
    {
        $resources = $this->request->getRequiredBodyParam('resources');

        foreach ($resources as $publicId => $resource) {
            $resourceType = $resource['resource_type'];
            $fromFolder = $resource['from_asset_folder'];
            $toFolder = $resource['to_asset_folder'];

            if (!empty($subpath)) {
                if ($fromFolder !== $subpath && !str_starts_with($fromFolder, $subpath . '/')) {
                    continue;
                }

                if ($toFolder !== $subpath && !str_starts_with($toFolder, $subpath . '/')) {
                    continue;
                }
    
                $fromFolder = substr($fromFolder, strlen($subpath) + 1);
                $toFolder = substr($toFolder, strlen($subpath) + 1);
            }

            $fromFolderPath = $fromFolder === '' ? '' : $fromFolder . '/';
            $toFolderPath = $toFolder === '' ? '' : $toFolder . '/';

            $assetQuery = Asset::find()
                ->volumeId($volumeId)
                ->folderPath($fromFolderPath);
            
            if ($resourceType === 'raw') {
                $assetQuery->filename($publicId);
            } else {
                $assetQuery->filename("$publicId.*");
                if ($resourceType === 'image') {
                    $assetQuery->kind('image');
                } else {
                    $assetQuery->kind(['video', 'audio']);
                }
            }

            $asset = $assetQuery->one();
                
            if ($asset === null) {
                continue;
            }

            $folderId = (new Query())
                ->select('id')
                ->from(Table::VOLUMEFOLDERS)
                ->where([
                    'volumeId' => $volumeId,
                    'path' => $toFolderPath,
                ])
                ->scalar();
            
            if (!$folderId) {
                continue;
            }

            $asset->folderId = $folderId;

            Craft::$app->getElements()->saveElement($asset);
        }

        return $this->asSuccess();
    }
}
