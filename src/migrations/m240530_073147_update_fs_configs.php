<?php

namespace thomasvantuycom\craftcloudinary\migrations;

use Craft;
use craft\db\Migration;
use craft\helpers\App;
use craft\services\ProjectConfig;
use thomasvantuycom\craftcloudinary\fs\CloudinaryFs;

/**
 * m240530_073147_update_fs_configs migration.
 */
class m240530_073147_update_fs_configs extends Migration
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

        foreach ($fsConfigs as $uid => $config) {
            if (
                $config['type'] === CloudinaryFs::class &&
                isset($config['url']) &&
                $config['url'] !== ''
            ) {
                $hostname = parse_url(App::parseEnv($config['url']), PHP_URL_HOST);

                if ($hostname !== 'res.cloudinary.com') {
                    if (str_ends_with($hostname, '-res.cloudinary.com')) {
                        $config['settings']['privateCdn'] = true;
                    } else {
                        $config['settings']['cname'] = $hostname;
                    }
                }

                $config['url'] = null;
                $projectConfig->set(sprintf('%s.%s', ProjectConfig::PATH_FS, $uid), $config);
            }
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m240530_073147_update_fs_configs cannot be reverted.\n";
        return false;
    }
}
