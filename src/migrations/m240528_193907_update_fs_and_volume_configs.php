<?php

namespace thomasvantuycom\craftcloudinary\migrations;

use Craft;
use craft\db\Migration;
use craft\services\ProjectConfig;
use thomasvantuycom\craftcloudinary\fs\CloudinaryFs;

/**
 * m240528_193907_update_fs_and_volume_configs migration.
 */
class m240528_193907_update_fs_and_volume_configs extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $schemaVersion = Craft::$app->getProjectConfig()->get('plugins.cloudinary.schemaVersion', true);
        if (version_compare($schemaVersion, '2.0', '>=')) {
            return true;
        }

        $projectConfig = Craft::$app->getProjectConfig();
        $fsConfigs = $projectConfig->get(ProjectConfig::PATH_FS) ?? [];
        $volumeConfigs = $projectConfig->get(ProjectConfig::PATH_VOLUMES) ?? [];

        foreach ($fsConfigs as $fsUid => $fsConfig) {
            if (
                $fsConfig['type'] === CloudinaryFs::class &&
                isset($fsConfig['settings']) &&
                is_array($fsConfig['settings']) &&
                isset($fsConfig['settings']['baseFolder']) &&
                $fsConfig['settings']['baseFolder'] !== ''
            ) {
                foreach ($volumeConfigs as $volumeUid => $volumeConfig) {
                    if (
                        $volumeConfig['fs'] === $fsUid &&
                        (!isset($volumeConfig['subpath']) || $volumeConfig['subpath'] === '')
                    ) {
                        $volumeConfig['subpath'] = $fsConfig['settings']['baseFolder'];
                        $projectConfig->set(sprintf('%s.%s', ProjectConfig::PATH_VOLUMES, $volumeUid), $volumeConfig);
                    }
                }

                unset($fsConfig['settings']['baseFolder']);
                $projectConfig->set(sprintf('%s.%s', ProjectConfig::PATH_FS, $fsUid), $fsConfig);
            }
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m240528_193907_update_fs_and_volume_configs cannot be reverted.\n";
        return false;
    }
}
