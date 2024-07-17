# Cloudinary for Craft CMS

This plugin integrates [Cloudinary](https://cloudinary.com/) with [Craft CMS](https://craftcms.com/). Assets can be uploaded from Craft's control panel and then transformed and delivered by Cloudinary, even if stored in a different filesystem. The plugin is compatible with your existing Craft template code and named image transforms.

## Requirements

This plugin requires Craft CMS 5.0.0 or later, and PHP 8.2 or later.

## Installation

You can install this plugin from the Plugin Store or with Composer.

#### From the Plugin Store

Go to the Plugin Store in your project’s Control Panel and search for “Cloudinary”. Then press “Install”.

#### With Composer

Open your terminal and run the following commands:

```bash
# go to the project directory
cd /path/to/my-project.test

# tell Composer to load the plugin
composer require thomasvantuycom/craft-cloudinary

# tell Craft to install the plugin
./craft plugin/install cloudinary
```

## Setup

The plugin adds a Cloudinary filesystem type to Craft. It can be used solely as a transform filesystem or as a storage filesystem as well. 

To create a new Cloudinary filesystem to use with your volumes, visit **Settings** → **Filesystems**, and press **New filesystem**. Select “Cloudinary” for the **Filesystem Type** setting and configure as needed.

To start using the filesystem, visit **Settings** → **Assets** → **Volumes**. Here you can create a new volume using the Cloudinary filesystem for both storage and transforms, or add the Cloudinary filesystem to any existing volumes for transforms only. In the latter case, any assets with public URLs from any local or remote filesystem are transformed by Cloudinary using the [fetch feature](https://cloudinary.com/documentation/fetch_remote_images#fetch_and_deliver_remote_files). This may not work in local development setups.

## Image Transformations

The plugin supports all of [Craft's native transform options](https://craftcms.com/docs/4.x/image-transforms.html). These can be found under **Settings** → **Assets** → **Image Transforms**.

In addition, you can incorporate any of [Cloudinary's transformation options](https://cloudinary.com/documentation/transformation_reference#overview) in the transforms you define in your templates, like so:
```twig
{% set thumb = {
  width: 100,
  height: 100,
  quality: 75,
  opacity: 33,
  border: '5px_solid_rgb:999',
} %}

<img src="{{ asset.getUrl(thumb) }}">
```
Transformation options should be in camelCase, meaning `aspect_ratio` becomes `aspectRatio`, or `fetch_format` becomes `fetchFornat`.

## Webhook notifications

To keep Craft aligned with changes made directly in Cloudinary, activate webhook notifications. Simply go to your [Cloudinary settings](https://console.cloudinary.com/settings/c-4547d495209fcc884b171f78858f04/webhooks) and add a new notification URL. Point it to the base URL of your website followed by `/actions/cloudinary/notifications/process?volume={VOLUME_ID}`. Remember to replace `{VOLUME_ID}` with the relevant asset volume ID, which you can find in the URL of the volume's settings page. Enable the relevant notification types: `upload`, `delete`, `rename`, `create_folder`, and `delete_folder`. Keep in mind, this setup only functions in local development if your local domain is publicly accessible via a service like ngrok. Additionally, note that the webhook may struggle with a large volume of operations. If you frequently make extensive changes in the Cloudinary Console, consider re-indexing your asset volume instead.