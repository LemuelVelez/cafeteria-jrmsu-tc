<?php

namespace Config;

class Mimes
{
    /**
     * MIME types used by the application's image upload validation.
     *
     * @var array<string, string|list<string>>
     */
    public static array $mimes = [
        'jpg' => ['image/jpeg', 'image/pjpeg'],
        'jpeg' => ['image/jpeg', 'image/pjpeg'],
        'jpe' => ['image/jpeg', 'image/pjpeg'],
        'png' => ['image/png', 'image/x-png'],
        'webp' => 'image/webp',
    ];

    public static function guessTypeFromExtension(string $extension): ?string
    {
        $extension = trim(strtolower($extension), '. ');

        if (! array_key_exists($extension, static::$mimes)) {
            return null;
        }

        $types = static::$mimes[$extension];

        return is_array($types) ? $types[0] : $types;
    }

    public static function guessExtensionFromType(string $type, ?string $proposedExtension = null): ?string
    {
        $type = trim(strtolower($type), '. ');
        $proposedExtension = trim(strtolower($proposedExtension ?? ''), '. ');

        if (
            $proposedExtension !== ''
            && array_key_exists($proposedExtension, static::$mimes)
            && in_array($type, (array) static::$mimes[$proposedExtension], true)
        ) {
            return $proposedExtension;
        }

        foreach (static::$mimes as $extension => $types) {
            if (in_array($type, (array) $types, true)) {
                return $extension;
            }
        }

        return null;
    }
}
