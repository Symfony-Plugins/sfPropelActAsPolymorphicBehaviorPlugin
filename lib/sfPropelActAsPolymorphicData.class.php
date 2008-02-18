<?php

/**
 * An extension of sfPropelData for loading polymorphic fixtures.
 * 
 * @package     plugins
 * @subpackage  sfPropelActAsPolymorphicBehaviorPlugin
 * @author      Kris Wallsmith <kris [dot] wallsmith [at] gmail [dot] com>
 * @version     SVN: $Id$
 */
class sfPropelActAsPolymorphicData extends sfPropelData
{
  /**
   * Catch any polymorphic exceptions.
   * 
   * @author  Kris Wallsmith
   * @param   array $data
   */
  public function loadDataFromArray($data)
  {
    try
    {
      parent::loadDataFromArray($data);
    }
    catch (sfException $e)
    {
      $trace = $e->getTrace();
      $trace = $trace[0];
      
      if ($e instanceof sfPropelActAsPolymorphicException)
      {
        $omClass = get_class($trace['args'][0]);
        $setter = 'set'.sfInflector::camelize($trace['args'][1]);
        $foreignObjectRef = $trace['args'][2];
      }
      elseif (strpos($trace['class'], 'Base') === 0 && $trace['function'] == '__call')
      {
        $omClass = substr($trace['class'], 4);
        $setter = $trace['args'][0];
        $foreignObjectRef = $trace['args'][1][0];
      }
      
      // test for behavior
      if (isset($omClass) && is_callable(array(new $omClass, 'getPolymorphicHasOneReference')))
      {
        $this->loadDataFromArgs($data, $omClass, $setter, $foreignObjectRef);

        // if there's anything left, send it through again
        if (count($data))
        {
          $this->loadDataFromArray($data);
        }
      }
      else
      {
        throw $e;
      }
    }
  }
  
  /**
   * Use args from an exception to load data, respectful of polymorphic keys.
   * 
   * @author  Kris Wallsmith
   * @param   array $data
   * @param   string $omClass
   * @param   string $setter
   * @param   string $foreignObjectRef
   */
  protected function loadDataFromArgs(&$data, $omClass, $setter, $foreignObjectRef)
  {
    foreach ($data as $class => $datas)
    {
      $class = trim($class);
      $peer_class = get_class(call_user_func(array(new $class, 'getPeer')));
      
      if ($class != $omClass)
      {
        // assume all records of this class have already been inserted
        unset($data[$class]);
        continue;
      }
      
      foreach ($datas as $key => $datum)
      {  
        // This data is already inserted, or is the PM key that threw the
        // exception. In either case, we should remove it so it doesn't go
        // through again.
        unset($data[$class][$key]);
        
        foreach ($datum as $col => $val)
        {
          if ('set'.sfInflector::camelize($col) == $setter && $val == $foreignObjectRef)
          {
            // The following code (down to the break 3 statement) was copied 
            // directly from sfPropelData, r6019... A minor modification was 
            // made to respect polymorphic keys... I wish there were a more 
            // elegant way...
            $tableMap = $this->maps[$class]->getDatabaseMap()->getTable(constant($peer_class.'::TABLE_NAME'));
            $column_names = call_user_func_array(array($peer_class, 'getFieldNames'), array(BasePeer::TYPE_FIELDNAME));
            $obj = new $class;
            foreach ($datum as $name => $value)
            {
              $isARealColumn = true;
              try
              {
                $column = $tableMap->getColumn($name);
              }
              catch (PropelException $e)
              {
                $isARealColumn = false;
              }

              // foreign key?
              if ($isARealColumn)
              {
                if ($column->isForeignKey() && !is_null($value))
                {
                  $relatedTable = $this->maps[$class]->getDatabaseMap()->getTable($column->getRelatedTableName());

                  if (!isset($this->object_references[$relatedTable->getPhpName().'_'.$value]))
                  {
                    throw new sfException(sprintf('The object "%s" from class "%s" is not defined in your data file.', $value, $relatedTable->getPhpName()));
                  }

                  $value = $this->object_references[$relatedTable->getPhpName().'_'.$value];
                }
              }

              if (false !== $pos = array_search($name, $column_names))
              {
                $obj->setByPosition($pos, $value);
              }
              else if (is_callable(array($obj, $method = 'set'.sfInflector::camelize($name))))
              {
                // this is where a polymorphic exception will be thrown; this
                // is where we do our dirty work...
                try
                {
                  $obj->$method($value);
                }
                catch (sfException $e)
                {
                  // test for behavior and polymorphic reference
                  if (is_callable(array($obj, 'setPolymorphicHasOneReference')) &&
                      ($foreignObject = $this->getObjectFromReference($value)))
                  {
                    $obj->setPolymorphicHasOneReference($name, $foreignObject);
                  }
                  else
                  {
                    throw $e;
                  }
                }
              }
              else
              {
                $error = 'Column "%s" does not exist for class "%s"';
                $error = sprintf($error, $name, $class);
                throw new sfException($error);
              }
            }
            $obj->save($this->con);

            // save the id for future reference
            if (method_exists($obj, 'getPrimaryKey'))
            {
              $this->object_references[$class.'_'.$key] = $obj->getPrimaryKey();
            }

            break 3;
          }
        }
      }
    }
  }
  
  /**
   * Turns the supplied reference into a Propel object.
   * 
   * @author  Kris Wallsmith
   * @param   string $foreignObjectRef
   * @return  BaseObject
   */
  protected function getObjectFromReference($foreignObjectRef)
  {
    $foreignObject = null;
    if (isset($this->object_references[$foreignObjectRef]))
    {
      $foreignPK = $this->object_references[$foreignObjectRef];
      $foreignClass = substr($foreignObjectRef, 0, strpos($foreignObjectRef, '_'));
      $foreignPeerClass = get_class(call_user_func(array(new $foreignClass, 'getPeer')));
      $foreignObject = call_user_func(array($foreignPeerClass, 'retrieveByPK'), $foreignPK);
    }
    
    return $foreignObject;
  }
  
}
