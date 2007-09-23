<?php

/**
 * Configuration for the symfony Propel behavior plugins system.
 * 
 * @package     plugins
 * @subpackage  sfPropelActAsPolymorphicBehaviorPlugin
 * @author      Kris Wallsmith <kris [dot] wallsmith [at] gmail [dot] com>
 * @version     SVN: $Id$
 */

sfPropelBehavior::registerMethods('sfPropelActAsPolymorphic', array(
  // has one methods
  array('sfPropelActAsPolymorphicBehavior', 'getPolymorphicHasOneReference'),
  array('sfPropelActAsPolymorphicBehavior', 'setPolymorphicHasOneReference'),
  array('sfPropelActAsPolymorphicBehavior', 'clearPolymorphicHasOneReference'),
  // has many methods
  array('sfPropelActAsPolymorphicBehavior', 'getPolymorphicHasManyReferences'),
  array('sfPropelActAsPolymorphicBehavior', 'addPolymorphicHasManyReference'),
  array('sfPropelActAsPolymorphicBehavior', 'clearPolymorphicHasManyReferences'),
  array('sfPropelActAsPolymorphicBehavior', 'deletePolymorphicHasManyReferences'),
  array('sfPropelActAsPolymorphicBehavior', 'countPolymorphicHasManyReferences'),
));
