<?php

/**
 * Static utility methods.
 * 
 * @package     plugins
 * @subpackage  sfPropelActAsPolymorphicBehaviorPlugin
 * @author      Kris Wallsmith <kris [dot] wallsmith [at] gmail [dot] com>
 * @version     SVN: $Id$
 */
class sfPropelActAsPolymorphicToolkit
{
  /**
   * Extract a peer class name from a column name.
   * 
   * @author  Kris Wallsmith
   * @throws  sfPropelActAsPolymorphicException
   * 
   * @param   string $colName
   * 
   * @return  string
   */
  public static function getPeerClassFromColName($colName)
  {
    $tableName = substr($colName, 0, strpos($colName, '.'));
    $tableMap  = Propel::getDatabaseMap()->getTable($tableName);
    if (!$tableMap)
    {
      $msg = 'The table "%s" extracted from "%s" does not exist.';
      $msg = sprintf($msg, $tableName, $colName);
      
      throw new sfPropelActAsPolymorphicException($msg);
    }
    
    $omClass   = $tableMap->getPhpName();
    $peerClass = get_class(call_user_func(array(new $omClass, 'getPeer')));
    
    return $peerClass;
  }
  
  /**
   * Get the default OM class for the supplied object.
   * 
   * @author  Kris Wallsmith
   * 
   * @param   BaseObject $object
   * 
   * @return  string
   */
  public static function getDefaultOmClass(BaseObject $object)
  {
    return Propel::import(constant(get_class($object->getPeer()) . '::CLASS_DEFAULT'));
  }
  
  /**
   * Convert column name to method name.
   * 
   * @author  Kris Wallsmith
   * 
   * @param   BaseObject $object
   * @param   string $columnName
   * @param   string $prefix
   * 
   * @return  string
   */
  public static function forgeMethodName(BaseObject $object, $colName, $prefix = 'get')
  {
    // translate from colName to phpName
    $peerClass = get_class($object->getPeer());
    $phpName = call_user_func(array($peerClass, 'translateFieldName'), $colName, BasePeer::TYPE_COLNAME, BasePeer::TYPE_PHPNAME);
    
    $methodName = $prefix . $phpName;
    
    return $methodName;
  }
  
}
