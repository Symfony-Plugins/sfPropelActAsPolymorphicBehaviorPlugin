<?php

/**
 * Unit tests for sfPropelActAsPolymorphicBehavior.
 * 
 * In order to run these tests in your context, you need to copy this file 
 * into your symfony test directory and set the configuration variables
 * appropriately.
 * 
 * <code>
 *  cp plugins/sfPropelActAsPolymorphicBehaviorPlugin/test/unit/* test/unit/
 * </code>
 * 
 * If running these tests in an application that already includes calls to 
 * add this behavior, those calls to sfPropelBehavior::add() will need to be
 * surrounded by the following:
 * 
 * <code>
 *  if(sfConfig::get('sf_environment') != 'test')
 *  {
 *    // sfPropelBehavior::add( ...
 *  }
 * </code>
 * 
 * @package     plugins
 * @subpackage  sfPropelActAsPolymorphicBehaviorPlugin
 * @author      Kris Wallsmith <kris [dot] wallsmith [at] gmail [dot] com>
 * @version     SVN: $Id$
 */

// ------------------------------------------------------------------------ //
// SYMFONY INITIALIZATION
// ------------------------------------------------------------------------ //

$app = 'frontend';

include realpath(dirname(__FILE__).'/../..').'/test/bootstrap/functional.php';
include sfConfig::get('sf_symfony_lib_dir').'/vendor/lime/lime.php';

$context = sfContext::getInstance();
$con = Propel::getConnection();

$t = new lime_test(12, new lime_output_color);

// ------------------------------------------------------------------------ //
// TEST VARIABLES
// ------------------------------------------------------------------------ //

// test classes
$localClass    = 'AbstractEventLog';
$foreignClass1 = 'sfGuardUser';
$foreignClass2 = 'Business';

// column names
$foreignModelColName = AbstractEventLogPeer::SUBJECT_TYPE;
$foreignModelPhpName = 'SubjectType';
$foreignPKColName    = AbstractEventLogPeer::SUBJECT_ID;
$foreignPKPhpName    = 'SubjectId';

// key names
$hasOneKeyName  = 'sender';
$hasManyKeyName = 'messages';

// ------------------------------------------------------------------------ //
// BEHAVIOR CONFIG
// ------------------------------------------------------------------------ //

sfPropelBehavior::add($localClass, array('sfPropelActAsPolymorphic' => array(
  'has_one'  => array($hasOneKeyName  => array('foreign_model' => $foreignModelColName,
                                               'foreign_pk'    => $foreignPKColName)))));
sfPropelBehavior::add($foreignClass1, array('sfPropelActAsPolymorphic' => array(
  'has_many' => array($hasManyKeyName => array('foreign_model' => $foreignModelColName,
                                               'foreign_pk'    => $foreignPKColName)))));
sfPropelBehavior::add($foreignClass2, array('sfPropelActAsPolymorphic' => array(
  'has_many' => array($hasManyKeyName => array('foreign_model' => $foreignModelColName,
                                               'foreign_pk'    => $foreignPKColName)))));

// ------------------------------------------------------------------------ //
// TEST: New methods
// ------------------------------------------------------------------------ //

// has one methods
$t->diag('New has_one methods');
$t->ok(is_callable($localClass, 'getPolymorphicHasOneReference'), 'Behavior adds a new getPolymorphicHasOneReference() method to the class.');
$t->ok(is_callable($localClass, 'setPolymorphicHasOneReference'), 'Behavior adds a new setPolymorphicHasOneReference() method to the class.');
$t->ok(is_callable($localClass, 'clearPolymorphicHasOneReference'), 'Behavior adds a new clearPolymorphicHasOneReference() method to the class.');

// has many methods
$t->diag('New has_many methods');
$t->ok(is_callable($foreignClass1, 'getPolymorphicHasManyReferences'), 'Behavior adds a new getPolymorphicHasManyReferences() method to the class.');
$t->ok(is_callable($foreignClass1, 'addPolymorphicHasManyReferences'), 'Behavior adds a new addPolymorphicHasManyReferences() method to the class.');
$t->ok(is_callable($foreignClass2, 'clearPolymorphicHasManyReferences'), 'Behavior adds a new clearPolymorphicHasManyReferences() method to the class.');
$t->ok(is_callable($foreignClass2, 'deletePolymorphicHasManyReferences'), 'Behavior adds a new deletePolymorphicHasManyReferences() method to the class.');

// ------------------------------------------------------------------------ //
// TEST: setPolymorphicHasOneReference
// ------------------------------------------------------------------------ //

$foreign = new $foreignClass1;
$foreign->save();

$local = new $localClass;
$local->setPolymorphicHasOneReference($hasOneKeyName, $foreign);
$local->save();

$t->diag('setPolymorphicHasOneReference()');
$t->is(call_user_func(array($local, 'get'.$foreignModelPhpName)), $foreignClass1, 'setPolymorphicHasOneReference() sets the model column.');
$t->is(call_user_func(array($local, 'get'.$foreignPKPhpName)), $foreign->getPrimaryKey(), 'setPolymorphicHasOneReference() sets the PK column.');

// ------------------------------------------------------------------------ //
// TEST: getPolymorphicHasOneReference
// ------------------------------------------------------------------------ //

$t->diag('getPolymorphicHasOneReference()');
$t->ok($foreign->equals($local->getPolymorphicHasOneReference($hasOneKeyName)), 'getPolymorphicHasOneReference() loads the foreign object.');

// ------------------------------------------------------------------------ //
// TEST: clearPolymorphicHasOneReference
// ------------------------------------------------------------------------ //

$local->clearPolymorphicHasOneReference($hasOneKeyName);

$t->diag('clearPolymorphicHasOneReference()');
$t->is(call_user_func(array($local, 'get'.$foreignModelPhpName)), null, 'clearPolymorphicHasOneReference() sets the model column to NULL.');
$t->is(call_user_func(array($local, 'get'.$foreignPKPhpName)), null, 'clearPolymorphicHasOneReference() sets the PK column to NULL.');

// ------------------------------------------------------------------------ //
// TEST: getPolymorphicHasManyReferences
// ------------------------------------------------------------------------ //



// ------------------------------------------------------------------------ //
// TEST: addPolymorphicHasManyReferences
// ------------------------------------------------------------------------ //



// ------------------------------------------------------------------------ //
// TEST: clearPolymorphicHasManyReferences
// ------------------------------------------------------------------------ //



// ------------------------------------------------------------------------ //
// TEST: deletePolymorphicHasManyReferences
// ------------------------------------------------------------------------ //







