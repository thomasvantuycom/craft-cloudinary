<?php

namespace thomasvantuycom\craftcloudinary\helpers;

use Cloudinary\Asset\AssetType;
use Cloudinary\Cloudinary;
use craft\helpers\App;
use craft\helpers\FileHelper;
use craft\helpers\StringHelper;

class AssetHelper
{
    public static function url($asset, $transformation)
    {
        $volume = $asset->getVolume();
        $fs = $volume->getFs();
        $resourceType = self::resourceType($asset);

        $config = [
            'cloud' => [
                'cloud_name' => App::parseEnv($fs->cloudName),
            ],
            'url' => [
                'forceVersion' => false,
                'analytics' => false,
            ],
        ];

        if ($fs->privateCdn) {
            $config['url']['private_cdn'] = true;
        }

        if (!empty($fs->cname)) {
            $config['url']['private_cdn'] = true;
            $config['url']['secure_distribution'] = App::parseEnv($fs->cname);
        }

        $client = new Cloudinary($config);

        $path = $volume->getSubpath() . $asset->getPath();
        $resource = $client->{$resourceType}($path);

        if ($transformation !== null) {
            if ($resourceType === AssetType::IMAGE) {
                return;
            }

            $params = [];

            foreach ($transformation as $key => $value) {
                $key = StringHelper::toSnakeCase($key);
                $params[$key] = $value;
            }

            $resource->addTransformation($params);

            if (isset($transformation['format'])) {
                $resource->extension($transformation['format']);
            }
        }

        return $resource->toUrl();
    }

    public static function resourceType($asset)
    {
        $mimeType = FileHelper::getMimeTypeByExtension($asset->getPath());

        if ($mimeType === null) {
            return AssetType::RAW;
        }

        switch (true) {
            case str_starts_with($mimeType, "image/"):
            case $mimeType === "application/pdf":
                return AssetType::IMAGE;
            case str_starts_with($mimeType, "video/"):
            case str_starts_with($mimeType, "audio/"):
                return AssetType::VIDEO;
            default:
                return AssetType::RAW;
        }
    }
}
