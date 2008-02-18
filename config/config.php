<?php

/**
 * Configuration for the symfony Propel behavior plugins system.
 * 
 * @package     plugins
 * @subpackage  sfPropelActAsPolymorphicBehaviorPlugin
 * @author      Kris Wallsmith <kris [dot] wallsmith [at] gmail [dot] com>
 * @version     SVN: $Id$
 */

sfPropelBehavior::registerHooks('sfPropelActAsPolymorphic', array(
  ':save:pre'  => array('sfPropelActAsPolymorphicBehavior', 'preSave'),
  ':save:post' => array('sfPropelActAsPolymorphicBehavior', 'postSave'),
));

sfPropelBehavior::registerMethods('sfPropelActAsPolymorphic', array(
  array('sfPropelActAsPolymorphicBehavior', 'getPolymorphicHasOneReference'),
  array('sfPropelActAsPolymorphicBehavior', 'setPolymorphicHasOneReference'),
  array('sfPropelActAsPolymorphicBehavior', 'getPolymorphicHasManyReferences'),
  array('sfPropelActAsPolymorphicBehavior', 'addPolymorphicHasManyReference'),
  array('sfPropelActAsPolymorphicBehavior', 'countPolymorphicHasManyReferences'),
  array('sfPropelActAsPolymorphicBehavior', 'getParameterHolder'),
));
