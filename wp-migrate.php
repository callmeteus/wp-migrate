<?php
	/**
	 * @package WP Migrate
	 * Wordpress server migration script
	 * @author Matheus Giovani <matheus@ad3com.com.br>
	 * @see https://github.com/theprometeus/wp-migrate
	 */
	
	// Check if current platform is Windows
	define("IS_WINDOWS", PHP_OS === "WINNT" || PHP_OS === "WIN32");

	/**
	 * Migration configuration defaults
	 * @var array
	 */
	$config = [
		"target" => [
			// Target Wordpress installation URL
			"url" => "http://example.com",
			"ftp" => [
				// Target FTP host
				"host" => "localhost",
				// Target FTP user
				"user" => "anonymous",
				// Target FTP password
				"password" => null,
				// Target FTP directory
				"dir" => "public_html",
			]
		],
		"source" => [
			// Source directory (default points to current directory)
			"dir" => __DIR__
		],
		"skip-content" => false,
		"skip-database" => false
	];

	/**
	 * Usable functions
	 */
	
	/**
	 * Get a temporary file name
	 * @return string
	 */
	function getTempName() {
		return tempnam(sys_get_temp_dir(), "wpmigrate");
	}
	
	/**
	 * Walk over an array and callback it
	 * @param  Array    &$array   Array to be walked
	 * @param  Callable $callback Walk callback
	 * @param  Array    $current  Already walked keys
	 */
	function walk(Array &$array, Callable $callback, $current = []) {
		// Check if it's a string
		if (is_string($current)) {
			// Implode it
			$current = explode(".", $current);
		}

		// Try to get parameters from argv
		foreach($array as $key => &$content) {
			// Get current complete key
			$currentKey = implode(".", array_merge($current, [$key]));

			// Check if it's an array
			if (is_array($content)) {
				walk($content, $callback, $currentKey);
			} else {
				// Callback it
				call_user_func_array($callback, [$currentKey, &$content]);
			}
		}
	}

	/**
	 * Print a string on console
	 */
	function console_log(...$args) {
		echo date("[Y-m-d H:i:s]") . " ";

		foreach($args as $arg) {
			echo $arg . " ";
		}

		echo "\n";
	}

	/**
	 * Do file cleanup
	 */
	function cleanup() {
		if (defined("PROMPT_FILE")) {
			unlink(PROMPT_FILE);
		}

		if (defined("SQL_FILE")) {
			unlink(SQL_FILE);
		}

		if (defined("SCRIPT_FILE")) {
			unlink(SCRIPT_FILE);
		}
	}

	/**
	 * Check if a remote FTP directory exists
	 * @param  int $ftp    FTP resource
	 * @param  string $dir Remote directory
	 * @return boolean
	 */
	function ftp_directory_exists($ftp, $dir) { 
		// Get the current working directory 
		$origin = ftp_pwd($ftp); 

		// Attempt to change directory, suppress errors 
		if (@ftp_chdir($ftp, $dir))  { 
			// If the directory exists, set back to origin 
			ftp_chdir($ftp, $origin);    
			return true; 
		} 

		// Directory does not exist 
		return false; 
	}

	/**
	 * Prompt user for a response
	 * @param  string  $message Prompt message
	 * @param  boolean $hidden  Need to hide the prompt?
	 * @return string
	 */
	function prompt($message = "prompt: ", $hidden = false) {
		// Show the prompt message
		echo $message;

		// Get the return
		$ret = $hidden ? exec(IS_WINDOWS ? PROMPT_FILE : "read -s PW; echo $PW") : rtrim(fgets(STDIN), PHP_EOL);

		// Check if is hidden
		if ($hidden) {
			// Show an end of line
			echo PHP_EOL;
		}

		return $ret;
	}

	/**
	 * Create a full FTP directory, including parent and subdirectories
	 * @param  int $ftp    FTP resource
	 * @param  string $dir Directory name
	 * @return boolean
	 */
	function ftp_mkdir_full($ftp, $dir) {
		// Explode all subdirectories
		$directories = explode("/", $dir);

		/**
		 * Current directory
		 * @var string
		 */
		$current = "";

		// Iterate over all directories
		foreach($directories as $dir) {
			// Update current directory
			$current .= $dir . "/";

			// Create it
			@ftp_mkdir($ftp, rtrim($current, "/"));
		}

		return true;
	}

	// Set default exception handler
	set_exception_handler(function($error) {
		// Show the error
		console_log("An error ocurred while running the migration:");
		console_log($error->getMessage());

		// Do a cleanup
		cleanup();
	});

	/**
	 * Startup
	 */
	
	// Check if is Windows
	if (IS_WINDOWS) {
		// Create .bat file for password prompting
		define("PROMPT_FILE", str_replace(".tmp", ".bat", getTempName()));

		// Write file contents
		file_put_contents(PROMPT_FILE, '
			SetLocal DisableDelayedExpansion
			Set "Line="
			For /F %%# In (\'"Prompt;$H & For %%# in (1) Do Rem"\') Do (
				Set "BS=%%#"
			)

			:loop_start
				Set "Key="
				For /F "delims=" %%# In (\'Xcopy /L /W "%~f0" "%~f0" 2^>Nul\') Do (
					If Not Defined Key (
						Set "Key=%%#"
					)
				)

				Set "Key=%Key:~-1%"
				SetLocal EnableDelayedExpansion

				If Not Defined Key (
					Goto :loop_end
				)
				If %BS%==^%Key% (
					Set "Key="
				If Defined Line (
					Set "Line=!Line:~0,-1!"
					)
				)
				If Not Defined Line (
					EndLocal
					Set "Line=%Key%"
				) Else (
					For /F "delims=" %%# In ("!Line!") Do (
					EndLocal
					Set "Line=%%#%Key%"
					)
				)

			Goto :loop_start
			:loop_end

			Echo;!Line!
		');
	}
	
	/**
	 * Array of config descriptions
	 * @var array
	 */
	$configs = [
		"source.dir" => [
			"name" => "Source Wordpress installation directory",
			"type" => "string"
		],
		"target.url" => [
			"name" => "Remote Wordpress site URL",
			"type" => "string"
		],
		"target.ftp.host" => [
			"name" => "Remote FTP host",
			"type" => "string"
		],
		"target.ftp.user" => [
			"name" => "Remote FTP user",
			"type" => "string"
		],
		"target.ftp.password" => [
			"name" => "Remote FTP password",
			"type" => "password"
		],
		"target.ftp.dir" => [
			"name" => "Remote FTP directory",
			"type" => "string"
		]
	];

	// Try to get paramters from argv
	walk($config, function($key, &$content) use ($configs) {
		/**
		 * Default value handler
		 * @var string
		 */
		$default = null;

		// Try to get the value from arguments
		$val = getopt(null, ["{$key}::"]);

		// Check if don't have any value
		if (!is_array($val) || count($val) === 0) {
			$val = getopt(null, ["{$key}:"]);
		}

		// Check if has a valid value
		if (is_array($val) && count($val) > 0) {
			// Update it
			$content = current($val);
		}

		// Check if key has a descriptor
		if (!isset($configs[$key])) {
			return true;
		}

		// Get config descriptor
		$descriptor = $configs[$key];

		// Prompt user for new data
		$newContent = prompt($descriptor["name"] . " [{$content}]: ", $descriptor["type"] === "password");

		// Check if any data has been given
		if (!empty($newContent) && strlen($newContent) > 0) {
			// Attribute new content data
			$content = $newContent;
		}
	});

	echo "\n";
	
	// Check if PHP FTP is installed
	if (!function_exists("ftp_connect") && !$config->{"skip-content"}) {
		throw new Exception("WP Migrate requires PHP FTP module to run the migration.", 1);
	}

	// Check if PHP FTP is installed
	if (!function_exists("mysqli_connect") && !$config->{"skip-database"}) {
		throw new Exception("WP Migrate requires PHP MySQLi module to run the migration.", 1);
	}
	
	// Convert config to object
	$config = json_decode(json_encode($config));

	// Check if target URL has a protocol
	if (stripos($config->target->url, "http") === false) {
		// Set target URL with protocol
		$config->target->url = "http://" . $config->target->url;
	}

	console_log("Starting up...");
	console_log("Source Wordpress directory is", $config->source->dir);

	// Check if source has a valid Wordpress installation
	if (!file_exists($config->source->dir . "/wp-config.php")) {
		throw new Exception("Invalid source Wordpress installation.", 1);
	}

	// Open Wordpress configuration file as text
	$wpConfig = file_get_contents($config->source->dir . "/wp-config.php");

	$wpRegex = "/\'(.*?)\'\,[| ]\'(.*?)\'/";

	// Get all variables
	preg_match_all($wpRegex, $wpConfig, $matches, PREG_SET_ORDER);

	// Iterate over all variables
	foreach($matches as $match) {
		// Define it
		define($match[1], $match[2]);
	}

	// Check if need to skip database
	if (!$config->{"skip-database"}) {
		// Create a new database connection
		$db = new MySQLi(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);

		// Check if the connection succeeded
		if ($db->connect_errno > 0) {
			throw new Exception("Can't connect to Wordpress database [{$db->connect_error}].");
		}

		console_log("Connected to the database.");

		// Set the charset
		$db->set_charset(DB_CHARSET);

		console_log("Extracting the database, this may take some time...");

		/**
		 * Export the database
		 */
		
		/**
		 * Final SQL code container
		 * @var string
		 */
		$sql = "";

		// Get temp sql file
		define("SQL_FILE", getTempName());

		// Get all tables
		$tables = $db->query("SHOW TABLES");

		// Iterate over all the tables
		while($table = $tables->fetch_array()) {
			// Get table name
			$table = $table[0];

			console_log("Extracing table", $table);

			// Append drop table if exists
			$sql .= "DROP TABLE IF EXISTS {$table};\n";

			// Get table creation code
			$query = $db->query("SHOW CREATE TABLE {$table}");

			// While has code
			while($row = $query->fetch_array()) {
				// Append to the SQL
				$sql .= $row[1] . ";\n\n";
			}

			// Get table contents
			$query = $db->query("SELECT * FROM {$table}");

			// Get row count
			$count = $query->num_rows;

			// Check if has rows
			if ($count === 0) {
				console_log("\tNo results to extract.");

				continue;
			}

			// Get all fields
			$fields = $query->fetch_fields();

			// Get field count
			$fieldCount = count($fields);

			// Create the header
			$header = "INSERT INTO `{$table}` (";

			console_log("\tExtracting", $count, "results...");

			// Iterate over all fields to make the header
			for($i = 0; $i < $fieldCount; $i++) {
				// Append field name to the header
				$header .= "`" . $fields[$i]->name . "`";

				// Check if we haven't reached the last field
				if ($i < $fieldCount) {
					// Append a comma to the header
					$header .= ",";
				}
			}

			// Append the data container
			$header .= ") VALUES (";

			/**
			 * Appended rows counter
			 * @var integer
			 */
			$counter = 0;

			// Iterate over all results
			while($row = $query->fetch_array()) {
				// Check if we achieved 400 rows
				if (($counter % 400) === 0) {
					// Append another insert head
					$sql .= $header;
				}

				/**
				 * Result content handler
				 * @var string
				 */
				$contents = "(";

				// Iterate over all fields
				for($i = 0; $i < $fieldCount; $i++) {
					// Replace new lines with real new lines
					$content = str_replace("\n", "\\n", $db->real_escape_string($row[$i]));

					// Switch over the field type
					switch($fields[$i]->type) {
						// If it's a number
						case 8: case 3:
							// Append it as a number
							$contents .=  $content;
						break;

						default:
							// Append it as a string
							$contents .= "'". $content ."'";
					}

					// Check if we haven't reached the end
					if ($i < $fieldCount - 1) {
						// Add a comma to append a new field
						$contents .= ', ';
					}
				}

				// Check if reached the end
				if (($counter + 1) === $count) {
					// Append end line to it
					$contents .= ");\n\n";
				} else {
					// Append continuation line to it
					$contents .= "),\n";
				}
			}

			console_log("\tDone");

			// Append the SQL
			$sql .= $contents;
		}

		// Save the SQL code
		file_put_contents(SQL_FILE, $sql);

		// Close the database connection
		$db->close();

		console_log("Full SQL has been saved to", SQL_FILE);
	}

	console_log("Connecting to target FTP server...");

	// Get target host IP
	$config->target->ftp->host = gethostbyname($config->target->ftp->host);

	// Connect to the FTP server
	$ftp = @ftp_connect($config->target->ftp->host);

	// Check if the connection succeeded
	if (!$ftp) {
		throw new Exception("Couldn't connect to FTP server {$config->target->ftp->host}", 1);
	}

	console_log("\tConnected.");

	// Check if the authentication succeeded
	if (!@ftp_login($ftp, $config->target->ftp->user, $config->target->ftp->password)) {
		throw new Exception("Couldn't login on {$config->target->ftp->host} as {$config->target->ftp->user}: " . error_get_last()["message"], 1);
	}

	// Check if the authentication succeeded
	if (!@ftp_chdir($ftp, $config->target->ftp->dir)) {
		throw new Exception("Failed to change FTP directory to {$config->target->ftp->dir}: " . error_get_last()["message"], 1);
	}

	// Enter passive mode
	ftp_pasv($ftp, true);

	console_log("Checking if WP Migrate folder exists...");

	// Get wp-migrate file list
	$exists = ftp_nlist($ftp, "wp-migrate");

	// Check if remote directory exists
	if ($exists == false) {
		console_log("\tCreating WP Migrate folder...");

		// Create the migration directory
		if (!@ftp_mkdir($ftp, "wp-migrate")) {
			throw new Exception("Couldn't create remote directory wp-migrate: " . error_get_last()["message"], 1);
		}

		console_log("\t\tCreated");
	}

	// Check if need to migrate database
	if (!$config->{"skip-database"}) {
		console_log("Sending SQL query file...");

		// Try to send the SQL file
		if (!@ftp_put($ftp, "wp-migrate/db.sql", SQL_FILE, FTP_ASCII)) {
			throw new Exception("Couldn't send SQL file to the FTP server: " . error_get_last()["message"], 1);
		}

		console_log("\tSent.");

		/**
		 * Remote database restore script
		 * @var string
		 */
		$script = '
			<?php
				// Set all error reporting 
				error_reporting(E_ALL);

				try {
					// Include Wordpress loader file 
					// This way we have access to the entire Wordpress API
					require_once __DIR__ . "/../wp-config.php";

					// Get the SQL file
					$sql = file_get_contents("db.sql");

					// Connect to the database 
					$db = new MySQLi(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);

					// Do the query
					$result = $db->multi_query($sql);

					if ($result) {
						// If everything runs fine, script should output true
						echo "1";
					} else {
						echo $wpdb->last_error;
					}
				} catch(Exception $e) {
					echo $e->getMessage();
				}
		';

		console_log("\tSending migration script file...");

		// Get temp script filename
		define("SCRIPT_FILE", getTempName());

		// Save script contents
		file_put_contents(SCRIPT_FILE, $script);

		// Try to send the SQL file
		if (!@ftp_put($ftp, "wp-migrate/migrate.php", SCRIPT_FILE, FTP_ASCII)) {
			throw new Exception("Couldn't send SQL file to the FTP server: " . error_get_last()["message"], 1);
		}

		console_log("\t\tSent.");

		console_log("\tExecuting migration script file...");

		// Run the script
		$result = trim(file_get_contents(rtrim($config->target->url, "/") . "/wp-migrate/migrate.php"));

		// Check if succeeded
		if ($result === "1") {
			console_log("\t\tDatabase successfully migrated!");
		} else {
			throw new Exception("Database migration failed: {$result}", 1);
		}

		// Try to remove all files and the directory
		if (!ftp_delete($ftp, "wp-migrate/migrate.php") || !ftp_delete($ftp, "wp-migrate/db.sql") || !ftp_rmdir($ftp, "wp-migrate")) {
			throw new Exception("Failed to clean remote installation files: " . error_get_last()["message"], 1);
		}
	}

	// Check if need to migrate content
	if (!$config->{"skip-content"}) {
		console_log("Sending content to the FTP...");

		// Change directory to wp-content
		ftp_chdir($ftp, "wp-content");

		// Get content directory
		$contentDir = rtrim($config->source->dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . "wp-content";

		// Read entire source wp-content directory
		$dir = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($contentDir, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);

		// Convert the iterator to array
		$dir = iterator_to_array($dir);

		// Iterate over all files
		while($file = next($dir)) {
			// Get full file dir
			$fileLocation = $contentDir . $file;

			// Get normalized file name
			$fileName = str_replace(DIRECTORY_SEPARATOR, "/", str_replace($contentDir . DIRECTORY_SEPARATOR, "", $file));

			// Check if file directory exists
			if (is_dir($file) && !ftp_directory_exists($ftp, $fileName)) {
				console_log("Creating directory {$fileName}...");

				// Create directory and subdirectories
				ftp_mkdir_full($ftp, $file);

				console_log("\tCreated.");
			}

			// Check if it's a directory
			if (is_dir($file)) {
				continue;
			}

			// Check if file exists
			if (ftp_size($ftp, $fileName) > -1) {
				console_log("{$fileName} already exists, skipping...");
				// Skip it
				continue;
			}

			console_log("Sending {$fileName}...");

			// Try to send it to the FTP
			if (!@ftp_put($ftp, $fileName, $file, FTP_ASCII)) {
				// Get the last error
				$error = error_get_last();

				// Check if it's a not found error
				// This is caused by a missing directory
				if (stripos($error["message"], "no such file or directory") !== false) {
					// Get file directory
					$fileDir = dirname($fileName);

					console_log("\tParent directory {$fileDir} doesn't exists, creating it...");

					// Create the directory
					ftp_mkdir_full($ftp, $fileDir);

					// Go one step back
					prev($dir);

					// Skip the error
					continue;
				}

				throw new Exception("Failed to send {$file} to the FTP server: " . $error["message"], 1);
			}

			console_log("\tSent.");
		}
	}

	// Close the FTP connection
	ftp_close($ftp);

	console_log("Done! You can see your site live at {$config->target->url}");
