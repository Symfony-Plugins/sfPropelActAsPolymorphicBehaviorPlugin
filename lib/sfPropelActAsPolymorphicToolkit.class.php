<?php

/**
 * Static utility methods.
 * 
 * @package     sfPropelActAsPolymorphicBehaviorPlugin
 * @subpackage  util
 * @author      Kris Wallsmith <kris [dot] wallsmith [at] gmail [dot] com>
 * @version     SVN: $Id$
 */
class sfPropelActAsPolymorphicToolkit
{
  /**
   * Extract a peer class name from a column name.
   * 
   * @param   string $colName
   * 
   * @return  string
   */
  static public function getPeerClassFromColName($colName)
  {
    $tableName = substr($colName, 0, strpos($colName, '.'));
    $omClass   = Propel::getDatabaseMap()->getTable($tableName)->getPhpName();
    
    return self::getPeerClassName($omClass);
  }
  
  /**
   * Get the default OM class for the supplied object.
   * 
   * @param   mixed $object
   * 
   * @return  string
   */
  static public function getDefaultOmClass($object)
  {
    return Propel::import(constant(self::getPeerClassName($object).'::CLASS_DEFAULT'));
  }
  
  /**
   * Get the name of the supplied object's Peer class.
   * 
   * @param   mixed $object Either an instance of BaseObject or an OM class name
   * 
   * @return  string
   */
  static public function getPeerClassName($object)
  {
    if (is_string($object) && class_exists($object))
    {
      // try to concat 'Peer' and check CLASS_DEFAULT constant to confirm
      if (class_exists($object.'Peer') && '.'.$object == substr(constant($object.'Peer::CLASS_DEFAULT'), (strlen($object) + 1) * -1))
      {
        return $object.'Peer';
      }
      
      $object = new $object;
    }
    
    if (false === ($object instanceof BaseObject))
    {
      throw new InvalidArgumentException('Argument is not an instance of BaseObject');
    }
    
    return get_class($object->getPeer());
  }
  
  /**
   * Convert column name to method name.
   * 
   * @param   BaseObject $object
   * @param   string $columnName
   * @param   string $prefix
   * 
   * @return  string
   */
  static public function forgeMethodName(BaseObject $object, $colName, $prefix = 'get')
  {
    // translate to phpName
    $phpName = $object->getPeer()->translateFieldName($colName, BasePeer::TYPE_COLNAME, BasePeer::TYPE_PHPNAME);
    $methodName = $prefix.$phpName;
    
    return $methodName;
  }
  
  /**
   * Get prefixes for the supplied key type.
   * 
   * @param   string
   * 
   * @return  array
   */
  static public function getMethodPrefixes($keyType)
  {
    $prefixes = array();
    switch ($keyType)
    {
      case 'has_one':
      $prefixes = array('get', 'set');
      break;
      
      case 'has_many':
      $prefixes = array('get', 'add', 'count');
      break;
      
      default:
      throw new sfPropelActAsPolymorphicException(sprintf('Unrecognized polymorphic key type "%s".', $keyType));
    }
    
    return $prefixes;
  }
}
