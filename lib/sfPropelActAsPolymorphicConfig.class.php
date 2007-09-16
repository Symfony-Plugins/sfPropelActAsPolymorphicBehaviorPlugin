<?php

/**
 * Class of static methods for interacting with behavior configuration values.
 * 
 * @package     plugins
 * @subpackage  sfPropelActAsPolymorphicBehaviorPlugin
 * @author      Kris Wallsmith <kris [dot] wallsmith [at] gmail [dot] com>
 * @version     SVN: $Id$
 */
class sfPropelActAsPolymorphicConfig
{
  const CONFIG_KEY_FMT = 'propel_behavior_sfPropelActAsPolymorphic_%s_%s';
  
  /**
   * Get a configuration value.
   * 
   * @author  Kris Wallsmith
   * 
   * @param   BaseObject $object
   * @param   string $keyName
   * @param   string $paramName
   * @param   string $keyType
   * @param   mixed $defaultValue
   * 
   * @return  mixed
   */
  public static function get(BaseObject $object, $keyName, $paramName, $keyType = 'has_one', $defaultValue = null)
  {
    $keyConfig = self::getAll($object, $keyName, $keyType);
    
    $retval = $defaultValue;
    if(isset($keyConfig[$paramName]))
    {
      $retval = $keyConfig[$paramName];
    }
    
    return $retval;
  }
  
  /**
   * Get a has_one key configuration value.
   * 
   * @author  Kris Wallsmith
   * 
   * @param   BaseObject $object
   * @param   string $keyName
   * @param   string $paramName
   * @param   mixed $defaultValue
   * 
   * @return  mixed
   */
  public static function getHasOne(BaseObject $object, $keyName, $paramName, $defaultValue = null)
  {
    return self::get($object, $keyName, $paramName, 'has_one', $defaultValue);
  }
  
  /**
   * Get a has_many key configuration value.
   * 
   * @author  Kris Wallsmith
   * 
   * @param   BaseObject $object
   * @param   string $keyName
   * @param   string $paramName
   * @param   mixed $defaultValue
   * 
   * @return  mixed
   */
  public static function getHasMany(BaseObject $object, $keyName, $paramName, $defaultValue = null)
  {
    return self::get($object, $keyName, $paramName, 'has_many', $defaultValue);
  }
  
  /**
   * Get all configuration values for a named key.
   * 
   * @author  Kris Wallsmith
   * 
   * @param   BaseObject $object
   * @param   string $keyName
   * @param   string $keyType
   * 
   * @return  array
   */
  public static function getAll(BaseObject $object, $keyName = null, $keyType = 'has_one')
  {
    // don't automatically jump to the default class just yet
    $omClass = get_class($object);
    $keys = sfConfig::get(sprintf(self::CONFIG_KEY_FMT, $omClass, $keyType), array());
    
    // if there is no configuration data, check the default class
    if (!count($keys))
    {
      $defaultOmClass = sfPropelActAsPolymorphicToolkit::getDefaultOmClass($object);
      if ($defaultOmClass != $omClass)
      {
        $keys = sfConfig::get(sprintf(self::CONFIG_KEY_FMT, $defaultOmClass, $keyType), array());
      }
    }
    
    // now that we have the keys for the supplied key type, return the named
    // key that was requested
    $retval = $keys;
    if($keyName !== null && isset($keys[$keyName]))
    {
      $retval = $keys[$keyName];
    }
    
    return $retval;
  }
  
}
