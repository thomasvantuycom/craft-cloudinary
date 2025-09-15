<?php

namespace thomasvantuycom\craftcloudinary\helpers;

use craft\models\ImageTransform;
use ReflectionClass;

class ImageTransforms
{
    public static function isNativeTransform(mixed $transform): bool
    {
        if (is_array($transform)) {
            $reflection = new ReflectionClass(ImageTransform::class);
    
            foreach ($transform as $key => $value) {
                if (!$reflection->hasProperty($key)) {
                    return false;
                }

                $property = $reflection->getProperty($key);

                if (!$property->isPublic() || $property->isStatic()) {
                    return false;
                }
            }
        }
    
        return true;
    }
}
