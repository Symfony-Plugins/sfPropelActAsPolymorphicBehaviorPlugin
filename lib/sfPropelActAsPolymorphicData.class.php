<?php

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
    catch (sfPropelActAsPolymorphicException $e)
    {
      $this->loadDataFromException($data, $e);
      
      // if there's anything left, send it through again
      if (count($data))
      {
        $this->loadDataFromArray($data);
      }
    }
  }
  
  /**
   * Use the exception to create an object, respectful of polymorphic keys.
   * 
   * @author  Kris Wallsmith
   * @param   array $data
   * @param   sfPropelActAsPolymorphicException $e
   */
  protected function loadDataFromException(&$data, $e)
  {
    $trace = $e->getTrace();
    $args  = $trace[0]['args'];
    
    $object = $args[0];
    $keyName = $args[1];
    $foreignObjectRef = $args[2];
    
    foreach ($data as $class => $datas)
    {
      $class = trim($class);
      $peer_class = get_class(call_user_func(array(new $class, 'getPeer')));
      
      if ($class != get_class($args[0]))
      {
        // assume all records of this class have already been inserted
        unset($data[$class]);
        continue;
      }
      
      foreach ($datas as $key => $datum)
      {  
        // this data is already inserted, or is the pm key that threw the
        // exception. in either case, we should remove it so it doesn't go
        // through again.
        unset($data[$class][$key]);
        
        if ($datum[$keyName] == $foreignObjectRef)
        {
          // the following code (down to the break 2 statement) was copied 
          // directly from sfPropelData, r6019... a minor modification was 
          // made to respect polymorphic keys... i wish there were a more 
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
              catch (sfPropelActAsPolymorphicException $e)
              {
                if (isset($this->object_references[$value]) && ($foreignObject = $this->getObjectFromReference($value)))
                {
                  $object->$method($foreignObject);
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
          
          break 2;
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
    $foreignPK = $this->object_references[$foreignObjectRef];
    $foreignClass = substr($foreignObjectRef, 0, strpos($foreignObjectRef, '_'));
    $foreignPeerClass = get_class(call_user_func(array(new $foreignClass, 'getPeer')));
    $foreignObject = call_user_func(array($foreignPeerClass, 'retrieveByPK'), $foreignPK);

    return $foreignObject;
  }
  
}
