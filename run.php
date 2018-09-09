<?php

/* Allow running from CLI only */
if (php_sapi_name() != "cli") {
	http_response_code(404);
	exit();
}

/* Requirements */
require_once './settings.php';
require_once './classes/pdo.class.php';
require __DIR__ . '/vendor/autoload.php';

/* Gitter Client */
use Gitter\Client;

/* Initial vars */
$observers = array();
$roomIds = array();
$challengeIds = array();
$currentRoom = 0;
$lastMsg = '';
$msgRepeats = 0;

/* Instantiate objects */
$db = new Database;
$client = new Client(TOKEN);

/* Handles most DB queries */
function dbQuery($type, $query, $data = array(), $db){

	// Selecting a single row
	if($type === 'single'){
		$db->query($query);

		foreach($data as $bind){
			$db->bind($bind[0], $bind[1]);
		}

		try {
			return $db->single();
		} catch(PDOException $e){
			file_put_contents('dbErrors.txt', file_get_contents('dbErrors.txt') . "\n" . $e->getMessage());
			return "Database error. @".BOTOWNER." has been informed about this issue.";
		}

	// Selecting multiple rows
	} else if($type === 'resultset'){
		$db->query($query);

		foreach($data as $bind){
			$db->bind($bind[0], $bind[1]);
		}

		try {
			return $db->resultset();
		} catch(PDOException $e){
			file_put_contents('dbErrors.txt', file_get_contents('dbErrors.txt') . "\n" . $e->getMessage());
			return "Database error. @".BOTOWNER." has been informed about this issue.";
		}

	// Inserting or update a row
	} else if($type === 'insert' || $type === 'update'){
		$db->query($query);

		foreach($data as $bind){
			$db->bind($bind[0], $bind[1]);
		}

		try {
			$db->execute();
			return 'success';
		} catch(PDOException $e){
			file_put_contents('dbErrors.txt', file_get_contents('dbErrors.txt') . "\n" . $e->getMessage());
			return "Database error. @".BOTOWNER." has been informed about this issue.";
		}
	}
}

$spamPrevention = function($msg) use (&$lastMsg, &$msgRepeats){
	if($msg === $lastMsg){
		if($msgRepeats > 1){
			$msgRepeats = 0;
			return " Sending random number to prevent SPAM detection after repeating last message more than 3 times: ". rand(1000, 10000) .".\n";
		} else {
			$msgRepeats++;
			return '';
		}
	} else {
		$lastMsg = $msg;
		$msgRepeats = 0;
		return '';
	}
};

/* Get messages and id's for each room */
foreach ($client->rooms as $room) {
	array_push($observers, $client->rooms->messages($room['id']));
	array_push($roomIds, $room['id']);
}

/* Create observer for each room */
foreach ($observers as $observer) {
	$observer->subscribe(function ($message) use ($client, $roomIds, $currentRoom, &$challengeIds, &$db, &$spamPrevention){
		// Uncomment to enable message debugger
		//file_put_contents('messages.txt', file_get_contents('messages.txt') . "\n" . print_r($message, true));

		// Verify bot being mentioned first by user
		if(!empty($message['mentions'][0]) && $message['mentions'][0]['screenName'] === BOTUSER){

			$msg = explode(' ', $message['text'], 2);
			$msg[1] = trim($msg[1]);

			// Command 'help' received
			if(preg_match("/^help$/i", $msg[1])){
				$query = "SELECT msg FROM messages WHERE type = :type";
				$data = array(array(":type","help"));
				$result = dbQuery('single', $query, $data, $db);

				// Spam prevention
				$spam = $spamPrevention($result['msg']);

				$client->messages->create($roomIds[$currentRoom],
					$spam.
					"@".$message['fromUser']['username'].
					" ".$result['msg']
				);
			
			// Command 'help javascript' received
			} else if(preg_match("/^help javascript$/i", $msg[1])){
				$query = "SELECT msg FROM messages WHERE type = :type";
				$data = array(array(":type","help javascript"));
				$result = dbQuery('single', $query, $data, $db);

				// Spam prevention
				$spam = $spamPrevention($result['msg']);

				$client->messages->create($roomIds[$currentRoom],
					$spam.
					"@".$message['fromUser']['username'].
					" ".$result['msg']
				);

			// Command 'help challenges' received
			} else if(preg_match("/^help challenges$/i", $msg[1])){
				if($roomIds[$currentRoom] == '5b903b77d73408ce4fa70b9c'){
					$query = "SELECT msg FROM messages WHERE type = :type";
					$data = array(array(":type","help challenges"));
					$result = dbQuery('single', $query, $data, $db);

					// Spam prevention
					$spam = $spamPrevention($result['msg']);

					$client->messages->create($roomIds[$currentRoom],
						$spam.
						"@".$message['fromUser']['username'].
						" ".$result['msg']
					);
				} else {
					$query = "SELECT msg FROM messages WHERE type = :type";
					$data = array(array(":type","help challenges wrong room"));
					$result = dbQuery('single', $query, $data, $db);

					// Spam prevention
					$spam = $spamPrevention($result['msg']);

					$client->messages->create($roomIds[$currentRoom],
						$spam.
						"@".$message['fromUser']['username'].
						" ".$result['msg']
					);
				}

			// Command 'challenge' received
			} else if(preg_match("/^challenge$/i", $msg[1])){

				// Make sure user is in the correct room
				if($roomIds[$currentRoom] == CHALLENGEROOM){
					$query = "SELECT description, conditions, example FROM challenges WHERE current = 1";
					$result = dbQuery('single', $query, array(), $db);

					$msg = " The current challenge is:\n".
						$result['description']."\n\n".
						"Rules:\n".
						$result['conditions']."\n\n".
						"Example:\n".
						$result['example'];

					// Spam prevention
					$spam = $spamPrevention($msg);

					// Return current challenge to user
					$client->messages->create($roomIds[$currentRoom],
						$spam.
						"@".$message['fromUser']['username'].
						$msg
					);

				// User is in the wrong room
				} else {
					$query = "SELECT msg FROM messages WHERE type = :type";
					$data = array(array(":type","challenges cmd wrong room"));
					$result = dbQuery('single', $query, $data, $db);

					// Spam prevention
					$spam = $spamPrevention($result['msg']);

					$client->messages->create($roomIds[$currentRoom],
						$spam.
						"@".$message['fromUser']['username'].
						" ".$result['msg']
					);
				}

			// Command 'solve' received
			} else if(preg_match("/^solve/i", $msg[1])){

				// Make sure user is in the correct room
				if($roomIds[$currentRoom] == CHALLENGEROOM){

					// Verify user is providing a solution
					if(preg_match("/```(.*)```/s", $message['text'], $result)){

						// Sandbox user provided code
						$js = <<<EOT
$result[1]
EOT;

						// Select current challenge's tests from database
						$query = "SELECT id, tests, results FROM challenges WHERE current = 1";
						$result = dbQuery('single', $query, array(), $db);
						$challengeID = $result['id'];

						// Parse tests and their expected results into arrays
						$tests = explode("\n", $result['tests']);
						$results = explode("\n", $result['results']);

						// Assume user provided correct solution and no JS errors were present
						$solved = true;
						$error = false;
						
						// Placeholder for wrong test results
						$testResults = "";

						// Run tests against user provided solution
						for($i = 0; $i < count($tests); $i++){
							$run = $js.PHP_EOL.$tests[$i];

							// Instantiate V8Js engine
							$v8 = new V8Js();
							$v8->setTimeLimit(10000);
							$v8->setMemoryLimit(10240000);

							try {
								$output = $v8->executeString($run);

								// JSON encode if result is object
								if(is_object($output)){
									$output = json_encode($output);
								}

								// Test failed
								if($output != $results[$i]){
									if(is_bool($output)){
										if($output){ $output = 'true'; } else { $output = 'false'; }
									}
									$testResults .= "Test `".$tests[$i]."` should return `".$results[$i]."`. Instead got: `".$output."` .\n";
									$solved = false;
								}

							// JS error in user provided code solution
							} catch (V8JsException $e) {
								$output = $e->getMessage();

								$msg = " There was an error with your Javascript code:\n".
									"```\n".
									$output."\n".
									"```";

								// Spam prevention
								$spam = $spamPrevention($msg);

								$client->messages->create($roomIds[$currentRoom],
									$spam.
									"@".$message['fromUser']['username'].
									$msg
								);

								$solved = false;
								$error = true;
								break;
							}

							unset($v8);
						}

						// All tests succeeded
						if($solved){

							// Add challenge to challenges array so it doesn't get selected again
							array_push($challengeIds, $result['id']);

							// Verify if user already solved the current challenge in the past
							$query = "SELECT id FROM solved WHERE username = :user AND challengeid = :id";
							$data = array(
								array(":user", $message['fromUser']['username']),
								array(":id", $result['id'])
							);
							$result = dbQuery('single', $query, $data, $db);

							// User solved the challenge in the past
							if($db->rowCount() > 0){
								$query = "SELECT msg FROM messages WHERE type = :type";
								$data = array(array(":type","solved no points"));
								$result = dbQuery('single', $query, $data, $db);

								// Spam prevention
								$spam = $spamPrevention($result['msg']);

								$client->messages->create($roomIds[$currentRoom],
									$spam.
									"@".$message['fromUser']['username'].
									" ".$result['msg']
								);
							} else {
								
								// Add the user the list of users that has solved the challenge
								$query = "INSERT INTO solved (`challengeid`,`username`) VALUES (:id, :user)";
								$data = array(
									array(":id", $challengeID),
									array(":user", $message['fromUser']['username'])
								);
								$result = dbQuery('insert', $query, $data, $db);

								if($result !== 'success'){
									
									// Spam prevention
									$spam = $spamPrevention($result);

									$client->messages->create($roomIds[$currentRoom],
										$spam.
										"@".$message['fromUser']['username'].
										" ".$result
									);
								}

								$query = "SELECT msg FROM messages WHERE type = :type";
								$data = array(array(":type","solved"));
								$result = dbQuery('single', $query, $data, $db);

								// Spam prevention
								$spam = $spamPrevention($result['msg']);

								$client->messages->create($roomIds[$currentRoom],
									$spam.
									"@".$message['fromUser']['username'].
									" ".$result['msg']
								);

								$query = "INSERT INTO users (`username`,`points`) VALUES (:user, 1) ON DUPLICATE KEY UPDATE points = points + 1";
								$data = array(array(":user", $message['fromUser']['username']));
								$result = dbQuery('insert', $query, $data, $db);

								if($result !== 'success'){
									
									// Spam prevention
									$spam = $spamPrevention($result);

									$client->messages->create($roomIds[$currentRoom],
										$spam.
										"@".$message['fromUser']['username'].
										" ".$result
									);
								}
							}

							// Remove the current status from the challenge
							$query = "UPDATE challenges SET solved = solved + 1, current = 0 WHERE current = 1";
							$result = dbQuery('update', $query, array(), $db);

							if($result !== 'success'){
									
								// Spam prevention
								$spam = $spamPrevention($result);

								$client->messages->create($roomIds[$currentRoom],
									$spam.
									"@".$message['fromUser']['username'].
									" ".$result
								);
							}

							// Select a random new challenge
							$query = "SELECT id FROM challenges WHERE id NOT IN (:challenges) AND status = 2 ORDER BY RAND() LIMIT 1";
							//print_r($challengeIds);
							$data = array(array(":challenges", implode(',', $challengeIds)));
							$result = dbQuery('single', $query, $data, $db);
							//echo $result['id'];

							if($db->rowCount() > 0){

								// Set the random selected challenge to current
								$query = "UPDATE challenges SET current = 1 WHERE id = :id";
								$data = array(array(":id", $result['id']));
								$result = dbQuery('update', $query, $data, $db);

								if($result !== 'success'){
									
									// Spam prevention
									$spam = $spamPrevention($result);

									$client->messages->create($roomIds[$currentRoom],
										$spam.
										"@".$message['fromUser']['username'].
										" ".$result
									);
								}
							} else {
								$challengeIds = array();

								// Select a random new challenge
								$query = "SELECT id FROM challenges WHERE id NOT IN (:challenges) AND status = 2 ORDER BY RAND() LIMIT 1";
								$data = array(array(":challenges", implode(',', $challengeIds)));
								$result = dbQuery('single', $query, array(), $db);

								// Set the random selected challenge to current
								$query = "UPDATE challenges SET current = 1 WHERE id = :id";
								$data = array(array(":id", $result['id']));
								$result = dbQuery('update', $query, $data, $db);

								if($result !== 'success'){
									
									// Spam prevention
									$spam = $spamPrevention($result);

									$client->messages->create($roomIds[$currentRoom],
										$spam.
										"@".$message['fromUser']['username'].
										" ".$result
									);
								}
							}
						} else if(!$error) {
							$msg = $testResults."Please try again!";

							// Spam prevention
							$spam = $spamPrevention($msg);

							$client->messages->create($roomIds[$currentRoom],
								$spam.
								"@".$message['fromUser']['username'].
								" ".$msg
							);
						}

					// User did not provide a solution
					} else {
						$query = "SELECT msg FROM messages WHERE type = :type";
						$data = array(array(":type","no solution"));
						$result = dbQuery('single', $query, $data, $db);

						// Spam prevention
						$spam = $spamPrevention($result['msg']);

						$client->messages->create($roomIds[$currentRoom],
							$spam.
							"@".$message['fromUser']['username'].
							" ".$result['msg']
						);
					}
				} else {
					$query = "SELECT msg FROM messages WHERE type = :type";
					$data = array(array(":type","challenges cmd wrong room"));
					$result = dbQuery('single', $query, $data, $db);

					// Spam prevention
					$spam = $spamPrevention($result['msg']);

					$client->messages->create($roomIds[$currentRoom],
						$spam.
						"@".$message['fromUser']['username'].
						" ".$result['msg']
					);
				}

			// Command 'ranks' received
			} else if(preg_match("/^ranks/i", $msg[1])){

				// Make sure user is in the correct room
				if($roomIds[$currentRoom] == CHALLENGEROOM){
					$query = "SELECT username, points FROM users ORDER BY points DESC";
					$results = dbQuery('resultset', $query, array(), $db);

					if($db->rowCount() > 0){
						$list = "";
						$place = 1;
						$points = 0;
						$firstLoop = true;

						foreach($results as $result){
							if($firstLoop){
								$points = $result['points'];
								$firstLoop = false;

								$list .= $place .". ". $result['username'] ." with ". $result['points'] ." points!\n";
								continue;
							} else {

								if($result['points'] == $points){
									$list .= $result['username'] ." with ". $result['points'] ." points!\n";
								} else if($place < 5){
									$points = $result['points'];
									$place++;
									$list .= $place .". ". $result['username'] ." with ". $result['points'] ." points!\n";
								} else {
									break;
								}
							}
						}

						// Spam prevention
						$spam = $spamPrevention($list);

						$client->messages->create($roomIds[$currentRoom], $spam."@".$message['fromUser']['username']." \n".$list);
					} else {
						$msg = "No one has received any points yet!";

						// Spam prevention
						$spam = $spamPrevention($msg);

						$client->messages->create($roomIds[$currentRoom],
							$spam.
							"@".$message['fromUser']['username'].
							" ".$msg
						);
					}
				} else {
					$query = "SELECT msg FROM messages WHERE type = :type";
					$data = array(array(":type","challenges cmd wrong room"));
					$result = dbQuery('single', $query, $data, $db);

					// Spam prevention
					$spam = $spamPrevention($result['msg']);

					$client->messages->create($roomIds[$currentRoom],
						$spam.
						"@".$message['fromUser']['username'].
						" ".$result['msg']
					);
				}

			// Command 'points' received
			} else if(preg_match("/^points/i", $msg[1])){

				// Make sure user is in the correct room
				if($roomIds[$currentRoom] == CHALLENGEROOM){

					$query = "SELECT points FROM users WHERE username = :user";
					$data = array(array(":user", $message['fromUser']['username']));
					$result = dbQuery('single', $query, $data, $db);

					if($db->rowCount() > 0){
						$msg = "You currently have: ". $result['points'] ." points!";

						// Spam prevention
						$spam = $spamPrevention($msg);

						$client->messages->create($roomIds[$currentRoom],
							$spam.
							"@".$message['fromUser']['username'].
							" ".$msg
						);
					} else {
						$query = "SELECT msg FROM messages WHERE type = :type";
						$data = array(array(":type","no points"));
						$result = dbQuery('single', $query, $data, $db);

						// Spam prevention
						$spam = $spamPrevention($result['msg']);

						$client->messages->create($roomIds[$currentRoom],
							$spam.
							"@".$message['fromUser']['username'].
							" ".$result['msg']
						);
					}
				} else {
					$query = "SELECT msg FROM messages WHERE type = :type";
					$data = array(array(":type","challenges cmd wrong room"));
					$result = dbQuery('single', $query, $data, $db);

					// Spam prevention
					$spam = $spamPrevention($result['msg']);

					$client->messages->create($roomIds[$currentRoom],
						$spam.
						"@".$message['fromUser']['username'].
						" ".$result['msg']
					);
				}

			// Admin Command 'skip challenge' recieved
			} else if(preg_match("/^skip challenge/i", $msg[1])){

				// Verify Admin
				if($message['fromUser']['username'] == BOTOWNER){

					$query = "SELECT id FROM challenges WHERE current = 1";
					$result = dbQuery('single', $query, array(), $db);

					// Add challenge to challenges array so it doesn't get selected again
					array_push($challengeIds, $result['id']);

					// Remove the current status from the challenge
					$query = "UPDATE challenges SET current = 0 WHERE current = 1";
					$result = dbQuery('update', $query, array(), $db);

					if($result !== 'success'){
									
						// Spam prevention
						$spam = $spamPrevention($result);

						$client->messages->create($roomIds[$currentRoom],
							$spam.
							"@".$message['fromUser']['username'].
							" ".$result
						);
					}

					// Select a random new challenge
					$query = "SELECT id FROM challenges WHERE id NOT IN (:challenges) AND status = 2 ORDER BY RAND() LIMIT 1";
					$data = array(array(":challenges", implode(',', $challengeIds)));
					$result = dbQuery('single', $query, array(), $db);

					if($db->rowCount() > 0){

						// Set the random selected challenge to current
						$query = "UPDATE challenges SET current = 1 WHERE id = :id";
						$data = array(array(":id", $result['id']));
						$result = dbQuery('update', $query, $data, $db);

						if($result !== 'success'){
							
							// Spam prevention
							$spam = $spamPrevention($result);

							$client->messages->create($roomIds[$currentRoom],
								$spam.
								"@".$message['fromUser']['username'].
								" ".$result
							);
						}
					} else {
						$challengeIds = array();

						// Select a random new challenge
						$query = "SELECT id FROM challenges WHERE id NOT IN (:challenges) AND status = 2 ORDER BY RAND() LIMIT 1";
						$data = array(array(":challenges", implode(',', $challengeIds)));
						$result = dbQuery('single', $query, array(), $db);

						// Set the random selected challenge to current
						$query = "UPDATE challenges SET current = 1 WHERE id = :id";
						$data = array(array(":id", $result['id']));
						$result = dbQuery('update', $query, $data, $db);

						if($result !== 'success'){
							
							// Spam prevention
							$spam = $spamPrevention($result);

							$client->messages->create($roomIds[$currentRoom],
								$spam.
								"@".$message['fromUser']['username'].
								" ".$result
							);
						}
					}

					$msg = "The current challenge has been skipped. A new random challenge is selected.\nMessage \"challenge\" to me to see the new challenge!";

					// Spam prevention
					$spam = $spamPrevention($msg);

					$client->messages->create($roomIds[$currentRoom],
						$spam.
						"@".$message['fromUser']['username'].
						" ".$msg
					);
				}

			// Admin Command 'stop' received
			} else if(preg_match("/^stop/i", $msg[1])){

				// Verify Admin
				if($message['fromUser']['username'] == BOTOWNER){
				
					$client->messages->create($roomIds[$currentRoom],
						"@".$message['fromUser']['username'].
						" ". BOTUSER ." shut down succesfully!"
					);
					exit;
				}

			// No command received. User is trying to run JS code
			} else if(preg_match("/```(.*)```/s", $msg[1], $code)){

				// Sandbox user provided code
				$js = <<<EOT
$code[1]
EOT;

				// Instantiate V8Js engine
				$v8 = new V8Js();
				$v8->setTimeLimit(10000);
				$v8->setMemoryLimit(10240000);

				try {
					$output = $v8->executeString($js);

					if(is_object($output)){
						$output = json_encode($output);
					} else if(is_array($output)){
						$output = print_r($output, true);
					}

					if(strlen($output) > 400){
						$query = "SELECT msg FROM messages WHERE type = :type";
						$data = array(array(":type","javascript too long"));
						$result = dbQuery('single', $query, $data, $db);

						// Spam prevention
						$spam = $spamPrevention($result['msg']);

						$client->messages->create($roomIds[$currentRoom],
							$spam.
							"@".$message['fromUser']['username'].
							" ".$result['msg']
						);
					} else {
						$msg = "The result of your Javascript code is:\n```\n".$output."\n```";

						// Spam prevention
						$spam = $spamPrevention($msg);

						$client->messages->create($roomIds[$currentRoom],
							$spam.
							"@".$message['fromUser']['username'].
							" ".$msg
						);
					}
				
				// JS error in user provided code
				} catch (V8JsException $e) {
					$output = $e->getMessage();

					$msg = "There was an error with your Javascript code:\n```\n".$output."\n```";

					// Spam prevention
					$spam = $spamPrevention($msg);

					$client->messages->create($roomIds[$currentRoom],
						$spam.
						"@".$message['fromUser']['username'].
						" ".$msg
					);
				}

				unset($v8);
			}

			// Mark instantiated objects for the garbage collector
			unset($db);
		}
	});
	$currentRoom++;
}

// Connect to stream!
$client->connect();
