<?php

/**
 * This behavior adds the necessary logic for polymorphic keys.
 * 
 * @package     plugins
 * @subpackage  sfPropelActAsPolymorphicBehaviorPlugin
 * @author      Kris Wallsmith <kris [dot] wallsmith [at] gmail [dot] com>
 * @version     SVN: $Id$
 */
class sfPropelActAsPolymorphicBehavior
{
  static protected
    $parameterHolders = array(),
    $customMethods    = array();
  
  /**
   * Mixin methods based on behavior configuration.
   * 
   * This method should be called after the behavior has been added to the 
   * OM class.
   * 
   * @param string $omClass
   */
  static public function mixinCustomMethods($omClass)
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
  
  /**
   * Listens for the 'context.load_factories' event in symfony >= 1.1 to mixin custom methods.
   * 
   * @param sfEvent $event
   */
  static public function listenForLoadFactories(sfEvent $event)
  {
    $context       = $event->getSubject();
    $configuration = $context->getConfiguration();

    $cache = new sfFileCache(array(
      'cache_dir' => sfConfig::get('sf_config_cache_dir').'/sfPropelActAsPolymorphic',
    ));

    if (is_null($classes = $cache->get('classes')))
    {
      $classes = array();

      $finder = sfFinder::type('file')->name('*MapBuilder.php');
      foreach ($finder->in($configuration->getModelDirs()) as $builder)
      {
        $class = basename($builder, 'MapBuilder.php');
        $fmt = 'propel_behavior_sfPropelActAsPolymorphic_'.$class.'_has_%s';
        if (
          class_exists($class)
          &&
          (sfConfig::has(sprintf($fmt, 'one')) || sfConfig::has(sprintf($fmt, 'many')))
        )
        {
          $classes[] = $class;
        }
      }

      $cache->set('classes', serialize($classes));
    }
    else
    {
      $classes = unserialize($classes);
    }

    foreach ($classes as $class)
    {
      self::mixinCustomMethods($class);
    }
  }
  
  /**
   * Process has_one references.
   * 
   * If a modified foreign object exist in the parameter holder for any of
   * the supplied object's has_one keys, save the foreign object and set the
   * local column values. This is the same business logic used in the Propel-
   * generated BaseXXX::doSave() methods.
   * 
   * @param   BaseObject $object
   * @param   Connection $con
   */
  public function preSave(BaseObject $object, $con)
  {
    foreach ($this->getParameterHolder($object)->getAll('polymorphic/has_one/reference') as $keyName => $foreignObject)
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
   * @param   BaseObject  $object
   * @param   Connection  $con
   * @param   integer     $affectedRows
   */
  public function postSave(BaseObject $object, $con, $affectedRows)
  {
    foreach ($this->getParameterHolder($object)->getAll('polymorphic/has_many/coll') as $keyName => $coll)
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
          if (0 !== strpos(constant(sfPropelActAsPolymorphicToolkit::getPeerClassName($foregnObject).'::TABLE_NAME'), $foreignModelCol))
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
  
  /**
   * Get the object referenced by the supplied has_one key.
   * 
   * If there is not a value in the parameter holder for this key, query the
   * database and store the result.
   * 
   * @param   BaseObject  $object
   * @param   string      $keyName
   * @param   Connection  $con
   * 
   * @return  BaseObject or null
   */
  public function getPolymorphicHasOneReference(BaseObject $object, $keyName, $con = null)
  {
    $parameterHolder = $this->getParameterHolder($object);
    
    if (!$parameterHolder->has($keyName, 'polymorphic/has_one/reference'))
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
      
      $foreignObject = null;
      if ($foreignModel && $foreignPK)
      {
        if (!class_exists($foreignModel))
        {
          throw new sfPropelActAsPolymorphicException('The referenced class does not exist: '.$foreignModel);
        }
        
        // retrieve object
        $foreignObject = call_user_func(array(sfPropelActAsPolymorphicToolkit::getPeerClassName($foreignModel), 'retrieveByPK'), $foreignPK, $con);
      }
      
      $parameterHolder->set($keyName, $foreignObject, 'polymorphic/has_one/reference');
    }
    
    return $parameterHolder->get($keyName, null, 'polymorphic/has_one/reference');
  }
  
  /**
   * Set the has_one reference for the supplied has_one key.
   * 
   * Set the object's key columns to reference the supplied foreign object 
   * and store the foreign object in the parameter holder.
   *
   * @param   BaseObject  $object
   * @param   string      $keyName
   * @param   mixed       $foreignObject
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
    
    if (is_null($foreignObject))
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
    
    $this->getParameterHolder($object)->set($keyName, $foreignObject, 'polymorphic/has_one/reference');
  }
  
  /**
   * Get a collection of foreign objects for a has_many key.
   * 
   * If there is no value for this key in the parameter holder or if the 
   * previous Criteria doesn't match the supplied Criteria, query the 
   * database for the collection and store the results in the parameter
   * holder.
   * 
   * @param   BaseObject  $object
   * @param   string      $keyName
   * @param   Criteria    $c
   * @param   Connection  $con
   * 
   * @return  array
   */
  public function getPolymorphicHasManyReferences(BaseObject $object, $keyName, $c = null, $con = null)
  {
    if (is_null($c))
    {
      $c = new Criteria;
    }
    elseif ($c instanceof Criteria)
    {
      $c = clone $c;
    }
    
    $parameterHolder = $this->getParameterHolder($object);
    
    if (!$parameterHolder->has($keyName, 'polymorphic/has_many/criteria') ||
        !$parameterHolder->get($keyName, new Criteria, 'polymorphic/has_many/criteria')->equals($c))
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
      
      $parameterHolder->set($keyName, $coll, 'polymorphic/has_one/coll');
    }
    
    return $parameterHolder->get($keyName, array(), 'polymorphic/has_one/coll');
  }
  
  /**
   * Add an object to a has_many key reference.
   * 
   * Store the foreign object to the parameter holder to be updated and saved
   * in the post-save hook.
   * 
   * @param   BaseObject  $object
   * @param   string      $keyName
   * @param   BaseObject  $foreignObject
   */
  public function addPolymorphicHasManyReference(BaseObject $object, $keyName, BaseObject $foreignObject)
  {
    $parameterHolder = $this->getParameterHolder($object);
    
    $coll = $parameterHolder->get($keyName, array(), 'polymorphic/has_many/coll');
    $coll[] = $foreignObject;
    $parameterHolder->set($keyName, $coll, 'polymorphic/has_many/coll');
  }
  
  /**
   * Count the number of references in the supplied key.
   * 
   * @param   BaseObject  $object
   * @param   string      $keyName
   * @param   Criteria    $c
   * @param   boolean     $distinct
   * @param   Connection  $con
   * 
   * @return  integer
   */
  public function countPolymorphicHasManyReferences(BaseObject $object, $keyName, $c = null, $distinct = false, $con = null)
  {
    if (is_null($c))
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
  
  /**
   * Get a parameter holder for the supplied object.
   * 
   * @param   BaseObject $object
   * 
   * @return  sfParameterHolder
   */
  protected function getParameterHolder(BaseObject $object)
  {
    if (!isset($object->sfPropelActAsPolymorphicParameterHolder))
    {
      $object->sfPropelActAsPolymorphicParameterHolder = class_exists('sfNamespacedParameterHolder') ? new sfNamespacedParameterHolder : new sfParameterHolder;
    }
    
    return $object->sfPropelActAsPolymorphicParameterHolder;
  }
  
  /**
   * Catch custom methods.
   * 
   * @throws  sfException if the method is unrecognized
   * 
   * @param   string  $method
   * @param   array   $args
   * 
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
