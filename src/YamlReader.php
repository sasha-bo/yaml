<?php

namespace Erwin\Yaml;

use Symfony\Component\Yaml\Yaml;

class YamlReader
{
    private bool $privatePropertiesAllowed = false;
    private bool $dynamicPropertiesAllowed = false;
    private bool $staticPropertiesAllowed = false;

    public function allowPrivateProperties(bool $allow = true): self
    {
        $this->privatePropertiesAllowed = $allow;

        return $this;
    }

    public function allowDynamicProperties(bool $allow = true): self
    {
        $this->dynamicPropertiesAllowed = $allow;

        return $this;
    }

    public function allowStaticProperties(bool $allow = true): self
    {
        $this->staticPropertiesAllowed = $allow;

        return $this;
    }

    public function readFile(string $yamlFile, string|object $classOrObject = \stdClass::class): mixed
    {
        return $this->convert(Yaml::parseFile($yamlFile), $classOrObject);
    }

    public function readString(string $yamlSource, string|object $classOrObject = \stdClass::class): mixed
    {
        return $this->convert(Yaml::parse($yamlSource), $classOrObject);
    }

    private function convert(mixed $yamlData, string|object $typeOrClassOrObject, ?string $path = null): mixed
    {
        if (is_object($typeOrClassOrObject)) {
            if (!is_array($yamlData)) {
                throw new YamlException('Can\'t convert '.(is_null($path) ? 'root' : $path).' node to '.$typeOrClassOrObject::class);
            }
            $this->setObjectProperties($yamlData, $typeOrClassOrObject);
            return $typeOrClassOrObject;
        } else {
            if ($typeOrClassOrObject[0] == '?') {
                $typeOrClassOrObject = 'null|'.substr($typeOrClassOrObject, 1);
            }
            foreach (explode('|', $typeOrClassOrObject) as $type) {
                // strict types
                if ($type == 'mixed') {
                    return $yamlData;
                } elseif ($type == 'null') {
                    if (is_null($yamlData)) {
                        return null;
                    }
                } elseif ($type == 'int') {
                    if (is_int($yamlData)) {
                        return $yamlData;
                    }
                } elseif ($type == 'float') {
                    if (is_float($yamlData)) {
                        return $yamlData;
                    }
                } elseif ($type == 'bool') {
                    if (is_bool($yamlData)) {
                        return $yamlData;
                    }
                } elseif ($type == 'string') {
                    if (is_string($yamlData)) {
                        return $yamlData;
                    }
                } elseif ($type == 'scalar') {
                    if (is_scalar($yamlData)) {
                        return $yamlData;
                    }
                } elseif ($type == 'array') {
                    if (is_array($yamlData)) {
                        return $yamlData;
                    }
                } else {
                    if (is_array($yamlData)) {
                        $object = $this->toObject($yamlData, $type, $path);
                        if (!is_null($object)) {
                            return $object;
                        }
                    }
                }
                // casting
                if ($type == 'null') {
                    if (YamlCaster::castsToNull($yamlData)) {
                        return null;
                    }
                } elseif ($type == 'int') {
                    $ret = YamlCaster::toInt($yamlData);
                    if (!is_null($ret)) {
                        return $ret;
                    }
                } elseif ($type == 'float') {
                    $ret = YamlCaster::toFloat($yamlData);
                    if (!is_null($ret)) {
                        return $ret;
                    }
                } elseif ($type == 'bool') {
                    $ret = YamlCaster::toBool($yamlData);
                    if (!is_null($ret)) {
                        return $ret;
                    }
                } elseif ($type == 'string') {
                    $ret = YamlCaster::toString($yamlData);
                    if (!is_null($ret)) {
                        return $ret;
                    }
                } elseif ($type == 'array') {
                    $ret = YamlCaster::toArray($yamlData);
                    if (!is_null($ret)) {
                        return $ret;
                    }
                }
            }
        }
        throw new YamlException('Can\'t convert '.(is_null($path) ? 'root' : $path).' node to '.$typeOrClassOrObject);
    }

    private function toObject(array $properties, string $class, ?string $path = null): ?object
    {
        $ret = null;
        if (class_exists($class)) {
            $ret = new $class();
            $this->setObjectProperties($properties, $ret, $path);
        }

        return $ret;
    }

    private function setObjectProperties(array $properties, object $object, ?string $path = null): void
    {
        $reflection = new \ReflectionClass($object);
        foreach ($reflection->getProperties() as $property) {
            $key = $this::toSnakeCase($property->getName());
            if (array_key_exists($key, $properties)) {
                if ($property->isStatic() && !$this->staticPropertiesAllowed) {
                    throw new YamlException($object::class.'::'.$property->getName().' is static, but staticPropertiesAllowed is false');
                }
                if (!$property->isPublic() && !$this->privatePropertiesAllowed) {
                    throw new YamlException($object::class.'::'.$property->getName().' is not public, but privatePropertiesAllowed is false');
                }
                $property->setValue(
                    $object,
                    $this->convert(
                        $properties[$key],
                        $this->getTypehint($property),
                        is_null($path) ? $key : $path.'.'.$key
                    )
                );
                unset($properties[$key]);
            }
        }
        foreach ($properties as $key => $value) {
            $name = $this->toCamelCase((string) $key);
            if (!$this->dynamicPropertiesAllowed) {
                throw new YamlException('Can\'t set '.$object::class.'::'.$name.' while dynamicPropertiesAllowed is false');
            }
            $object->$name = $value;
        }
    }

    private function toSnakeCase(string $name): string
    {
        $name = (string) preg_replace('/[A-Z]/', ' \\0', $name);
        $name = strtolower($name);
        $name = (string) preg_replace('/[^a-z0-9]+/', ' ', $name);
        $name = trim($name);
        return str_replace(' ', '_', $name);
    }

    private function toCamelCase(string $name): string
    {
        return (string) preg_replace_callback('/[\-_]([a-z]?)/', function (array $matches): string {
            return strtoupper($matches[1]);
        }, $name);
    }

    private function getTypehint(\ReflectionProperty $property): string
    {
        $type = $property->getType();
        if ($type instanceof \ReflectionNamedType) {
            return ($type->allowsNull() ? '?' : '').$type->getName();
        } elseif ($type instanceof \ReflectionUnionType) {
            $ret = [];
            foreach ($type->getTypes() as $subType) {
                $ret[] = $subType->getName();
            }
            return implode('|', $ret);
        }

        return 'mixed';
    }
}
