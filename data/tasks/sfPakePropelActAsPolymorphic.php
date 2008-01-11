<?php

pake_desc('load polymorphic data from fixtures directory');
pake_task('propel-load-pm-data', 'project_exists');

function run_propel_load_pm_data($task, $args)
{
  if (!count($args))
  {
    throw new Exception('You must provide the app.');
  }
  
  $app = $args[0];
  $env = empty($args[1]) ? 'dev' : $args[1];
  
  $cacheDir  = sfConfig::get('sf_root_dir').'/'.sfConfig::get('sf_cache_dir_name').'/'.$app.'/'.$env;
  $cacheFile = $cacheDir.'/base_run_propel_load_pm_data.php';
  
  $func = new ReflectionFunction('run_propel_load_data');
  
  $lines = file($func->getFileName());
  $lines = array_slice($lines, $func->getStartLine(), $func->getEndLine()-$func->getStartLine());
  array_unshift($lines, 'function base_run_propel_load_pm_data($task, $args)');
  
  $funcBody = join('', $lines);
  $funcBody = str_replace('sfPropelData', 'sfPropelActAsPolymorphicData', $funcBody);
  
  file_put_contents($cacheFile, "<?php\n\n".$funcBody);
  include $cacheFile;
  
  base_run_propel_load_pm_data($task, $args);
}
