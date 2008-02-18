<?php

/**
 * This behavior adds the necessary logic for polymorphic keys.
 * 
 * To enable this behavior, add the following code to the end of a Propel OM
 * class declaration:
 * 
 * <code>
 *  class Record extends BaseRecord
 *  {
 *  }
 *  $hasOneKeys  = array('author'   => array('foreign_model' => RecordPeer::AUTHOR_TYPE,
 *                                           'foreign_pk'    => RecordPeer::AUTHOR_ID));
 *  $hasManyKeys = array('comments' => array('foreign_model' => CommentPeer::SUBJECT_TYPE,
 *                                           'foreign_pk'    => CommentPeer::SUBJECT_ID));
 *  sfPropelBehavior::add('Record', array('sfPropelActAsPolymorphic' => array('has_one'  => $hasOneKeys,
 *                                                                            'has_many' => $hasManyKeys)));
 * </code>
 * 
 * After adding the behavior you can optionally "mixin" custom methods based on
 * the name of your keys:
 * 
 * <code>
 *  sfPropelActAsPolymorphicBehavior::mixinCustomMethods('Record');
 * </code>
 * 
 * This will add a number of vanity methods to your class: getXXX and setXXX 
 * for has_one keys, and getXXX, addXXX and countXXX for has_many keys, 
 * where XXX is the camelCased key name.
 * 
 * This plugin does not support multi-column primary keys.
 * 
 * @package     plugins
 * @subpackage  sfPropelActAsPolymorphicBehaviorPlugin
 * @author      Kris Wallsmith <kris [dot] wallsmith [at] gmail [dot] com>
 * @version     SVN: $Id$
 */
class sfPropelActAsPolymorphicBehavior
{
  static $customMethods = array();
  
  // ---------------------------------------------------------------------- //
  // STATIC METHODS
  // ---------------------------------------------------------------------- //
  
  /**
   * Mixin methods based on behavior configuration.
   * 
   * This method should be called after the behavior has been added to the 
   * OM class.
   * 
   * @author  Kris Wallsmith
   * @throws  sfPropelActAsPolymorphicException on method name conflict
   * @param   string $omClass
   */
  public static function mixinCustomMethods($omClass)
  {
    $keys = array(
      'has_one'  => sfPropelActAsPolymorphicConfig::getAll(new $omClass, 'has_one'),
      'has_many' => sfPropelActAsPolymorphicConfig::getAll(new $omClass, 'has_many'));
    
    foreach ($keys as $type => $key)
    {
      $prefixes = sfPropelActAsPolymorphicToolkit::getMethodPrefixes($type);
      foreach ($key as $name => $config)
      {
        $camelCase = sfInflector::camelize($name);
        foreach ($prefixes as $prefix)
        {
          $method = $prefix.$camelCase;
          if (method_exists($omClass, $method) || isset(sfPropelActAsPolymorphicBehavior::$customMethods[$omClass][$method]))
          {
            $msg = 'The class "%s" already has a method named "%s". Please rename either the method or your "%s" %s polymorphic key.';
            $msg = sprintf($msg, $omClass, $method, $name, $type);
            
            throw new sfPropelActAsPolymorphicException($msg);
          }
          
          if (!isset(sfPropelActAsPolymorphicBehavior::$customMethods[$omClass]))
          {
            sfPropelActAsPolymorphicBehavior::$customMethods[$omClass] = array();
          }
          sfPropelActAsPolymorphicBehavior::$customMethods[$omClass][$method] = array($type, $prefix, $name);
          
          sfMixer::register('Base'.$omClass, array(new sfPropelActAsPolymorphicBehavior, $method));
        }
      }
    }
  }
  
  // ---------------------------------------------------------------------- //
  // HOOKS
  // ---------------------------------------------------------------------- //
  
  /**
   * Process has_one references.
   * 
   * If a modified foreign object exist in the parameter holder for any of
   * the supplied object's has_one keys, save the foreign object and set the
   * local column values. This is the same business logic used in the Propel-
   * generated BaseXXX::doSave() methods.
   * 
   * @author  Kris Wallsmith
   * @param   BaseObject $object
   * @param   Connection $con
   */
  public function preSave(BaseObject $object, $con)
  {
    foreach ($object->getParameterHolder()->getAll('polymorphic/has_one/reference') as $keyName => $foreignObject)
    {
      if ($foreignObject && $foreignObject->isModified())
      {
        $foreignObject->save($con);
        $object->setPolymorphicHasOneReference($keyName, $foreignObject);
      }
    }
  }
  
  /**
   * Process has_many collections.
   * 
   * Update and save the foreign objects stored in the parameter holder for 
   * this object's has_many keys.
   * 
   * @author  Kris Wallsmith
   * @param   BaseObject $object
   * @param   Connection $con
   * @param   int $affectedRows
   */
  public function postSave(BaseObject $object, $con, $affectedRows)
  {
    foreach ($object->getParameterHolder()->getAll('polymorphic/has_many/coll') as $keyName => $coll)
    {
      // key config
      $foreignModelCol = sfPropelActAsPolymorphicConfig::getHasMany($object, $keyName, 'foreign_model');
      $foreignPKCol = sfPropelActAsPolymorphicConfig::getHasMany($object, $keyName, 'foreign_pk');
      
      if (!$foreignModelCol || !$foreignPKCol)
      {
        throw new sfPropelActAsPolymorphicException('Unrecognized polymorphic key: '.$keyName);
      }
      
      // forge setter methods
      $setModel = sfPropelActAsPolymorphicToolkit::forgeMethodName($foreignObject, $foreignModelCol, 'set');
      $setPK = sfPropelActAsPolymorphicToolkit::forgeMethodName($foreignObject, $foreignPKCol, 'set');
      
      foreach ($coll as $foreignObject)
      {
        if (!$foreignObject->isDeleted())
        {
          // confirm object belongs in this key
          if (strpos(constant(get_class($foregnObject->getPeer()).'::TABLE_NAME'), $foreignModelCol) !== 0)
          {
            throw new sfPropelActAsPolymorphicException('The supplied object does not belong in this key.');
          }
          
          $foreignObject->$setModel(sfPropelActAsPolymorphicToolkit::getDefaultOmClass($object));
          $foreignObject->$setPK($object->getPrimaryKey());
          $foreignObject->save($con);
        }
      }
    }
  }
  
  // ---------------------------------------------------------------------- //
  // HAS_ONE METHODS
  // ---------------------------------------------------------------------- //
  
  /**
   * Get the object referenced by the supplied has_one key.
   * 
   * If there is not a value in the parameter holder for this key, query the
   * database and store the result.
   * 
   * @author  Kris Wallsmith
   * @param   BaseObject $object
   * @param   string $keyName
   * @param   Connection $con
   * @return  BaseObject or null
   */
  public function getPolymorphicHasOneReference(BaseObject $object, $keyName, $con = null)
  {
    if (!$object->getParameterHolder()->has($keyName, 'polymorphic/has_one/reference'))
    {
      // key config
      $foreignModelCol = sfPropelActAsPolymorphicConfig::getHasOne($object, $keyName, 'foreign_model');
      $foreignPKCol = sfPropelActAsPolymorphicConfig::getHasOne($object, $keyName, 'foreign_pk');
      
      if (!$foreignModelCol || !$foreignPKCol)
      {
        throw new sfPropelActAsPolymorphicException('Unrecognized polymorphic key: '.$keyName);
      }
      
      // key values
      $foreignModel = call_user_func(array($object, sfPropelActAsPolymorphicToolkit::forgeMethodName($object, $foreignModelCol)));
      $foreignPK = call_user_func(array($object, sfPropelActAsPolymorphicToolkit::forgeMethodName($object, $foreignPKCol)));
      
      if (!class_exists($foreignModel))
      {
        throw new sfPropelActAsPolymorphicException('The referenced class does not exist: '.$foreignModel);
      }
      
      $foreignObject = null;
      if ($foreignModel && $foreignPK)
      {
        // retrieve object
        $foreignPeer = get_class(call_user_func(array(new $foreignModel, 'getPeer')));
        $foreignObject = call_user_func(array($foreignPeer, 'retrieveByPK'), $foreignPK, $con);
      }
      
      $object->getParameterHolder()->set($keyName, $foreignObject, 'polymorphic/has_one/reference');
    }
    
    return $object->getParameterHolder()->get($keyName, null, 'polymorphic/has_one/reference');
  }
  
  /**
   * Set the has_one reference for the supplied has_one key.
   * 
   * Set the object's key columns to reference the supplied foreign object 
   * and store the foreign object in the parameter holder.
   *
   * @author  Kris Wallsmith
   * @param   BaseObject $object
   * @param   string $keyName
   * @param   mixed $foreignObject
   */
  public function setPolymorphicHasOneReference(BaseObject $object, $keyName, $foreignObject)
  {
    // key config
    $foreignModelCol = sfPropelActAsPolymorphicConfig::getHasOne($object, $keyName, 'foreign_model');
    $foreignPKCol = sfPropelActAsPolymorphicConfig::getHasOne($object, $keyName, 'foreign_pk');
    
    if (!$foreignModelCol || !$foreignPKCol)
    {
      throw new sfPropelActAsPolymorphicException('Unrecognized polymorphic key: '.$keyName);
    }
    
    // forge setter methods
    $setModel = sfPropelActAsPolymorphicToolkit::forgeMethodName($object, $foreignModelCol, 'set');
    $setPK = sfPropelActAsPolymorphicToolkit::forgeMethodName($object, $foreignPKCol, 'set');
    
    if ($foreignObject === null)
    {
      $object->$setModel(null);
      $object->$setPK(null);
    }
    elseif ($foreignObject instanceof BaseObject)
    {
      $object->$setModel(sfPropelActAsPolymorphicToolkit::getDefaultOMClass($foreignObject));
      $object->$setPK($foreignObject->getPrimaryKey());
    }
    else
    {
      throw new sfPropelActAsPolymorphicException('The referenced foreign object ['.$foreignObject.'] is neither NULL nor a BaseObject');
    }
    
    $object->getParameterHolder()->set($keyName, $foreignObject, 'polymorphic/has_one/reference');
  }
  
  // ---------------------------------------------------------------------- //
  // HAS_MANY METHODS
  // ---------------------------------------------------------------------- //
  
  /**
   * Get a collection of foreign objects for a has_many key.
   * 
   * If there is no value for this key in the parameter holder or if the 
   * previous Criteria doesn't match the supplied Criteria, query the 
   * database for the collection and store the results in the parameter
   * holder.
   * 
   * @author  Kris Wallsmith
   * @param   BaseObject $object
   * @param   string $keyName
   * @param   Criteria $c
   * @param   Connection $con
   * @return  array
   */
  public function getPolymorphicHasManyReferences(BaseObject $object, $keyName, $c = null, $con = null)
  {
    if ($c === null)
    {
      $c = new Criteria;
    }
    elseif ($c instanceof Criteria)
    {
      $c = clone $c;
    }
    
    if (!$object->getParameterHolder()->has($keyName, 'polymorphic/has_many/criteria') ||
        !$object->getParameterHolder()->get($keyName, new Criteria, 'polymorphic/has_many/criteria')->equals($c))
    {
      // key config
      $foreignModelCol = sfPropelActAsPolymorphicConfig::getHasMany($object, $keyName, 'foreign_model');
      $foreignPKCol = sfPropelActAsPolymorphicConfig::getHasMany($object, $keyName, 'foreign_pk');
      
      if (!$foreignModelCol || !$foreignPKCol)
      {
        throw new sfPropelActAsPolymorphicException('Unrecognized polymorphic key: '.$keyName);
      }
      
      $coll = array();
      if (!$object->isNew())
      {
        // query collection
        $peerClass = sfPropelActAsPolymorphicToolkit::getPeerClassFromColName($foreignModelCol);
        
        $c->add($foreignModelCol, sfPropelActAsPolymorphicToolkit::getDefaultOmClass($object));
        $c->add($foreignPKCol, $object->getPrimaryKey());
        
        $peerMethod = sfPropelActAsPolymorphicConfig::getHasMany($object, $keyName, 'peer_method', 'doSelect');
        $coll = call_user_func(array($peerClass, $peerMethod), $c, $con);
      }
      
      $object->getParameterHolder()->set($keyName, $coll, 'polymorphic/has_one/coll');
    }
    
    return $object->getParameterHolder()->get($keyName, array(), 'polymorphic/has_one/coll');
  }
  
  /**
   * Add an object to a has_many key reference.
   * 
   * Store the foreign object to the parameter holder to be updated and saved
   * in the post-save hook.
   * 
   * @author  Kris Wallsmith
   * @param   BaseObject $object
   * @param   string $keyName
   * @param   BaseObject $foreignObject
   */
  public function addPolymorphicHasManyReference(BaseObject $object, $keyName, BaseObject $foreignObject)
  {
    $coll = $object->getParameterHolder()->get($keyName, array(), 'polymorphic/has_many/coll');
    $coll[] = $foreignObject;
    $object->getParameterHolder()->set($keyName, $coll, 'polymorphic/has_many/coll');
  }
  
  /**
   * Count the number of references in the supplied key.
   * 
   * @author  Kris Wallsmith
   * @param   BaseObject $object
   * @param   string $keyName
   * @param   Criteria $c
   * @param   bool $distinct
   * @param   Connection $con
   * @return  int
   */
  public function countPolymorphicHasManyReferences(BaseObject $object, $keyName, $c = null, $distinct = false, $con = null)
  {
    if ($c === null)
    {
      $c = new Criteria;
    }
    elseif ($c instanceof Criteria)
    {
      $c = clone $c;
    }
    
    // key config
    $foreignModelCol = sfPropelActAsPolymorphicConfig::getHasMany($object, $keyName, 'foreign_model');
    $foreignPKCol = sfPropelActAsPolymorphicConfig::getHasMany($object, $keyName, 'foreign_pk');
    
    if (!$foreignModelCol || !$foreignPKCol)
    {
      throw new sfPropelActAsPolymorphicException('Unrecognized polymorphic key: '.$keyName);
    }
    
    // query count
    $c->add($foreignModelCol, sfPropelActAsPolymorphicToolkit::getDefaultOmClass($object));
    $c->add($foreignPKCol, $object->getPrimaryKey());
    
    $peerClass = sfPropelActAsPolymorphicToolkit::getPeerClassFromColName($foreignModelCol);
    
    return call_user_func(array($peerClass, 'doCount'), $c, $distinct, $con);
  }
  
  // ---------------------------------------------------------------------- //
  // UTILITY METHODS
  // ---------------------------------------------------------------------- //
  
  /**
   * Get this object's parameter holder, used to cache queries.
   * 
   * This mixed-in method uses a common name, but hopefully any other plugin
   * that mixes in the same method will also use the same functionality.
   * 
   * @author  Kris Wallsmith
   * @param   BaseObject $object
   * @return  sfParameterHolder
   */
  public function getParameterHolder(BaseObject $object)
  {
    if (empty($object->parameterHolder))
    {
      $object->parameterHolder = new sfParameterHolder;
    }
    
    return $object->parameterHolder;
  }
  
  // ---------------------------------------------------------------------- //
  // INTERNAL
  // ---------------------------------------------------------------------- //
  
  /**
   * Catch custom methods.
   * 
   * @author  Kris Wallsmith
   * @throws  sfException
   * @param   string $method
   * @param   array $args
   * @return  mixed
   */
  public function __call($method, $args)
  {
    $object = array_shift($args);
    $omClass = sfPropelActAsPolymorphicToolkit::getDefaultOMClass($object);
    
    if (isset(sfPropelActAsPolymorphicBehavior::$customMethods[$omClass][$method]))
    {
      list($type, $prefix, $name) = sfPropelActAsPolymorphicBehavior::$customMethods[$omClass][$method];
      
      $mixin = '%sPolymorphic%sReference%s';
      $mixin = sprintf($mixin, $prefix, sfInflector::camelize($type), $type == 'has_many' ? 's' : null);
      
      array_unshift($args, $name);
      array_unshift($args, $object);
      
      return call_user_func_array(array($this, $mixin), $args);
    }
    else
    {
      throw new sfException(sprintf('Call to undefined method %s::%s', $omClass, $method));
    }
  }
  
}
