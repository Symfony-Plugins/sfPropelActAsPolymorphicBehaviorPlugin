<?php

/**
 * Unit tests for sfPropelActAsPolymorphicConfig.
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

$t = new lime_test(13, new lime_output_color);

// ------------------------------------------------------------------------ //
// TEST VARIABLES
// ------------------------------------------------------------------------ //

// test classes
$localClass   = 'AbstractEventLog';
$foreignClass = 'sfGuardUser';

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
sfPropelBehavior::add($foreignClass, array('sfPropelActAsPolymorphic' => array(
  'has_many' => array($hasManyKeyName => array('foreign_model' => $foreignModelColName,
                                               'foreign_pk'    => $foreignPKColName)))));

// ------------------------------------------------------------------------ //
// TEST: Behavior config
// ------------------------------------------------------------------------ //

$expectedHasOneConfig = array($hasOneKeyName => array(
  'foreign_model' => $foreignModelColName,
  'foreign_pk'    => $foreignPKColName));
$expectedHasManyConfig = array($hasManyKeyName => array(
  'foreign_model' => $foreignModelColName,
  'foreign_pk'    => $foreignPKColName));

$hasOneConfigKey = sprintf(sfPropelActAsPolymorphicConfig::CONFIG_KEY_FMT, $localClass, 'has_one');
$hasManyConfigKey = sprintf(sfPropelActAsPolymorphicConfig::CONFIG_KEY_FMT, $foreignClass, 'has_many');

$t->diag('Behavior configuration');
$t->is_deeply(sfConfig::get($hasOneConfigKey), $expectedHasOneConfig, 'has_one config is stored.');
$t->is_deeply(sfConfig::get($hasManyConfigKey), $expectedHasManyConfig, 'has_many config is stored.');

// ------------------------------------------------------------------------ //
// TEST: getAll()
// ------------------------------------------------------------------------ //

$local = new $localClass;
$local->save();

$foreign = new $foreignClass;
$foreign->save();

$expectedHasOneGetAll = array($hasOneKeyName => array(
  'foreign_model' => $foreignModelColName,
  'foreign_pk'    => $foreignPKColName));
$expectedHasManyGetAll = array($hasManyKeyName => array(
  'foreign_model' => $foreignModelColName,
  'foreign_pk'    => $foreignPKColName));

$t->diag('getAll() - no key specified');
$t->is_deeply(
  sfPropelActAsPolymorphicConfig::getAll($local, null, 'has_one'), 
  $expectedHasOneGetAll, 
  'getAll() returns all has_one keys.');
$t->is_deeply(
  sfPropelActAsPolymorphicConfig::getAll($foreign, null, 'has_many'), 
  $expectedHasManyGetAll, 
  'getAll() returns all has_many keys.');

$t->diag('getAll() - key specified');
$t->is_deeply(
  sfPropelActAsPolymorphicConfig::getAll($local, $hasOneKeyName, 'has_one'), 
  $expectedHasOneGetAll[$hasOneKeyName], 
  'getAll() returns a specific has_one key.');
$t->is_deeply(
  sfPropelActAsPolymorphicConfig::getAll($foreign, $hasManyKeyName, 'has_many'), 
  $expectedHasManyGetAll[$hasManyKeyName], 
  'getAll() returns a specific has_many key.');

// ------------------------------------------------------------------------ //
// TEST: get()
// ------------------------------------------------------------------------ //

$t->diag('get()');
$t->is(
  sfPropelActAsPolymorphicConfig::get($local, $hasOneKeyName, 'foreign_model', 'has_one'),
  $foreignModelColName,
  'get() returns a has_one parameter.');
$t->is(
  sfPropelActAsPolymorphicConfig::get($foreign, $hasManyKeyName, 'foreign_pk', 'has_many'),
  $foreignPKColName,
  'get() returns a has_many parameter.');
$t->is(
  sfPropelActAsPolymorphicConfig::get($foreign, $hasManyKeyName, 'peer_method', 'has_many', 'doSelect'),
  'doSelect',
  'get() returns a default value.');

// ------------------------------------------------------------------------ //
// TEST: getHasOne()
// ------------------------------------------------------------------------ //

$t->diag('getHasOne()');
$t->is(
  sfPropelActAsPolymorphicConfig::getHasOne($local, $hasOneKeyName, 'foreign_model'),
  $foreignModelColName,
  'getHasOne() returns a parameter.');
$t->is(
  sfPropelActAsPolymorphicConfig::getHasOne($local, $hasOneKeyName, 'peer_method', 'doSelect'),
  'doSelect',
  'getHasOne() returns a default value.');

// ------------------------------------------------------------------------ //
// TEST: getHasMany()
// ------------------------------------------------------------------------ //

$t->diag('getHasMany()');
$t->is(
  sfPropelActAsPolymorphicConfig::getHasMany($foreign, $hasManyKeyName, 'foreign_model'),
  $foreignModelColName,
  'getHasMany() returns a parameter.');
$t->is(
  sfPropelActAsPolymorphicConfig::getHasMany($foreign, $hasManyKeyName, 'peer_method', 'doSelect'),
  'doSelect',
  'getHasMany() returns a default value.');
