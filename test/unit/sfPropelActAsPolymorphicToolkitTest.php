<?php

/**
 * Unit tests for sfPropelActAsPolymorphicToolkit.
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

$t = new lime_test(4, new lime_output_color);

// ------------------------------------------------------------------------ //
// TEST VARIABLES
// ------------------------------------------------------------------------ //

// test classes
$localClass = 'AbstractEventLog';
$localPeerClass = 'AbstractEventLogPeer';
$localClassExtended = 'FavoriteAddedLog';

// column names
$foreignModelColName = AbstractEventLogPeer::SUBJECT_TYPE;
$foreignModelPhpName = 'SubjectType';

// ------------------------------------------------------------------------ //
// TEST: getPeerClassFromColName()
// ------------------------------------------------------------------------ //

$t->diag('getPeerClassFromColName()');
$t->is(
  sfPropelActAsPolymorphicToolkit::getPeerClassFromColName($foreignModelColName), 
  $localPeerClass,
  'getPeerClassFromColName() extracts the peer class from a column name.');

// ------------------------------------------------------------------------ //
// TEST: getDefaultOmClass()
// ------------------------------------------------------------------------ //

$t->diag('getDefaultOmClass()');
$t->is(
  sfPropelActAsPolymorphicToolkit::getDefaultOmClass(new $localClassExtended),
  $localClass,
  'getDefaultOmClass() returns the default OM class.');

// ------------------------------------------------------------------------ //
// TEST: forgeMethodName()
// ------------------------------------------------------------------------ //

$t->diag('forgeMethodName()');
$t->is(
  sfPropelActAsPolymorphicToolkit::forgeMethodName(new $localClass, $foreignModelColName, 'get'),
  'get'.$foreignModelPhpName,
  'forgetMethodName() creates a getXXX method.');
$t->is(
  sfPropelActAsPolymorphicToolkit::forgeMethodName(new $localClass, $foreignModelColName, 'set'),
  'set'.$foreignModelPhpName,
  'forgetMethodName() creates a setXXX method.');






