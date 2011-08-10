<?php
if (!isset($_SERVER['argv'][1])) {
  print "Usage: php generate.php [path to drupal]\n"; exit;  
}
$api = rtrim($_SERVER['argv'][1], '/');
$files = '/\.(inc|module|php)$/';
$keywords = array(
  'HOOK',
  'FORM_ID',
);

$hooks = array();

// recurse the given directory for source code files.
function recurse($dir, $hook = FALSE) {
  global $files;
  $handle = opendir($dir);
  while ($file = readdir($handle)) {
    $path = $dir .'/'. $file;
    if (is_dir($path) && $file[0] != '.') {
      recurse($path, $hook);
    }
    if (preg_match($files, $file)) {
      // We only ever want to do api files once.
      if ($hook && preg_match('/\.(api\.php)$/', $file)) {
        parse(file_get_contents($path));
      }
      elseif (!$hook) {
        parse(file_get_contents($path));
      }
    }
  }
  closedir($handle);
}

// parse the given data for functions
function parse($data) {
  global $hooks;
  preg_match_all('/(?:void|bool|boolean|float|int|resource|string|mixed|array|object|function) +([A-Za-z0-9_]+)(\([^{\n]+)/', $data, $matches, PREG_SET_ORDER);

  foreach ($matches as $func) {
    if (!preg_match('`^__`', $func[1]) && !preg_match('`^_`', $func[1]) && !preg_match('`theme_`', $func[1])) {
      $process = TRUE;
      $hook_func = preg_match('`^hook_`', $func[1]);
      if (!$hook_func) {
        switch ($func[1]) {
          case 'theme':
            if (!strpos($func[2], '$hook')) {
              $process = FALSE;
              break;
            }
          default:
            foreach ($hooks as $hook) {
              if (strpos(preg_replace('`^([A-Za-z]*)_`', '', $func[1]), $hook)) {
                $process = FALSE;
              }
            }
        }
      }
      if ($process) {
        print $func[1] . $func[2] ."\n";
        $snippet = snippet($func, $hook_func);
        $f = fopen('./'. $func[1] .'.snippet', 'w+');
        fwrite($f, $snippet . "\n");
        fclose($f);
      }
      if ($hook_func) {
        $hook = str_replace('hook_', '', $func[1]);
        $hooks[$hook] = $hook;
      }
    }
  }
}

// generate a snippet for the given function
function snippet($func, $hook_func) {
  global $keywords;
  $tabstop = 1;

  // Change hook snippets
  $func_name = $func[1];
  if ($hook_func) {
    $func[1] = '`Filename()`'. substr($func[1], 4);
  }
  else {
    $arguments = explode(', ', $func[2]);
    $last = count($arguments)-1;
    $args = array();
    foreach ($arguments as $key => $argument) {
      $argument = trim($argument);
      switch ($key) {
        case 0:
          $argument = substr($argument, 1);
          if (!$last) { break; }
        case $last:
          $argument = substr($argument, 0, -1);
          break;
      }
      $args[] .= '${' . $tabstop . ': /* ' . $argument . ' */ }';
      $tabstop++;
    }
    $func[2] = '(' . implode(', ', $args) . ')';
  }

  if ($hook_func) {
    foreach ($keywords as $keyword) {
     if (strpos($func_name, $keyword)) {
        $func_name = str_replace($keyword, '${' . $tabstop . ': /* ' . $keyword . ' */}', $func_name);
        $tabstop++;
        $func[1] = str_replace($keyword, '${' . $tabstop . ': /* ' . $keyword . ' */}', $func[1]);
        $tabstop++;
      }
    }
    $template = <<<DOC
/**
 * Implementation of $func_name().
 */
function $func[1]$func[2] {
  \${{$tabstop}:/* Your code here */}
}
DOC;
  }
  else {
    $template = <<<DOC
$func[1]$func[2]
DOC;
  }

  return $template;
}

// Create bundle
if (!is_dir('snippets')) {
  mkdir('snippets');
}
chdir('snippets');

// Create snippets
if (!is_dir('drupal')) {
  mkdir('drupal');
}
chdir('drupal');

// For Drupal core
recurse($api, TRUE); // Do API files first so we don't duplicate hooks().
recurse($api);

chdir('../..');
?>
