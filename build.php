<?php
/**
 * Multiplatform install script for Drupal in a Box.
 *
 * PHP Version 5
 *
 * @package dib_build
 * @author Thomas Foulds <tfoulds@sapient.com>
 * @copyright 2015
 *
 * @version 0.0.2
 */

main($argv);

/**
 * Run the build with the provided commandline parameters.
 *
 * @param $argv
 *      Array of commandline parameters passed to the script.
 */
function main($argv) {
  if (count($argv) !== 3) {
    print 'Usage:' . PHP_EOL;
    print 'php build.php [[optional branch key].][.run file name] [section in .run file]' . PHP_EOL;
    exit(1);
  }
	
  $branch = '';
  if ( strpos($argv[1], '.') === FALSE ) {
    $run_file_name = $argv[1] . '.run';
  }
  else {
		list($branch, $run_file_name) = explode('.', $argv[1]);
    $run_file_name .= '.run';
	}
  $command_set = trim($argv[2]);

  try {
    $variables = parse_variable_file(__DIR__ . '/build.ini', $branch);
    $commands = parse_run_file(__DIR__ . '/' . $run_file_name);
    if (!empty($commands[$command_set])) {
      $commands = $commands[$command_set];
    }
    else {
      throw new InvalidArgumentException('Could not access command set: '
                                         . '[' . $command_set . ']');
    }
  }
  catch (Exception $e) {
    print $e->getMessage() . PHP_EOL;
    exit(1);
  }

	if (isset($variables['all'])) {
		foreach ($variables['all'] as $key => $vars) {
			run_commands($commands, $vars);
		}
  }
  else if (isset($variables['master'])) {
		run_commands($commands, $variables['master']);
	}
}

/**
 * Run an array of commands, substituting variables where appropriate.
 *
 * @param $commands
 *      Array of CLI commands to be executed which may contain variables to
 *      substitute.
 * @param $variables
 *      An key/value array of variables which may be substituted into commands
 *      before execution.
 */
function run_commands($commands, $variables){
  foreach ($commands as $command) {
    try {
      $command = parse_command($command, $variables);
      print '>> ' . $command . PHP_EOL;
      $output = execute_command($command);
      foreach ($output as $line) {
        print $line . PHP_EOL;
      }
    }
    catch (Exception $e) {
      print $e->getMessage() . PHP_EOL;
      exit(1);
    }
  }	
}

/**
 * Parses variable INI file and returns configured variable set.
 *
 * The correct variable set is selected by accessing the 'build'
 * property in the [master] section.
 *
 * @param string $file_path
 *      Absolute or relative file path for the .ini file to load.
 * @param string $branch
 *      specific variable section to be used for current command
 * @return array 
 *		'all' => array ( [section_key] => [variable array] )
 *	OR
 *		'master' => [variable array]
 *      [variable array] is the associative array keying variable names to
 *      variable values.
 * @throws InvalidArgumentException
 *      The provided filename cannot be parsed as an INI file.
 *      The build variable set does not exist.
 */
function parse_variable_file($file_path, $branch='') {
  $variables = parse_ini_file($file_path, true);
  if ($variables) {
    if (!empty($branch) && $branch != 'master' && isset($variables[$branch])) {
      $build_set = $branch;
    }
    else {
      $build_set = $variables['master']['build'];
    }
		if ($build_set == 'all') {
			unset($variables['master']);
			return array('all' => $variables);
    }
    else {
			if (!empty($variables[$build_set])) {
				return array('master' => $variables[$build_set]);
      }
      else {
				throw new InvalidArgumentException('Could not access variable set: '
                                           . '[' . $build_set . ']');
			}
    }
  }
  else {
    throw new InvalidArgumentException('Could not open file: '. $file_path);
  }
}

/**
 * Parses file into keyed array of commands.
 *
 * @param string $file_path
 *      Absolute or relative file path for the .run file to load.
 * @return array
 *      Multidimensional associative array keying section names to an array
 *      of commands to execute in order.
 * @throws InvalidArgumentException
 *      The provided filename cannot be opened for reading.
 */
function parse_run_file($file_path) {
  $file_handle = fopen($file_path, 'r');
  if ($file_handle) {
    $commands = array();
    $current_section = '';
    while (($line = fgets($file_handle)) !== false) {
      $section_pattern = '/^\[([a-z A-Z 0-9 \- \_]+)\]/';
      $comment_pattern = '/\/\/[ ].*/';
      $line = trim($line);
      $line = preg_replace($comment_pattern, '', $line);
      if (!empty($line)) {
        $section_match = array();
        preg_match($section_pattern, $line, $section_match);
        if (!empty($section_match[1])) {
          $current_section = $section_match[1];
        }
        else {
          $commands[$current_section][] = $line;
        }
      }
    }
    fclose($file_handle);
    return $commands;
  }
  else {
    throw new InvalidArgumentException('Could not open file: ' . $file_path);
  }
}

/**
 * Parse a command string and perform variable replacements.
 *
 * @param string $command
 *      The command string with substitutable variables.
 * @param array $variables
 *      An associative array of variable names to values.
 * @return string
 *      The command string with the variable values substituted for their
 *      placeholders.
 * @throw Exception
 *      If there is an error with preg_replace substituting variable values.
 */
function parse_command($command, $variables) {
  $variable_pattern = '/%(%*([^%]+)%*)%/';
  $matches = array();
  $command = preg_replace_callback($variable_pattern,
    function ($matches) use ($variables) {
      return empty($variables[$matches[1]])
        ? $matches[1]
        : $variables[$matches[1]];
      },
    $command
  );
  if (!empty($command)) {
    return $command;
  }
  else {
    throw new Exception('Error parsing command substitutions.');
  }
}

/**
 * Execute a command in the local shell.
 *
 * This function also implements special case handling for the following 
 * command regexes.
 * - /^cd (.*)$/
 *
 * @param string $command
 *      The command string to be executed by the local shell.
 * @return array
 *      Array of strings containing the merged contents of STDOUT and STDERR.
 * @throws Exception
 *      If the command exits with a non-zero exit status.
 */
function execute_command($command) {
  $stdout = array();
  $return_value = 0;
  $cd_pattern = '/^cd (.*)$/';
  $cd_check = array();
  preg_match($cd_pattern, $command, $cd_check);
  if (empty($cd_check)) {
    exec($command, $stdout, $return_value);
  }
  else {
    $raw_path = empty($cd_check[1]) ? '' : $cd_check[1];
    $raw_path = trim($raw_path);
    $abs_path = realpath($raw_path);
    if ($abs_path) {
      if (!chdir($abs_path)) {
        $return_value = 1;
        $stdout[] = 'Could not cd to ' . $abs_path;
      }
    }
    else {
      $return_value = 1;
      $stdout[] = 'Could not cd to ' . $cd_check[1];
    }
  }
  if ($return_value !== 0) {
    throw new Exception('Error: ' . end($stdout));
  }
  else {
    return $stdout;
  }
}
?>
