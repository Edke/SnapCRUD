<?php

namespace DannaxTools;


use Nette\Environment;

/**
 * EntityTools
 * @author	Eduard Kracmar <kracmar@dannax.sk>
 * @copyright	Copyright (c) 2006-2011 Eduard Kracmar, DANNAX (http://www.dannax.sk)
 */
class EntityTools {

    /**
     * @var \Nette\Reflection\ClassType
     */
    private static $reflection;
    private static $entity;

    private static function setEntity($entity) {
        self::$entity = $entity;
        self::$reflection = \Nette\Reflection\ClassType::from($entity);
    }

    /**
     * Get entity as array
     * @return array
     */
    public static function toArray($entity) {
        $result = array();
        self::setEntity($entity);
        $reflection = self::$reflection;
        do {
            foreach ($reflection->getProperties() as $property) {
                $getter = self::getGetter($property->getName()); // getter of entity
                if (!$getter) {
                    continue;
                }

                foreach ($property->getAnnotations() as $name => $annotation) {
                    if (\strtolower($name) == 'column') {
                        $result[$property->getName()] = self::$entity->$getter();
                    } elseif (\strtolower($name) == 'onetoone' or \strtolower($name) == 'manytoone') {
                        $value = self::$entity->$getter();
                        if (\is_object($value)) {
                            $valueReflection = \Nette\Reflection\ClassType::from($value);
                            $valueGetter = ($joinColumn = $property->getAnnotation('joinColumn') and isset($joinColumn['referencedColumnName'])) ?
                                    self::getGetter($joinColumn['referencedColumnName'], $valueReflection) :
                                    self::getGetter('id', $valueReflection);
                            $value = $value->$valueGetter();
                        }
                        $result[$property->getName()] = $value;
                    }
                }
            }
            $reflection = $reflection->getParentClass();
        } while ($reflection);
        #\Nette\Debug::barDump($result, 'toArray result');
        return $result;
    }

    /**
     * Get entity as stdClass
     * @return \stdClass
     */
    public static function toStdClass() {
        return (object) self::toArray();
    }

    /**
     * Fill entity with values
     * @param array|stdclass $values
     * @return boolean
     */
    public static function fill($entity, $values) {
        #\Nette\Debug::barDump($values);
        self::setEntity($entity);
        $reflection = self::$reflection;
        do {
            foreach ($values as $key => $value) {
                $property = $reflection->hasProperty($key) ? $reflection->getProperty($key) : false; // property of iterated reflection
                $setter = self::getSetter($key); // setter of entity
                if (!($property and $setter)) {
                    continue;
                }

                if ($property->hasAnnotation('column') or $property->hasAnnotation('fillable')) {
                    if ($property->hasAnnotation('file')) {
                        $value = self::handleFile($property->getAnnotation('file'), $key, $value);
                    }
                    self::$entity->$setter($value);
                } else {
                    $annotations = array('manyToOne', 'oneToOne');
                    foreach ($annotations as $annotation) {
                        $targetEntity = self::getTargetEntity($property, $annotation);
                        if (!$targetEntity) {
                            continue;
                        }

                        # filling association with entity
                        if (\is_object($value)) {
                            self::$entity->$setter($value);
                        }
                        # loading entity
                        elseif ($value > 0) {
                            $entityName = '\\Entities\\' . $targetEntity;
                            $em = Environment::getService('doctrine');
                            $_entity = $em->find($entityName, $value);
                            self::$entity->$setter($_entity);
                        }
                        # setting null entity
                        elseif ($value == '') {
                            self::$entity->$setter(null);
                        }
                    }
                }
                unset($values->$key);
            }

            $reflection = $reflection->getParentClass();
        } while ($reflection);
        #\Nette\Debug::barDump($entity);
        return true;
    }

    private static function getSetter($property, $reflection = null) {
        return self::getMethod($property, 'set', $reflection);
    }

    private static function getGetter($property, $reflection = null) {
        return self::getMethod($property, 'get', $reflection);
    }

    private static function getMethod($property, $preffix, $reflection) {
        if (is_null($reflection)) {
            $reflection = self::$reflection;
        }

        $property[0] = $property[0] & "\xDF";
        $method = $preffix . $property;
        return $reflection->hasMethod($method) && $reflection->getMethod($method)->isPublic() ? $method : false;
    }

    /**
     * @param \Nette\Reflection\Property $property
     * @param string $annotation
     * @return string|boolean
     */
    private static function getTargetEntity($property, $annotation) {
        return ($property->hasAnnotation($annotation) and
        isset($property->getAnnotation($annotation)->targetEntity)) ?
                $property->getAnnotation($annotation)->targetEntity : false;
    }

    /**
     * @param string $property
     * @param \Nette\Http\FileUpload $file
     */
    private static function handleFile($annotation, $property, $file) {
        if (!isset($annotation->path)) {
            throw new \InvalidArgumentException(self::$reflection->getName() . "'s property '$property' is missing required attribute 'path' in @file annotation.");
        }

        $base = isset($annotation->base) ? $annotation->base : realpath(Environment::getVariable('wwwDir'));
        $full = $base . DIRECTORY_SEPARATOR . $annotation->path;

        $getter = self::getGetter($property);
        $current = $getter ? self::$entity->$getter() : null;

        # delete current
        if ($file instanceof \Nette\Web\UploadedFile && $file->getUnlink() && $current) {
            \unlink($base . DIRECTORY_SEPARATOR . $current);
            return null;
        }
        # unchanged
        elseif ($file instanceof \Nette\Web\UploadedFile && $file->getUnlink() === false && $current) {
            return $file->name;
        }
        # uploaded new file, replace or add
        elseif (get_class($file) == 'Nette\Http\FileUpload' && $file->size > 0) {
            // replace, unlink current
            if ($current) {
                \unlink($base . DIRECTORY_SEPARATOR . $current);
            }

            // add new
            $dest = File::findSafeDestination($full . DIRECTORY_SEPARATOR . $file->name);
            $file->move($dest);

            if (\Nette\Utils\Strings::startsWith($dest, $base)) {
                return substr($dest, strlen($base));
            } else {
                throw new \InvalidArgumentException('Destination lookup failed.');
            }
        }
        # null
        elseif (get_class($file) == 'Nette\Http\FileUpload' && $file->getError() == \UPLOAD_ERR_NO_FILE) {
            return null;
        }
        # unknown case
        else {
            throw new \LogicException('invalid case');
        }
    }


}