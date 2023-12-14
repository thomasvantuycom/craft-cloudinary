<?php

namespace thomasvantuycom\craftcloudinary\fs;

use Cloudinary\Cloudinary;
use Craft;
use craft\base\Fs;
use craft\errors\FsException;
use craft\errors\FsObjectNotFoundException;
use craft\models\FsListing;
use Exception;

class CloudinaryFs extends Fs
{
    public string $cloudName = '';

    public string $apiKey = '';

    public string $apiSecret = '';

    public string $baseFolder = '';

    public bool $dynamicFolders = false;

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
            // ...
        ]);
    }

    public function getSettingsHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate('cloudinary/fsSettings', [
            'fs' => $this,
        ]);
    }

    public function getFileList(string $directory = '', bool $recursive = true): \Generator
    {
        try {
            $client = $this->client();
            $assets = [];
            $response = null;

            do {
                $response = (array) $client->adminApi()->assets([
                    'max_results' => 500,
                    'next_cursor' => isset($response['next_cursor']) ? $response['next_cursor'] : null,
                ]);
                $assets = array_merge($assets, $response['resources']);
            } while (isset($response['next_cursor']));

            $assets = array_filter($assets, function($asset) {
                return $asset['bytes'] > 0;
            });

            foreach ($assets as $asset) {
                yield new FsListing([
                    'basename' => basename($asset['public_id']) . '.' . $asset['format'],
                    'dirname' => $asset['asset_folder'] ?? $asset['folder'],
                    'type' => 'file',
                    'fileSize' => $asset['bytes'],
                    'dateModified' => (int) strtotime(isset($asset['last_updated']) ? $asset['last_updated']['updated_at'] : $asset['created_at']),
                ]);
            }
        } catch (Exception $e) {
            throw new FsException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function getFileSize(string $uri): int
    {
        try {
            $client = $this->client();
            $publicId = $this->pathToPublicId($uri);
            $response = $client->adminApi()->asset($publicId);
            return $response['bytes'];
        } catch (Exception $e) {
            throw new FsException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function getDateModified(string $uri): int
    {
        try {
            $client = $this->client();
            $publicId = $this->pathToPublicId($uri);
            $response = $client->adminApi()->asset($publicId);
            $updatedAt = (int) strtotime(isset($response['last_updated']) ? $response['last_updated']['updated_at'] : $response['created_at']);
            return intval($updatedAt);
        } catch (Exception $e) {
            throw new FsException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function read(string $path): string
    {
        try {
            $client = $this->client();
            $url = $this->pathToUrl($path);
            return file_get_contents($url);
        } catch (Exception $e) {
            throw new FsException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function write(string $path, string $contents, array $config = []): void
    {
        try {
            $client = $this->client();

            $uploadOptions = [
                'public_id' => $this->pathToPublicId($path),
            ];

            if ($this->dynamicFolders) {
                $uploadOptions['asset_folder'] = $this->pathToFolder($path);
            }

            if(!empty($this->baseFolder)) {
                $uploadOptions['folder'] = Craft::parseEnv($this->baseFolder);
            }

            $client->uploadApi()->upload($contents, $uploadOptions);
        } catch (Exception $e) {
            throw new FsException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function writeFileFromStream(string $path, $stream, array $config = []): void
    {
        try {
            $client = $this->client();

            $uploadOptions = [
                'public_id' => $this->pathToPublicId($path),
            ];

            if ($this->dynamicFolders) {
                $uploadOptions['asset_folder'] = $this->pathToFolder($path);
            }

            if(!empty($this->baseFolder)) {
                $uploadOptions['folder'] = Craft::parseEnv($this->baseFolder);
            }

            $client->uploadApi()->upload($stream, $uploadOptions);
        } catch (Exception $e) {
            throw new FsException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function fileExists(string $path): bool
    {
        try {
            $client = $this->client();
            $publicId = $this->pathToPublicId($path);
            $response = $client->adminApi()->asset($publicId);
            if ($response['bytes'] > 0) {
                return true;
            }
            return false;
        } catch (Exception $e) {
            return false;
        }
    }

    public function deleteFile(string $path): void
    {
        try {
            $client = $this->client();
            $publicId = $this->appendBaseFolderToPath($this->pathToPublicId($path));
            $client->uploadApi()->destroy($publicId, [
                'invalidate' => true,
            ]);
        } catch (Exception $e) {
            throw new FsException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function renameFile(string $path, string $newPath): void
    {
        try {
            $client = $this->client();
            $publicId = $this->appendBaseFolderToPath($this->pathToPublicId($path));
            $newPublicId = $this->appendBaseFolderToPath($this->pathToPublicId($newPath));
            $client->uploadApi()->rename($publicId, $newPublicId, [
                'invalidate' => true,
            ]);
        } catch (Exception $e) {
            throw new FsException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function copyFile(string $path, string $newPath): void
    {
        try {
            $client = $this->client();
            $url = $this->pathToUrl($path);

            $uploadOptions = [
                'public_id' => $this->pathToPublicId($newPath),
            ];

            if ($this->dynamicFolders) {
                $uploadOptions['asset_folder'] = $this->pathToFolder($path);
            }

            if(!empty($this->baseFolder)) {
                $uploadOptions['folder'] = Craft::parseEnv($this->baseFolder);
            }

            $client->uploadApi()->upload($url, $uploadOptions);
        } catch (Exception $e) {
            throw new FsException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function getFileStream(string $uriPath)
    {
        try {
            $client = $this->client();
            $uriPath = $this->appendBaseFolderToPath($uriPath);
            $url = $this->pathToUrl($uriPath);
            $file = @fopen($url, 'rb');
            if (!$file) {
                throw new FsObjectNotFoundException('Unable to open file: ' . $uriPath);
            }
            return $file;
        } catch (Exception $e) {
            throw new FsException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function directoryExists(string $path): bool
    {
        throw new FsException('Moving folders is not supported by Cloudinary.');
    }

    public function createDirectory(string $path, array $config = []): void
    {
        try {
            $client = $this->client();
            $client->adminApi()->createFolder($path);
        } catch (Exception $e) {
            throw new FsException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function deleteDirectory(string $path): void
    {
        try {
            $client = $this->client();
            $client->adminApi()->deleteAssetsByPrefix($path);
            $client->adminApi()->deleteFolder($path);
        } catch (Exception $e) {
            throw new FsException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function renameDirectory(string $path, string $newName): void
    {
        throw new FsException('Renaming folders is not supported by Cloudinary.');
    }

    protected function client(): Cloudinary
    {
        $config = [
            'cloud' => [
                'cloud_name' => Craft::parseEnv($this->cloudName),
                'api_key' => Craft::parseEnv($this->apiKey),
                'api_secret' => Craft::parseEnv($this->apiSecret),
            ],
        ];

        $url = Craft::parseEnv($this->url);
        if ($url) {
            $hostname = parse_url($url, PHP_URL_HOST);

            if ($hostname !== 'res.cloudinary.com') {
                $config['private_cdn'] = TRUE;
                $config['secure_distribution'] = $hostname;
            }
        }

        return new Cloudinary($config);
    }

    protected function appendBaseFolderToPath(string $path): string
    {
        if (!empty($this->baseFolder)) {
            $path = Craft::parseEnv($this->baseFolder) . '/' . $path;
        }
        return $path;
    }

    protected function pathToPublicId(string $path): string
    {
        if ($this->dynamicFolders) {
            $path = basename($path);
        }
        return preg_replace('/\.[^.]+$/', '', $path);
    }

    protected function pathToUrl(string $path): string
    {
        if ($this->dynamicFolders) {
            $path = basename($path);
        }
        $client = $this->client();
        return $client->image($path)->toUrl();
    }

    protected function pathToFolder(string $path): string
    {
        $folder = dirname($path);

        if ($folder === '.') {
            return '';
        }

        return $folder;
    }
}
