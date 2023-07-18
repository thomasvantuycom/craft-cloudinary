# Cloudinary for Craft CMS

This plugin provides a [Cloudinary](https://cloudinary.com/) integration for [Craft CMS](https://craftcms.com/).

## Requirements

This plugin requires Craft CMS 4.4.0 or later, and PHP 8.0.2 or later.

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

To create a new Cloudinary filesystem to use with your volumes, visit **Settings** → **Filesystems**, and press **New filesystem**. Select “Cloudinary” for the **Filesystem Type** setting and configure as needed.

The plugin is compatible with your existing Craft template code and named transforms.
