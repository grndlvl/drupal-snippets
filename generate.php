<?php
if (!isset($_SERVER['argv'][1])) {
  print "Usage: php generate.php [path to drupal]\n"; exit;
}
$api = rtrim($_SERVER['argv'][1], '/');

// Which files are we intersted in.
$files = '/\.(inc|module|php)$/';

// Special cases that we will want to place our tabstops at first.
$keywords = array(
  'HOOK',
  'FORM_ID',
);

// Keeps a record of the hooks that have been processed so that they are
// not duplicated.
$hooks = array();

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

// Do API files first that there are not duplicate hooks().
recurse($api, TRUE);
// Do EVERYTHING else.
recurse($api);

// Recurse the given directory for source code files.
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

/**
 * Parse the given data for functions.
 *
 * @param $data
 *  The contents of the file to be processed.
 */
function parse($data) {
  global $hooks;
  // Match functions.
  preg_match_all('/(?:void|bool|boolean|float|int|resource|string|mixed|array|object|function) +([A-Za-z0-9_]+)(\([^{\n]+)/', $data, $matches, PREG_SET_ORDER);

  foreach ($matches as $func) {
    // Don't include constructs, private functions, or theme functions.
    if (!preg_match('`^__`', $func[1]) && !preg_match('`^_`', $func[1]) && !preg_match('`theme_`', $func[1])) {
      // All functions should be processed unless otherwise noted.
      $process = TRUE;

      // Check to see if the current function is a hook.
      $hook_func = preg_match('`^hook_`', $func[1]);
      if (!$hook_func) {
        switch ($func[1]) {
          // Do not process theme functions.
          case 'theme':
            if (!strpos($func[2], '$hook')) {
              $process = FALSE;
              break;
            }
          default:
            // Check if the current function is a hook implementation,
            // if it is then do not process it.
            foreach ($hooks as $hook) {
              if (strpos(preg_replace('`^([A-Za-z]*)_`', '', $func[1]), $hook)) {
                $process = FALSE;
              }
            }
        }
      }

      // Only process functions that have are allowed.
      if ($process) {
        print $func[1] . $func[2] ."\n";
        // Get and write the snippet.
        $snippet = snippet($func, $hook_func);
        $f = fopen('./'. $func[1] .'.snippet', 'w+');
        fwrite($f, $snippet . "\n");
        fclose($f);
      }

      // Record hook functions so that implementations are not processed.
      if ($hook_func) {
        $hook = str_replace('hook_', '', $func[1]);
        $hooks[$hook] = $hook;
      }
    }
  }
}

/**
 * Generate a snippet for the given function.
 *
 * @param $func
 *  The contents of the function.
 * @param $hook_func
 *  Determines if the function being processed is a hook or not.
 */
function snippet($func, $hook_func) {
  if ($hook_func) {
    return process_hook_function($func);
  }
  return process_function($func);
}

/**
 *
 */
function process_hook_function($func) {
  global $keywords;
  $tabstop = 1;

  // Set the name of the function.
  $func_name = $func[1];

  $func[1] = '`Filename()`'. substr($func[1], 4);

  // Replace keywords with tabstops.
  foreach ($keywords as $keyword) {
   if (strpos($func_name, $keyword)) {
      $func_name = str_replace($keyword, '${' . $tabstop . ': /* ' . $keyword . ' */}', $func_name);
      $tabstop++;
      $func[1] = str_replace($keyword, '${' . $tabstop . ': /* ' . $keyword . ' */}', $func[1]);
      $tabstop++;
    }
  }

  return <<<DOC
/**
 * Implementation of $func_name().
 */
function $func[1]$func[2] {
  \${{$tabstop}:/* Your code here */}
}
DOC;
}

/**
 *
 */
function process_function($func) {
  $tabstop = 1;
  // Set the name of the function.
  $func_name = $func[1];

  // Create an array of arguments.
  $arguments = explode(', ', $func[2]);

  // Get the last element key.
  $last = count($arguments)-1;
  $args = array();
  foreach ($arguments as $key => $argument) {
    $argument = trim($argument);

    // Strips out the parenthese from the beginning and end of the arguments.
    switch ($key) {
      case 0:
        $argument = substr($argument, 1);
        if ($key != $last) { break; }
      case $last:
        $argument = substr($argument, 0, -1);
        break;
    }

    // Replace arguments with tabstops.
    $args[] .= '${' . $tabstop . ': /* ' . $argument . ' */ }';
    $tabstop++;
  }

  $func[2] = '(' . implode(', ', $args) . ')';

  return <<<DOC
$func[1]$func[2]
DOC;
}
