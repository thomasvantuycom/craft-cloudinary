<?php

namespace thomasvantuycom\craftcloudinary\fs;

use Cloudinary\Cloudinary;
use Craft;
use craft\behaviors\EnvAttributeParserBehavior;
use craft\flysystem\base\FlysystemFs;
use craft\helpers\App;
use League\Flysystem\FilesystemAdapter;
use ThomasVantuycom\FlysystemCloudinary\CloudinaryAdapter;

class CloudinaryFs extends FlysystemFs
{
    public string $cloudName = '';

    public string $apiKey = '';

    public string $apiSecret = '';

    public bool $privateCdn = false;

    public string $cname = '';

    protected bool $foldersHaveTrailingSlashes = false;

    public static function displayName(): string
    {
        return Craft::t('cloudinary', 'Cloudinary');
    }

    protected function defineBehaviors(): array
    {
        $behaviors = parent::defineBehaviors();
        $behaviors['parser'] = [
            'class' => EnvAttributeParserBehavior::class,
            'attributes' => [
                'cloudName',
                'apiKey',
                'apiSecret',
                'cname',
            ],
        ];

        return $behaviors;
    }

    protected function defineRules(): array
    {
        $rules = parent::defineRules();
        $rules[] = [['cloudName', 'apiKey', 'apiSecret'], 'required'];
        $rules[] = [['privateCdn'], 'boolean'];
        $rules[] = [['cname'], 'trim', 'chars' => ' /'];
        
        return $rules;
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

    public function getClient(): Cloudinary
    {
        $config = [
            'cloud' => [
                'cloud_name' => App::parseEnv($this->cloudName),
                'api_key' => App::parseEnv($this->apiKey),
                'api_secret' => App::parseEnv($this->apiSecret),
            ],
            'url' => [
                'analytics' => false,
                'forceVersion' => false,
            ],
        ];

        if ($this->privateCdn) {
            $config['url']['private_cdn'] = true;
        }

        if (!empty($this->cname)) {
            $config['url']['private_cdn'] = true;
            $config['url']['secure_distribution'] = App::parseEnv($this->cname);
        }

        return new Cloudinary($config);
    }

    protected function createAdapter(): FilesystemAdapter
    {
        $client = $this->getClient();

        return new CloudinaryAdapter($client);
    }

    protected function invalidateCdnPath(string $path): bool
    {
        return true;
    }
}
