<?php

namespace thomasvantuycom\craftcloudinary\behaviors;

use yii\base\Behavior;

class CloudinaryBehavior extends Behavior
{
    public int|string|null $angle = null;

    public float|string|null $aspectRatio = null;

    public string|null $background = null;

    public string|null $border = null;

    public string|null $color = null;
    
    public string|null $colorSpace = null;
    
    public string|null $crop = null;

    public string|null $defaultImage = null;

    public int|null $delay = null;

    public int|null $density = null;

    public float|string|null $dpr = null;

    public string|null $effect = null;

    public string|null $fetchFormat = null;
    
    public string|null $flags = null;

    public string|null $gravity = null;

    public int|null $opacity = null;
    
    public string|null $overlay = null;

    public int|string|null $page = null;

    public string|null $prefix = null;
    
    public int|string|null $radius = null;

    public string|null $transformation = null;

    public string|null $underlay = null;

    public float|int|null $x = null;
    
    public float|int|null $y = null;

    public float|null $zoom = null;
}
