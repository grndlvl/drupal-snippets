<?php
/*
  This is a script that will create snipMate snippets for Drupal to be used with Vim.

  This program is free software: you can redistribute it and/or modify
  it under the terms of the GNU General Public License as published by
  the Free Software Foundation, either version 3 of the License, or
  (at your option) any later version.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

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
  'MODULE',
  'DELTA',
  'N',
);

// Keeps a record of the functions/hooks that have been processed so that they are
// not duplicated.
$functions = $hooks = array();

$code_message = 'Your code here';

/**
 * @TODO
 *
 * Had a long informative message here but wasn't committed
 * and acctidenally deleted. I will put back soon.
 */
if (is_file('./extra/extra_snippets.php')) {
  require_once './extra/extra_snippets.php';
}

// Create snippets.
if (!is_dir('snippets')) {
  mkdir('snippets');
}
chdir('snippets');

// Create snippets/drupal.
/*
if (!is_dir('drupal')) {
  mkdir('drupal');
}
chdir('drupal');
 */

// Do API files first so that there are not duplicate hooks().
recurse($api, TRUE);
// Do EVERYTHING else.
recurse($api);

// Add custom snippets.
custom_snippets();

/**
 * Recurse the given directory for source code files.
 *
 * @param string $dir
 *  The directory to recursively process.
 * @param bool $hook
 *  If set to TRUE ONLY process hook_functions.
 */
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
        parse(file_get_contents($path), $file);
      }
      elseif (!$hook) {
        parse(file_get_contents($path), $file);
      }
    }
  }
  closedir($handle);
}

/**
* Parse the given data for functions.
*
* @param $data
* The contents of the file to be processed.
*/
function parse($data, $file) {
  global $functions;
  // Match functions.
  preg_match_all('/(?:void|bool|boolean|float|int|resource|string|mixed|array|object|function) +([A-Za-z0-9_]+)(\([^{\n]+) \{/', $data, $matches, PREG_SET_ORDER);

  if (!empty($matches)) {
    write_snippet('# ' . strtoupper($file));
  }
  foreach ($matches as $func) {
    // Don't include constructs, private functions, or theme functions.
    if (!preg_match('`^__`', $func[1]) && !preg_match('`^_`', $func[1]) && !preg_match('`theme_`', $func[1])) {
      if (!array_key_exists($func[1], $functions)) {
        list($process, $hook_func) = check_function($func);

        // Only process functions that have are allowed.
        if ($process) {
          // Get and write the snippet.
          if ($snippet = snippet($func, $hook_func)) {
            print $func[1] . $func[2] ."\n";
            write_snippet('snippet ' . $func[1] . PHP_EOL . $snippet);
          }
          $functions[$func[1]] = $func[1];
        }
      }
    }
  }
}

/**
 * Writes to the snippet file.
 *
 * @param string $snippet
 *
 */
function write_snippet($snippet) {
  $f = fopen('./drupal.snippets', 'a+');
  fwrite($f, $snippet . "\n");
  fclose($f);
}

/**
 * Checks the function to see if it should be process and if it's a hook.
 *
 * @param array $func
 *  The contents of the function.
 *
 * @return array
 */
function check_function($func) {
  global $hooks;

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
          if (preg_replace('`^([A-Za-z]*)_`', '', $func[1]) == $hook) {
            $process = FALSE;
          }
        }
    }
  }

  // Record hook functions so that implementations are not processed.
  if ($hook_func) {
    $hook = str_replace('hook_', '', $func[1]);
    $hooks[$hook] = $hook;
  }

  return array($process, $hook_func);
}

/**
 * Generate a snippet for the given function.
 *
 * @param $func
 *  The contents of the function.
 * @param $hook_func
 *  Determines if the function being processed is a hook or not.
 *
 * @return string
 */
function snippet($func, $hook_func) {
  if ($hook_func) {
    return process_hook_function($func);
  }
  return process_function($func);
}

/**
 * Processes a hook function.
 *
 * @param array $func
 *  The contents of the function.
 *
 * @return string
 */
function process_hook_function($func) {
  global $keywords;
  $tabstop = 1;

  // Set the name of the function.
  $snippet_name = $func_name = $func[1];

  $func[1] = '`Filename()`'. substr($func[1], 4);

  // Replace keywords with tabstops.
  foreach ($keywords as $keyword) {
    if (strpos($func_name, $keyword)) {
      $func_name = str_replace($keyword, '${' . $tabstop . ':' . $keyword . '}', $func_name);
      $tabstop++;
      $func[1] = str_replace($keyword, '${' . $tabstop . ':' . $keyword . '}', $func[1]);
      $tabstop++;
    }
  }

  // Get function expansion.
  $expansion = expand_hook_function($snippet_name, $tabstop);

  return <<<DOC
\t/**
\t* Implementation of $func_name().
\t*/
\tfunction $func[1]$func[2] {
\t$expansion
\t}
DOC;
}

/**
 * Processes a non-hook function.
 *
 * @aram array $func
 *  The contents of the function.
 *
 * @return string
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
    if ($argument != '') {
      // Replace arguments with tabstops.
      $args[] .= '${' . $tabstop . ':' . $argument . '}';
      $tabstop++;
    }
    else {
      return;
    }
  }

  $func[2] = '(' . implode(', ', $args) . ')';

  return "\t$func[1]$func[2]\${{$tabstop}}";
}

/**
 * Expand hook functions.
 *
 * @param string $func_name
 *  The name of the function.
 * @param int $tabstop
 *  The current tabstop position.
 *
 * @return string
 */
function expand_hook_function($func_name, $tabstop) {
  global $code_message;
  $exp = array();
  switch ($func_name) {
    case 'hook_help':
      $exp[] = 'switch ($path) {';
      $exp[] = "  case '\${" . $tabstop++ . ":path}':";
      $exp[] = "    return '<p>' . t('\${" . $tabstop++ . ":/* Text */}') . '</p>'";
      $exp[] = '    break;';
      $exp[] = '}';
      break;
    case 'hook_menu':
      $first_tabstop = $tabstop++;
      $exp[] = '$${' . $first_tabstop . ':items} = array();';
      $exp[] = '';
      $exp[] = '// Put your menu items here.';
      $exp[] = "$${$first_tabstop}['\${" . $tabstop++ . ":path}'] = array(";
      $exp[] = '  ${' . $tabstop++ . ":/*  $code_message */}";
      $exp[] = ');';
      $exp[] = '';
      $exp[] = "return $${$first_tabstop};";
      break;
    case 'hook_permission':
      $exp[] = 'return array(';
      $exp[] = "  'title' => t('\${" . $tabstop++ . ":/* Title */}')";
      $exp[] = "  'description' => t('\${" . $tabstop++ . ":/* Text */}')";
      $exp[] = ');';
      break;
    case 'hook_theme':
      $exp[] = '${' . $tabstop++ . ':theme_function} = array(';
      $exp[] = "  'arguments' => array(\${" . $tabstop++ . ':/* Theme function arguments */}),';
      $exp[] = '  ${' . $tabstop++ . ':/* See http://api.drupal.org/api/drupal/modules--system--system.api.php/function/hook_theme/7 for options */}';
      $exp[] = '  ),';
      $exp[] = ');';
      break;
    case 'hook_update_N':
      $exp[] = '$ret = array();';
      $exp[] = '${' . $tabstop++ . ":/* $code_message */}";
      $exp[] = 'return $ret';
      break;
    case 'hook_user_operations':
      $exp[] = '$operations = array(';
      $exp[] = "'\${" . $tabstop++ . ":operation}' => array(";
      $exp[] = "  'label' => t('\${" . $tabstop++ . ": /* Label */}')";
      $exp[] = "  'callback' => t('\${" . $tabstop++ . ":callback}')";
      $exp[] = 'return $operations;';
    default:
      // @TODO: Create a way for people to tie into this.
      $exp[] = '${' . $tabstop++ . ":/* $code_message */}";
      break;
  }
  if (!empty($exp)) {
    ksort($exp);

    // Add spaces to all code.
    foreach ($exp as $key => $value) {
      if ($value != '') {
        $exp[$key] = '  ' . $exp[$key];
      }
    }
    return implode(PHP_EOL . "\t", $exp);
  }

  return FALSE;
}

/**
 *
 */
function form_elements() {

}

/**
 *
 */
function custom_snippets() {
  write_snippet('# Custom snippets');
  $snips = array();
  $snips['**'][] = '/**';
  $snips['**'][] = ' * ${1:Your documentation}';
  $snips['**'][] = ' */';

  // @TODO Get extra snipps.
  foreach ($snips as $key => $value) {
    print $key . PHP_EOL;
    write_snippet("snippet $key" . PHP_EOL . "\t" . implode(PHP_EOL . "\t", $value));
  }
}
