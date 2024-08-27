<?php

namespace thomasvantuycom\craftcloudinary\helpers;

class ImageTransforms
{
    public static function isNativeTransform(mixed $transform): bool
    {
        if (is_array($transform)) {
            $nativeProperties = [
                'width',
                'height',
                'format',
                'mode',
                'position',
                'interlace',
                'quality',
                'fill',
                'upscale',
            ];
    
            foreach ($transform as $key => $value) {
                if (!in_array($key, $nativeProperties)) {
                    return false;
                }
            }
        }
    
        return true;
    }
}
