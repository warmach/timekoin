<?PHP
define("TIMEKOIN_VERSION","1.0"); // This Timekoin Software Version
define("NEXT_VERSION","tk_client_current_version0.txt"); // What file to check for future versions

error_reporting(E_ERROR | E_CORE_ERROR | E_COMPILE_ERROR); // Disable most error reporting except for fatal errors
ini_set('display_errors', FALSE);

//***********************************************************************************
//***********************************************************************************
function tk_client_task()
{
	// Repeat Task

	return;
}
//***********************************************************************************
//***********************************************************************************
function filter_sql($string)
{
	// Filter symbols that might lead to an SQL injection attack
	$symbols = array("'", "%", "*", "`");
	$string = str_replace($symbols, "", $string);

	return $string;
}
//***********************************************************************************
//***********************************************************************************
function find_string($start_tag, $end_tag, $full_string, $end_match = FALSE)
{
	$delimiter = '|';
	
	if($end_match == FALSE)
	{
		$regex = $delimiter . preg_quote($start_tag, $delimiter) . '(.*?)'  . preg_quote($end_tag, $delimiter)  . $delimiter  . 's';
	}
	else
	{
		$regex = $delimiter . preg_quote($start_tag, $delimiter) . '(.*)'  . preg_quote($end_tag, $delimiter)  . $delimiter  . 's';
	}

	preg_match_all($regex,$full_string,$matches);

	foreach($matches[1] as $found_string)
	{
	}
	
	return $found_string;
}
//***********************************************************************************
//***********************************************************************************
function write_log($message, $type)
{
	// Write Log Entry
	mysql_query("INSERT DELAYED INTO `activity_logs` (`timestamp` ,`log` ,`attribute`)	
		VALUES ('" . time() . "', '" . substr($message, 0, 256) . "', '$type')");
	return;
}
//***********************************************************************************
//***********************************************************************************
function my_public_key()
{
	return mysql_result(mysql_query("SELECT * FROM `my_keys` WHERE `field_name` = 'server_public_key' LIMIT 1"),0,1);
}
//***********************************************************************************
function my_private_key()
{
	return mysql_result(mysql_query("SELECT * FROM `my_keys` WHERE `field_name` = 'server_private_key' LIMIT 1"),0,1);
}
//***********************************************************************************
function poll_peer($ip_address, $domain, $subfolder, $port_number, $max_length, $poll_string, $custom_context)
{
	if(empty($custom_context) == TRUE)
	{
		// Standard socket close
		$context = stream_context_create(array('http' => array('header'=>'Connection: close'))); // Force close socket after complete
	}
	else
	{
		// Custom Context Data
		$context = $custom_context;
	}

	if(empty($domain) == TRUE)
	{
		$site_address = $ip_address;
	}
	else
	{
		$site_address = $domain;
	}

	if($port_number == 443)
	{
		$ssl = "s";
	}
	else
	{
		$ssl = NULL;
	}

	if(empty($subfolder) == FALSE)
	{
		// Sub-folder included
		$poll_data = filter_sql(file_get_contents("http$ssl://$site_address:$port_number/$subfolder/$poll_string", FALSE, $context, NULL, $max_length));
	}
	else
	{
		// No sub-folder
		$poll_data = filter_sql(file_get_contents("http$ssl://$site_address:$port_number/$poll_string", FALSE, $context, NULL, $max_length));
	}

	return $poll_data;
}
//***********************************************************************************
//***********************************************************************************
function tk_encrypt($key, $crypt_data)
{
	if(function_exists('openssl_private_encrypt') == TRUE)
	{
		openssl_private_encrypt($crypt_data, $encrypted_data, $key);
	}
	else
	{
		require_once('RSA.php');
		$rsa = new Crypt_RSA();
		$rsa->setEncryptionMode(CRYPT_RSA_ENCRYPTION_PKCS1);
		$rsa->loadKey($key);
		$encrypted_data = $rsa->encrypt($crypt_data);
	}

	return $encrypted_data;
}
//***********************************************************************************
//***********************************************************************************
function tk_decrypt($key, $crypt_data)
{
	if(function_exists('openssl_public_decrypt') == TRUE)
	{
		// Use OpenSSL if it is working
		openssl_public_decrypt($crypt_data, $decrypt, $key);
	}
	else
	{
		// Use built in Code
		require_once('RSA.php');
		$rsa = new Crypt_RSA();
		$rsa->setEncryptionMode(CRYPT_RSA_ENCRYPTION_PKCS1);
		$rsa->loadKey($key);
		$decrypt = $rsa->decrypt($crypt_data);
	}

	return $decrypt;
}
//***********************************************************************************
//***********************************************************************************
function check_crypt_balance($public_key)
{
	write_log("Balance Check Started","debug");
	
	if(empty($public_key) == TRUE)
	{
		return 0;
	}

	// Ask one of my active peers
	ini_set('user_agent', 'Timekoin Client v' . TIMEKOIN_VERSION);
	ini_set('default_socket_timeout', 5); // Timeout for request in seconds

	$sql_result = mysql_query("SELECT * FROM `active_peer_list` ORDER BY RAND() LIMIT 1");
	$sql_row = mysql_fetch_array($sql_result);

	$ip_address = $sql_row["IP_Address"];
	$domain = $sql_row["domain"];
	$subfolder = $sql_row["subfolder"];
	$port_number = $sql_row["port_number"];

	// Create map with request parameters
	$params = array ('public_key' => base64_encode($public_key));
	 
	// Build Http query using params
	$query = http_build_query($params);
	 
	// Create Http context details
	$contextData = array (
						 'method' => 'POST',
						 'header' => "Connection: close\r\n".
										 "Content-Length: ".strlen($query)."\r\n",
						 'content'=> $query );
	 
	// Create context resource for our request
	$context = stream_context_create (array ( 'http' => $contextData ));

	write_log("$ip_address, $domain, $subfolder, $port_number","db");

	return poll_peer($ip_address, $domain, $subfolder, $port_number, 20, "queueclerk.php?action=key_balance&hash=open", $context);
}
//***********************************************************************************
//***********************************************************************************
function tk_time_convert($time)
{
	if($time < 0)
	{
		return "0 sec";
	}
	
	if($time < 60)
	{
		if($time == 1)
		{
			$time .= " sec";
		}
		else
		{
			$time .= " secs";
		}
	}
	else if($time >= 60 && $time < 3600)
	{
		if($time >= 60 && $time < 120)
		{
			$time = intval($time / 60) . " min";
		}
		else
		{
			$time = intval($time / 60) . " mins";
		}
	}
	else if($time >= 3600 && $time < 86400)
	{
		if($time >= 3600 && $time < 7200)
		{
			$time = intval($time / 3600) . " hour";
		}
		else
		{
			$time = intval($time / 3600) . " hours";
		}
	}
	else if($time >= 86400)
	{
		if($time >= 86400 && $time < 172800)
		{
			$time = intval($time / 86400) . " day";
		}
		else
		{
			$time = intval($time / 86400) . " days";
		}		
	}

	return $time;
}
//***********************************************************************************
//***********************************************************************************
function db_cache_balance($my_public_key)
{
/*
	// Check server balance via custom memory index
	$my_server_balance = mysql_result(mysql_query("SELECT * FROM `balance_index` WHERE `public_key_hash` = 'server_timekoin_balance' LIMIT 1"),0,"balance");
	$my_server_balance_last = mysql_result(mysql_query("SELECT * FROM `balance_index` WHERE `public_key_hash` = 'server_timekoin_balance' LIMIT 1"),0,"block");

	if($my_server_balance === FALSE)
	{
		// Does not exist, needs to be created
		mysql_query("INSERT INTO `timekoin`.`balance_index` (`block` ,`public_key_hash` ,`balance`)VALUES ('0', 'server_timekoin_balance', '0')");

		// Update record with the latest balance
		$display_balance = check_crypt_balance($my_public_key);

		mysql_query("UPDATE `balance_index` SET `block` = '" . time() . "' , `balance` = '$display_balance' WHERE `balance_index`.`public_key_hash` = 'server_timekoin_balance' LIMIT 1");
	}
	else
	{
		if($my_server_balance_last < transaction_cycle(0) && time() - transaction_cycle(0) > 25) // Generate 25 seconds after cycle
		{
			// Last generated balance is older than the current cycle, needs to be updated
			// Update record with the latest balance
			$display_balance = check_crypt_balance($my_public_key);

			mysql_query("UPDATE `balance_index` SET `block` = '" . time() . "' , `balance` = '$display_balance' WHERE `balance_index`.`public_key_hash` = 'server_timekoin_balance' LIMIT 1");
		}
		else
		{
			$display_balance = $my_server_balance;
		}
	}

	return $display_balance;
 */

	return check_crypt_balance($my_public_key);
}
//***********************************************************************************
//***********************************************************************************
function send_timekoins($my_private_key, $my_public_key, $send_to_public_key, $amount, $message)
{
	$arr1 = str_split($send_to_public_key, 181);

	$encryptedData1 = tk_encrypt($my_private_key, $arr1[0]);
	$encryptedData64_1 = base64_encode($encryptedData1);	

	$encryptedData2 = tk_encrypt($my_private_key, $arr1[1]);
	$encryptedData64_2 = base64_encode($encryptedData2);

	if(empty($message) == TRUE)
	{
		$transaction_data = "AMOUNT=$amount---TIME=" . time() . "---HASH=" . hash('sha256', $encryptedData64_1 . $encryptedData64_2);
	}
	else
	{
		// Sanitization of message
		// Filter symbols that might lead to a transaction hack attack
		$symbols = array("|", "?", "="); // SQL + URL
		$message = str_replace($symbols, "", $message);

		// Trim any message to 64 characters max and filter any sql
		$message = filter_sql(substr($message, 0, 64));
		
		$transaction_data = "AMOUNT=$amount---TIME=" . time() . "---HASH=" . hash('sha256', $encryptedData64_1 . $encryptedData64_2) . "---MSG=$message";
	}

	$encryptedData3 = tk_encrypt($my_private_key, $transaction_data);

	$encryptedData64_3 = base64_encode($encryptedData3);
	$triple_hash_check = hash('sha256', $encryptedData64_1 . $encryptedData64_2 . $encryptedData64_3);

	$sql = "INSERT INTO `my_transaction_queue` (`timestamp`,`public_key`,`crypt_data1`,`crypt_data2`,`crypt_data3`, `hash`, `attribute`) VALUES 
		('" . time() . "', '$my_public_key', '$encryptedData64_1', '$encryptedData64_2' , '$encryptedData64_3', '$triple_hash_check' , 'T')";

	if(mysql_query($sql) == TRUE)
	{
		// Success code
		return TRUE;
	}
	else
	{
		return FALSE;
	}
}
//***********************************************************************************
//***********************************************************************************
function unix_timestamp_to_human($timestamp = "", $format = 'D d M Y - H:i:s')
{
	 if (empty($timestamp) || ! is_numeric($timestamp)) $timestamp = time();
	 return ($timestamp) ? date($format, $timestamp) : date($format, $timestamp);
}
//***********************************************************************************
//***********************************************************************************
function is_private_ip($ip, $ignore = FALSE)
{
	if(empty($ip) == TRUE)
	{
		return FALSE;
	}
	
	if($ignore == TRUE)
	{
		$result = FALSE;
	}
	else
	{
		if(filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE) == FALSE)
		{
			$result = TRUE;
		}
	}
	
	return $result;
}
//***********************************************************************************
//***********************************************************************************
function is_domain_valid($domain)
{
	$result = TRUE;
	
	if(empty($domain) == TRUE)
	{
		$result = FALSE;		
	}

	if(filter_var($domain, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) == TRUE)
	{
		$result = FALSE;
	}

	if(filter_var($domain, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) == TRUE)
	{
		$result = FALSE;
	}

	if(strtolower($domain) == "localhost")
	{
		$result = FALSE;
	}

	return $result;
}
//***********************************************************************************
//***********************************************************************************	
function generate_new_keys()
{
	require_once('RSA.php');

	$rsa = new Crypt_RSA();
	extract($rsa->createKey(1536));

	if(empty($privatekey) == FALSE && empty($publickey) == FALSE)
	{
		$symbols = array("\r");
		$new_publickey = str_replace($symbols, "", $publickey);
		$new_privatekey = str_replace($symbols, "", $privatekey);

		$sql = "UPDATE `my_keys` SET `field_data` = '$new_privatekey' WHERE `my_keys`.`field_name` = 'server_private_key' LIMIT 1";

		if(mysql_query($sql) == TRUE)
		{
			// Private Key Update Success
			$sql = "UPDATE `my_keys` SET `field_data` = '$new_publickey' WHERE `my_keys`.`field_name` = 'server_public_key' LIMIT 1";
			
			if(mysql_query($sql) == TRUE)
			{
				// Blank reverse crypto data field
				mysql_query("UPDATE `options` SET `field_data` = '' WHERE `options`.`field_name` = 'generation_key_crypt' LIMIT 1");

				// Public Key Update Success				
				return 1;
			}
		}
	}
	else
	{
		// Key Pair Creation Error
		return 0;
	}

	return 0;
}
//***********************************************************************************	
//***********************************************************************************
function check_for_updates()
{
	// Poll timekoin.com for any program updates
	$context = stream_context_create(array('http' => array('header'=>'Connection: close'))); // Force close socket after complete
	ini_set('user_agent', 'Timekoin Server (GUI) v' . TIMEKOIN_VERSION);
	ini_set('default_socket_timeout', 15); // Timeout for request in seconds

	$update_check1 = 'Checking for Updates....</br></br>';

	$poll_version = file_get_contents("https://timekoin.com/tkupdates/" . NEXT_VERSION, FALSE, $context, NULL, 10);

	if($poll_version > TIMEKOIN_VERSION && empty($poll_version) == FALSE)
	{
		$update_check1 .= '<strong>New Version Available <font color="blue">' . $poll_version . '</font></strong></br></br>
		<FORM ACTION="index.php?menu=options&upgrade=doupgrade" METHOD="post"><input type="submit" name="Submit3" value="Perform Software Update" /></FORM>';
	}
	else if($poll_version <= TIMEKOIN_VERSION && empty($poll_version) == FALSE)
	{
		$update_check1 .= 'Current Version: <strong>' . TIMEKOIN_VERSION . '</strong></br></br><font color="blue">No Update Necessary.</font>';	
	}
	else
	{
		$update_check1 .= '<strong>ERROR: Could Not Contact Secure Server https://timekoin.com</strong>';
	}

	return $update_check1;
}
//***********************************************************************************
//***********************************************************************************
function install_update_script($script_name, $script_file)
{
	$fh = fopen($script_name, 'w');

	if($fh != FALSE)
	{
		if(fwrite($fh, $script_file) > 0)
		{
			if(fclose($fh) == TRUE)
			{
				// Update Complete
				return '<strong><font color="green">Update Complete...</strong></font></br></br>';
			}
			else
			{
				return '<strong><font color="red">ERROR: Update FAILED with a file Close Error.</strong></font></br></br>';
			}
		}
	}
	else
	{
		return '<strong><font color="red">ERROR: Update FAILED with unable to Open File Error.</strong></font></br></br>';
	}
}
//***********************************************************************************
//***********************************************************************************
function check_update_script($script_name, $script, $php_script_file, $poll_version, $context)
{
	$update_status_return = NULL;
	
	$poll_sha = file_get_contents("https://timekoin.com/tkupdates/v$poll_version/$script.sha", FALSE, $context, NULL, 64);

	if(empty($poll_sha) == FALSE)
	{
		$download_sha = hash('sha256', $php_script_file);

		if($download_sha != $poll_sha)
		{
			// Error in SHA match, file corrupt
			return FALSE;
		}
		else
		{
			$update_status_return .= 'Server SHA: <strong>' . $poll_sha . '</strong></br>Download SHA: <strong>' . $download_sha . '</strong></br>';
			$update_status_return .= '<strong>' . $script_name . '</strong> SHA Match...</br>';
			return $update_status_return;
		}
	}

	return FALSE;
}
//***********************************************************************************
//***********************************************************************************
function get_update_script($php_script, $poll_version, $context)
{
	return file_get_contents("https://timekoin.com/tkupdates/v$poll_version/$php_script.txt", FALSE, $context, NULL);
}
//***********************************************************************************
//***********************************************************************************
function run_script_update($script_name, $script_php, $poll_version, $context, $php_format = 1, $sub_folder = "")
{
	$php_file = get_update_script($script_php, $poll_version, $context);
	
	if(empty($php_file) == TRUE)
	{
		return ' - <strong>No Update Available</strong>...</br></br>';
	}
	else
	{
		// File exist, is the download valid?
		$sha_check = check_update_script($script_name, $script_php, $php_file, $poll_version, $context);

		if($sha_check == FALSE)
		{
			return ' - <strong>ERROR: Unable to Download File Properly</strong>...</br></br>';
		}
		else
		{
			$update_status .= $sha_check;
			
			if($php_format == 1)
			{
				// PHP Files are downloaded as text, then renamed to the .php extension
				$update_status .= install_update_script($script_php . '.php', $php_file);
			}
			else
			{
				if(empty($sub_folder) == FALSE)
				{
					// This file is installed to a sub-folder
					$update_status .= install_update_script("$sub_folder/" . $script_php, $php_file);
				}
				else
				{
					$update_status .= install_update_script($script_php, $php_file);
				}
			}

			return $update_status;
		}
	}
}
//***********************************************************************************
function do_updates()
{
	// Poll timekoin.com for any program updates
	$context = stream_context_create(array('http' => array('header'=>'Connection: close'))); // Force close socket after complete
	ini_set('user_agent', 'Timekoin Server (GUI) v' . TIMEKOIN_VERSION);
	ini_set('default_socket_timeout', 30); // Timeout for request in seconds

	$poll_version = file_get_contents("https://timekoin.com/tkupdates/" . NEXT_VERSION, FALSE, $context, NULL, 10);

	$update_status = 'Starting Update Process...</br></br>';

	if(empty($poll_version) == FALSE)
	{
		//****************************************************
		//Check for CSS updates
		$update_status .= 'Checking for <strong>CSS Template</strong> Update...</br>';
		$update_status .= run_script_update("CSS Template (admin.css)", "admin.css", $poll_version, $context, 0, "css");
		//****************************************************
		//****************************************************
		$update_status .= 'Checking for <strong>RSA Code</strong> Update...</br>';
		$update_status .= run_script_update("RSA Code (RSA.php)", "RSA", $poll_version, $context);
		//****************************************************
		$update_status .= 'Checking for <strong>Openssl Template</strong> Update...</br>';
		$update_status .= run_script_update("Openssl Template (openssl.cnf)", "openssl.cnf", $poll_version, $context, 0);
		//****************************************************
		//****************************************************
		$update_status .= 'Checking for <strong>Balace Indexer</strong> Update...</br>';
		$update_status .= run_script_update("Balance Indexer (balance.php)", "balance", $poll_version, $context);
		//****************************************************
		$update_status .= 'Checking for <strong>Transaction Foundation Manager</strong> Update...</br>';
		$update_status .= run_script_update("Transaction Foundation Manager (foundation.php)", "foundation", $poll_version, $context);
		//****************************************************
		$update_status .= 'Checking for <strong>Currency Generation Manager</strong> Update...</br>';
		$update_status .= run_script_update("Currency Generation Manager (generation.php)", "generation", $poll_version, $context);
		//****************************************************
		$update_status .= 'Checking for <strong>Generation Peer Manager</strong> Update...</br>';
		$update_status .= run_script_update("Generation Peer Manager (genpeer.php)", "genpeer", $poll_version, $context);
		//****************************************************
		$update_status .= 'Checking for <strong>Timekoin Web Interface</strong> Update...</br>';
		$update_status .= run_script_update("Timekoin Web Interface (index.php)", "index", $poll_version, $context);
		//****************************************************
		$update_status .= 'Checking for <strong>Main Program</strong> Update...</br>';
		$update_status .= run_script_update("Main Program (main.php)", "main", $poll_version, $context);
		//****************************************************
		$update_status .= 'Checking for <strong>Peer List Manager</strong> Update...</br>';
		$update_status .= run_script_update("Peer List Manager (peerlist.php)", "peerlist", $poll_version, $context);
		//****************************************************
		$update_status .= 'Checking for <strong>Transaction Queue Manager</strong> Update...</br>';
		$update_status .= run_script_update("Transaction Queue Manager (queueclerk.php)", "queueclerk", $poll_version, $context);
		//****************************************************
		$update_status .= 'Checking for <strong>Timekoin Module Status</strong> Update...</br>';
		$update_status .= run_script_update("Timekoin Module Status (status.php)", "status", $poll_version, $context);
		//****************************************************
		$update_status .= 'Checking for <strong>Web Interface Template</strong> Update...</br>';
		$update_status .= run_script_update("Web Interface Template (templates.php)", "templates", $poll_version, $context);
		//****************************************************
		$update_status .= 'Checking for <strong>Transaction Clerk</strong> Update...</br>';
		$update_status .= run_script_update("Transaction Clerk (transclerk.php)", "transclerk", $poll_version, $context);
		//****************************************************
		$update_status .= 'Checking for <strong>Treasurer Processor</strong> Update...</br>';
		$update_status .= run_script_update("Treasurer Processor (treasurer.php)", "treasurer", $poll_version, $context);
		//****************************************************
		$update_status .= 'Checking for <strong>Process Watchdog</strong> Update...</br>';
		$update_status .= run_script_update("Process Watchdog (watchdog.php)", "watchdog", $poll_version, $context);
		//****************************************************
		// We do the function storage last because it contains the version info.
		// That way if some unknown error prevents updating the files above, this
		// will allow the user to try again for an update without being stuck in
		// a new version that is half-updated.
		$update_status .= 'Checking for <strong>Function Storage</strong> Update...</br>';
		$update_status .= run_script_update("Function Storage (function.php)", "function", $poll_version, $context);
		//****************************************************

		$finish_message = file_get_contents("https://timekoin.com/tkupdates/v$poll_version/ZZZfinish.txt", FALSE, $context, NULL);
		$update_status .= '</br>' . $finish_message;
	}
	else
	{
		$update_status .= '<strong>ERROR: Could Not Contact Secure Server https://timekoin.com</strong>';
	}

	return $update_status;
}
//***********************************************************************************
function find_file($dir, $pattern)
{
	// Get a list of all matching files in the current directory
	$files = glob("$dir/$pattern");

	// Find a list of all directories in the current directory
	//foreach (glob("$dir/{.[^.]*,*}", GLOB_BRACE|GLOB_ONLYDIR) as $sub_dir)
	foreach (glob("$dir/{*}", GLOB_BRACE|GLOB_ONLYDIR) as $sub_dir)
	{
		$arr = find_file($sub_dir, $pattern);  // Resursive call
		$files = array_merge($files, $arr); // Merge array with files from subdirectory
	}

	return $files;
}
//***********************************************************************************

?>
