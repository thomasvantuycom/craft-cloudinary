<?php

namespace thomasvantuycom\craftcloudinary\fs;

use Cloudinary\Cloudinary;
use Craft;
use craft\flysystem\base\FlysystemFs;
use craft\helpers\App;
use League\Flysystem\FilesystemAdapter;
use ThomasVantuycom\FlysystemCloudinary\CloudinaryAdapter;

class CloudinaryFs extends FlysystemFs
{
    public string $cloudName = '';

    public string $apiKey = '';

    public string $apiSecret = '';

    protected bool $foldersHaveTrailingSlashes = false;

    public static function displayName(): string
    {
        return Craft::t('cloudinary', 'Cloudinary');
    }

    public function attributeLabels(): array
    {
        return array_merge(parent::attributeLabels(), [
            // ...
        ]);
    }

    protected function defineRules(): array
    {
        return array_merge(parent::defineRules(), [
            [['cloudName', 'apiKey', 'apiSecret'], 'required'],
        ]);
    }

    public function getSettingsHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate('cloudinary/fsSettings', [
            'fs' => $this,
        ]);
    }

    public function getShowHasUrlSetting(): bool
    {
        return false;
    }

    protected function createAdapter(): FilesystemAdapter
    {
        $client = new Cloudinary([
            'cloud' => [
                'cloud_name' => App::parseEnv($this->cloudName),
                'api_key' => App::parseEnv($this->apiKey),
                'api_secret' => App::parseEnv($this->apiSecret),
            ],
            'url' => [
                'analytics' => false,
                'forceVersion' => false,
            ],
        ]);

        return new CloudinaryAdapter($client);
    }

    protected function invalidateCdnPath(string $path): bool
    {
        return true;
    }
}
