<?php

namespace Erwin\Yaml;

class YamlCaster
{
    public static function castsToNull(mixed $yamlData): bool
    {
        return !(bool) $yamlData || $yamlData == 'null';
    }

    public static function toInt(mixed $yamlData): ?int
    {
        if (is_null($yamlData)) {
            return 0;
        }
        if (is_scalar($yamlData) || $yamlData instanceof \Stringable) {
            $str = (string) $yamlData;
            if (preg_match('/^(-?[0-9]+)?$/', $str)) {
                return (int) $str;
            }
        }

        return null;
    }

    public static function toFloat(mixed $yamlData): ?float
    {
        if (is_null($yamlData)) {
            return 0;
        }
        if (is_scalar($yamlData) || $yamlData instanceof \Stringable) {
            $str = (string) $yamlData;
            if (preg_match('/^(-?[0-9]+(\.[0-9]+)?)?$/', $str)) {
                return (float) $str;
            }
        }

        return null;
    }

    public static function toString(mixed $yamlData): ?string
    {
        if (is_null($yamlData) || is_scalar($yamlData) || $yamlData instanceof \Stringable) {
            return (string) $yamlData;
        }

        return null;
    }

    public static function toBool(mixed $yamlData): ?bool
    {
        if (is_null($yamlData) || is_scalar($yamlData) || $yamlData instanceof \Stringable) {
            return (bool) $yamlData;
        }

        return null;
    }

    public static function toArray(mixed $yamlData): ?array
    {
        if (is_null($yamlData) || $yamlData === false || $yamlData === '') {
            return [];
        }

        return null;
    }
}
