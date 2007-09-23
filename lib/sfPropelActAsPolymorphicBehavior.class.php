<?php

/**
 * This behavior adds the necessary logic for polymorphic keys.
 * 
 * Alongside Propel's native support for single table inheritance (STI), this
 * plugin provides quite a bit of flexibility in your database design.
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
 * This plugin does not support multi-column primary keys.
 * 
 * @package     plugins
 * @subpackage  sfPropelActAsPolymorphicBehaviorPlugin
 * @author      Kris Wallsmith <kris [dot] wallsmith [at] gmail [dot] com>
 * @version     SVN: $Id$
 * 
 * @todo        Add post-save hook to delay saving modified foreign objects.
 */
class sfPropelActAsPolymorphicBehavior
{
  // ---------------------------------------------------------------------- //
  // HAS_ONE METHODS
  // ---------------------------------------------------------------------- //
  
  /**
   * Get the object referenced by the supplied has_one key.
   * 
   * @author  Kris Wallsmith
   * @throws  sfPropelActAsPolymorphicException
   * 
   * @param   BaseObject $object
   * @param   string $keyName
   * @param   Connection $con
   * 
   * @return  BaseObject or null
   */
  public function getPolymorphicHasOneReference(BaseObject $object, $keyName, $con = null)
  {
    // pull key configuration
    $foreignModelCol = sfPropelActAsPolymorphicConfig::getHasOne($object, $keyName, 'foreign_model');
    $foreignPKCol    = sfPropelActAsPolymorphicConfig::getHasOne($object, $keyName, 'foreign_pk');
    if (!$foreignModelCol || !$foreignPKCol)
    {
      $msg = 'The class "%s" does not have a has_one reference named "%s."';
      $msg = sprintf($msg, get_class($object), $keyName);
      
      throw new sfPropelActAsPolymorphicException($msg);
    }
    
    // extract local key values
    $foreignModel = call_user_func(array($object, sfPropelActAsPolymorphicToolkit::forgeMethodName($object, $foreignModelCol)));
    $foreignPK    = call_user_func(array($object, sfPropelActAsPolymorphicToolkit::forgeMethodName($object, $foreignPKCol)));
    
    $foreignObject = null;
    if ($foreignModel && $foreignPK)
    {
      // confirm foreign class
      if (!class_exists($foreignModel))
      {
        $msg = 'The referenced foreign class "%s" does not exist.';
        $msg = sprintf($msg, $foreignModel);
        
        throw new sfPropelActAsPolymorphicException($msg);
      }
      
      // determine foreign peer
      $tmp = new $foreignModel;
      $foreignPeer = get_class($tmp->getPeer());
      
      // finally, look up the referenced object
      $foreignObject = call_user_func(array($foreignPeer, 'retrieveByPK'), $foreignPK, $con);
    }
    
    return $foreignObject;
  }
  
  /**
   * Set the has_one reference for the supplied has_one key.
   * 
   * Will throw an exception if the supplied foreign object has not been saved
   * to the database.
   *
   * @author  Kris Wallsmith
   * @throws  sfPropelActAsPolymorphicException
   * 
   * @param   BaseObject $object
   * @param   string $keyName
   * @param   mixed $foreignObject
   * @param   Connection $con
   */
  public function setPolymorphicHasOneReference(BaseObject $object, $keyName, $foreignObject, $con = null)
  {
    // has the supplied foreign object been saved yet?
    if ($foreignObject instanceof BaseObject && $foreignObject->isNew())
    {
      $msg = 'Please save the foreign "%s" object before adding it as a polymorphic reference.';
      $msg = sprintf($msg, get_class($foreignObject));
      
      throw new sfPropelActAsPolymorphicException($msg);
    }
    
    // pull key configuration
    $foreignModelCol = sfPropelActAsPolymorphicConfig::getHasOne($object, $keyName, 'foreign_model');
    $foreignPKCol    = sfPropelActAsPolymorphicConfig::getHasOne($object, $keyName, 'foreign_pk');
    if (!$foreignModelCol || !$foreignPKCol)
    {
      $msg = 'The class "%s" does not have a has_one reference named "%s."';
      $msg = sprintf($msg, get_class($object), $keyName);
      
      throw new sfPropelActAsPolymorphicException($msg);
    }
    
    // forge setter methods
    $setModel = sfPropelActAsPolymorphicToolkit::forgeMethodName($object, $foreignModelCol, 'set');
    $setPK    = sfPropelActAsPolymorphicToolkit::forgeMethodName($object, $foreignPKCol, 'set');
    
    if ($foreignObject === null)
    {
      // set key columns to null
      $object->$setModel(null);
      $object->$setPK(null);
    }
    else
    {
      // set key columns
      $object->$setModel(sfPropelActAsPolymorphicToolkit::getDefaultOMClass($foreignObject));
      $object->$setPK($foreignObject->getPrimaryKey());
    }
  }
  
  /**
   * Clear a has_one reference.
   * 
   * @author  Kris Wallsmith
   * 
   * @param   BaseObject $object
   * @param   string $keyName
   * @param   Connection $con
   */
  public function clearPolymorphicHasOneReference(BaseObject $object, $keyName, $con = null)
  {
    $this->setPolymorphicHasOneReference($object, $keyName, null, $con);
  }
  
  // ---------------------------------------------------------------------- //
  // HAS_MANY METHODS
  // ---------------------------------------------------------------------- //
  
  /**
   * Get a collection of foreign objects for a has_many key.
   * 
   * The query is only built and fun if the supplied object has already been
   * saved to the database.
   * 
   * @author  Kris Wallsmith
   * @throws  sfPropelActAsPolymorphicException
   * 
   * @param   BaseObject $object
   * @param   string $keyName
   * @param   Criteria $c
   * @param   Connection $con
   * 
   * @return  array
   */
  public function getPolymorphicHasManyReferences(BaseObject $object, $keyName, $c = null, $con = null)
  {
    // pull key configuration
    $foreignModelCol = sfPropelActAsPolymorphicConfig::getHasMany($object, $keyName, 'foreign_model');
    $foreignPKCol    = sfPropelActAsPolymorphicConfig::getHasMany($object, $keyName, 'foreign_pk');
    if (!$foreignModelCol || !$foreignPKCol)
    {
      $msg = 'The class "%s" does not have a has_many reference named "%s."';
      $msg = sprintf($msg, get_class($object), $keyName);
      
      throw new sfPropelActAsPolymorphicException($msg);
    }
    
    // prepare criteria
    if ($c === null)
    {
      $c = new Criteria;
    }
    elseif ($c instanceof Criteria)
    {
      $c = clone $c;
    }
    
    // query the collection
    $coll = array();
    if (!$object->isNew())
    {
      $peerClass = sfPropelActAsPolymorphicToolkit::getPeerClassFromColName($foreignModelCol);
      
      $c->add(constant($peerClass.'::'.$foreignModelCol), sfPropelActAsPolymorphicToolkit::getDefaultOmClass($object));
      $c->add(constant($peerClass.'::'.$foreignPKCol), $object->getPrimaryKey());
      
      $peerMethod = sfPropelActAsPolymorphicConfig::getHasMany($object, $keyName, 'peer_method', 'doSelect');
      $coll = call_user_func(array($peerClass, $peerMethod), $c, $con);
    }
    
    return $coll;
  }
  
  /**
   * Add a foreign object to a has_many key reference.
   * 
   * Will throw an exception if the supplied local object has not yet been
   * saved to the database.
   * 
   * @author  Kris Wallsmith
   * @throws  sfPropelActAsPolymorphicException
   * 
   * @param   BaseObject $object
   * @param   string $keyName
   * @param   BaseObject $foreignObject
   * @param   Connection $con
   * 
   * @return  int
   */
  public function addPolymorphicHasManyReference(BaseObject $object, $keyName, BaseObject $foreignObject, $con = null)
  {
    // is this object in the database?
    if ($object->isNew())
    {
      $msg = 'Please save your object to the database before adding a polymorphic reference.';
      
      throw new sfPropelActAsPolymorphicException($msg);
    }
    
    // pull key configuration
    $foreignModelCol = sfPropelActAsPolymorphicConfig::getHasMany($object, $keyName, 'foreign_model');
    $foreignPKCol    = sfPropelActAsPolymorphicConfig::getHasMany($object, $keyName, 'foreign_pk');
    if (!$foreignModelCol || !$foreignPKCol)
    {
      $msg = 'The class "%s" does not have a has_many reference named "%s."';
      $msg = sprintf($msg, get_class($object), $keyName);
      
      throw new sfPropelActAsPolymorphicException($msg);
    }
    
    // confirm the supplied object belongs in this key by comparing its table
    // name to the key's table name, embedded in the configured column name.
    $foreignTableName = constant(get_class($foregnObject->getPeer()).'::TABLE_NAME');
    if (strpos($foreignTableName, $foreignModelCol) !== 0)
    {
      $msg = 'The foreign "%s" object does not appear to belong in the has_many reference "%s".';
      $msg = sprintf($msg, get_class($foreignObject), $keyName);
      
      throw new sfPropelActAsPolymorphicException($msg);
    }
    
    // modify and save the foreign object
    $setModel = sfPropelActAsPolymorphicToolkit::forgeMethodName($foreignObject, $foreignModelCol, 'set');
    $setPK    = sfPropelActAsPolymorphicToolkit::forgeMethodName($foreignObject, $foreignPKCol, 'set');
    
    $foreignObject->$setModel(sfPropelActAsPolymorphicToolkit::getDefaultOmClass($object));
    $foreignObject->$setPK($object->getPrimaryKey());
    $affectedRows = $foreignObject->save($con);
    
    return $affectedRows;
  }
  
  /**
   * Clear references in a has_many key.
   * 
   * @author  Kris Wallsmith
   * @throws  sfPropelActAsPolymorphicException
   * 
   * @param   BaseObject $object
   * @param   string $keyName
   * @param   Criteria $c
   * @param   bool $doDelete
   * @param   Connection $con
   * 
   * @return  int
   */
  public function clearPolymorphicHasManyReferences(BaseObject $object, $keyName, $c = null, $doDelete = false, $con = null)
  {
    // pull key configuration
    $foreignModelCol = sfPropelActAsPolymorphicConfig::getHasMany($object, $keyName, 'foreign_model');
    $foreignPKCol    = sfPropelActAsPolymorphicConfig::getHasMany($object, $keyName, 'foreign_pk');
    if (!$foreignModelCol || !$foreignPKCol)
    {
      $msg = 'The class "%s" does not have a has_many reference named "%s."';
      $msg = sprintf($msg, get_class($object), $keyName);
      
      throw new sfPropelActAsPolymorphicException($msg);
    }
    
    $affectedRows = 0;
    if (!$object->isNew())
    {
      $peerClass = sfPropelActAsPolymorphicToolkit::getPeerClassFromColName($foreignModelCol);
      
      // prepare criteria and connection
      if ($c === null)
      {
        $c = new Criteria;
      }
      elseif ($c instanceof Criteria)
      {
        $c = clone $c;
      }
      if ($con === null)
      {
        $con = Propel::getConnection(constant($peerClass.'::DATABASE_NAME'));
      }
      
      // update the criteria
      $c->add(constant($peerClass.'::'.$foreignModelCol), sfPropelActAsPolymorphicToolkit::getDefaultOmClass($object));
      $c->add(constant($peerClass.'::'.$foreignPKCol), $object->getPrimaryKey());
      
      if ($doDelete)
      {
        // delete all referenced objects
        $affectedRows = BasePeer::doDelete($c, $con);
      }
      else
      {
        // set all references to null
        $update_c = new Criteria;
        $update_c->add(constant($peerClass.'::'.$foreignModelCol), null);
        $update_c->add(constant($peerClass.'::'.$foreignPKCol), null);
        
        $affectedRows = BasePeer::doUpdate($c, $update_c, $con);
      }
    }
    
    return $affectedRows;
  }
  
  /**
   * Delete referenced records in a has_many key.
   *
   * @author  Kris Wallsmith
   * 
   * @param   BaseObject $object
   * @param   string $keyName
   * @param   Criteria $c
   * @param   Connection $con
   * 
   * @return  int
   */
  public function deletePolymorphicHasManyReferences(BaseObject $object, $keyName, $c = null, $con = null)
  {
    return $this->clearPolymorphicHasManyReferences($object, $keyName, $c, true, $con);
  }
  
  /**
   * Count the number of references in the supplied key.
   * 
   * @author  Kris Wallsmith
   * 
   * @param   BaseObject $object
   * @param   string $keyName
   * @param   Criteria $c
   * @param   bool $distinct
   * @param   Connection $con
   * 
   * @return  int
   */
  public function countPolymorphicHasManyReferences(BaseObject $object, $keyName, $c = null, $distinct = false, $con = null)
  {
    // pull key configuration
    $foreignModelCol = sfPropelActAsPolymorphicConfig::getHasMany($object, $keyName, 'foreign_model');
    $foreignPKCol    = sfPropelActAsPolymorphicConfig::getHasMany($object, $keyName, 'foreign_pk');
    if (!$foreignModelCol || !$foreignPKCol)
    {
      $msg = 'The class "%s" does not have a has_many reference named "%s."';
      $msg = sprintf($msg, get_class($object), $keyName);
      
      throw new sfPropelActAsPolymorphicException($msg);
    }
    
    $count = 0;
    if (!$object->isNew())
    {
      if ($c === null)
      {
        $c = new Criteria;
      }
      elseif ($c instanceof Criteria)
      {
        $c = clone $c;
      }
      
      $peerClass = sfPropelActAsPolymorphicToolkit::getPeerClassFromColName($foreignModelCol);
      
      $c->add(constant($peerClass.'::'.$foreignModelCol), sfPropelActAsPolymorphicToolkit::getDefaultOmClass($object));
      $c->add(constant($peerClass.'::'.$foreignPKCol), $object->getPrimaryKey());
      
      $count = call_user_func(array($peerClass, 'doCount'), $c, $distinct, $con);
    }
    
    return $count;
  }
  
}
