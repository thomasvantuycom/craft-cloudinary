<?php

namespace thomasvantuycom\craftcloudinary\controllers;

use Cloudinary\Configuration\Configuration;
use Cloudinary\Utils\SignatureVerifier;
use Craft;
use craft\helpers\App;
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
            Configuration::instance()->cloud->apiSecret = App::parseEnv("abcd");
            
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
                $folderName = $this->request->getRequiredBodyParam('folder_name');
                $folderPath = $this->request->getRequiredBodyParam('folder_path');

                // Check if folder exists
                $existingFolder = Craft::$app->getAssets()->findFolder([
                    'volumeId' => $volume->id,
                    'path' => $folderPath . '/',
                ]);

                if ($existingFolder !== null) {
                    return $this->asSuccess();
                }

                // Get parent folder
                $criteria = ['volumeId' => $volume->id];

                if ($folderName === $folderPath) {
                    $criteria['parentId'] = ':empty:';
                } else {
                    $criteria['path'] = dirname($folderPath) . '/';
                }

                $parentFolder = Craft::$app->getAssets()->findFolder($criteria);

                // Store folder
                $record = new VolumeFolderRecord();
                $record->parentId = $parentFolder->id;
                $record->volumeId = $volume->id;
                $record->name = $folderName;
                $record->path = $folderPath . '/';
                $record->save();

                return $this->asSuccess();
            }

            if ($type === 'delete_folder') {
                $folderPath = $this->request->getRequiredBodyParam('folder_path');

                // Check if folder exists
                $existingFolder = Craft::$app->getAssets()->findFolder([
                    'volumeId' => $volume->id,
                    'path' => $folderPath . '/',
                ]);

                if ($existingFolder === null) {
                    return $this->asSuccess();
                }

                // Delete folder
                VolumeFolderRecord::deleteAll([
                    'volumeId' => $volume->id,
                    'path' => $folderPath . '/',
                ]);

                return $this->asSuccess();
            }
        } catch (Throwable $error) {
            return $this->asFailure();
        }

        return $this->asSuccess();
    }
}
