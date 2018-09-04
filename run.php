<?php

if (php_sapi_name() != "cli") {
	http_response_code(404);
	exit();
}

require_once 'settings.php';
require_once 'pdo.class.php';
require __DIR__ . '/vendor/autoload.php';

use Gitter\Client;

/* Instantiate Gitter API client */
$client = new Client(TOKEN);

$observers = array();
$roomIds = array();
$currentRoom = 0;

/* Get messages and id's for each room */
foreach ($client->rooms as $room) {
	array_push($observers, $client->rooms->messages($room['id']));
	array_push($roomIds, $room['id']);
}

/* Create observer for each room */
foreach ($observers as $observer) {
	$observer->subscribe(function ($message) use ($client, $roomIds, $currentRoom){
		// Uncomment to enable message debugger
		//file_put_contents('lastMsg.txt', print_r($message, true));
		
		// Verify bot being mentioned first by user
		if(!empty($message['mentions'][0]) && $message['mentions'][0]['screenName'] === BOTUSER){

			// Instantiate V8Js engine
			$v8 = new V8Js();
			$v8->setTimeLimit(10000);
			$v8->setMemoryLimit(10240000);

			// Instantiate database
			$db = new Database;

			$msg = explode(' ', $message['text'], 2);
			$msg[1] = trim($msg[1]);

			// Command 'help' received
			if(preg_match("/^help$/i", $msg[1])){
				$client->messages->create($roomIds[$currentRoom],
					"@".$message['fromUser']['username'].
					" Available functions:\n".
					"- Parse Javascript code and output result (command: help javascript)\n".
					"- Do Javascript challenges (command: help challenges)"
				);
			
			// Command 'help javascript' received
			} else if(preg_match("/^help javascript$/i", $msg[1])){
				$client->messages->create($roomIds[$currentRoom],
					"@".$message['fromUser']['username'].
					" Put your Javascript code between 3 backticks and I will return you its result.\n".
					"Do NOT use `console.log()`. Instead, call the result directly, like:\n".
					"```\n".
					"let example = 'Hello World!';\n".
					"example;\n".
					"```\n".
					"Use `JSON.stringify()` or `array.toString()` to output arrays.\n\n".

					"Current limitations:\n".
					"- I can only output 1 result at a time. (Don't output results in a loop as it will only return the last loop)\n".
					"- My time limit is set to 10 seconds. Scripts taking more time will be killed\n".
					"- My memory limit is set to 10 MegaBytes. Scripts taking more memory will be killed"
				);

			// Command 'help challenges' received
			} else if(preg_match("/^help challenges$/i", $msg[1])){
				$client->messages->create($roomIds[$currentRoom],
					"@".$message['fromUser']['username'].
					" Similar to [Codewars](https://www.codewars.com), I can provide Javascript challenges which you need to solve.\n".
					"To see the current challenge (command: challenge)\n".
					"To input your solution (command: solve) followed by your code between 3 backticks\n".
					"To see the ranks (command: ranks)\n".
					"To see how many points you've earned (command: points)\n\n".

					"If you wish to contribute your own challenge, please go to [". BOTURL ."](". BOTURL .") and follow the instructions"
				);

			// Command 'challenge' received
			} else if(preg_match("/^challenge$/i", $msg[1])){

				// Select current challenge from database
				$db->query("SELECT description, conditions, example FROM challenges WHERE current = 1");

				try {
					$dbresult = $db->single();
				} catch(PDOException $e){
					echo "PDO Error: ". $e->getMessage();
					$client->messages->create($roomIds[$currentRoom],
						"@".$message['fromUser']['username'].
						" There was an error with my database. @".BOTOWNER." has been informed about this issue.\n".
						"To prevent further issues I will now shut down."
					);
					exit;
				}

				// Return current challenge to user
				$client->messages->create($roomIds[$currentRoom],
					"@".$message['fromUser']['username'].
					" The current challenge is:\n".
					$dbresult['description']."\n\n".
					"Rules:\n".
					$dbresult['conditions']."\n\n".
					"Example:\n".
					$dbresult['example']
				);
			
			// Command 'solve' received
			} else if(preg_match("/^solve/i", $msg[1])){

				// Verify user providing a solution
				if(preg_match("/```(.*)```/s", $message['text'], $result)){

					// Sandbox user provided code
					$js = <<<EOT
$result[1]
EOT;

					// Select current challenge's tests from database
					$db->query("SELECT id, tests, results FROM challenges WHERE current = 1");

					try {
						$dbresult = $db->single();
					} catch(PDOException $e){
						echo "PDO Error: ". $e->getMessage();
						$client->messages->create($roomIds[$currentRoom],
							"@".$message['fromUser']['username'].
							" There was an error with my database. @".BOTOWNER." has been informed about this issue.\n".
							"To prevent further issues I will now shut down."
						);
						exit;
					}

					// Parse tests and their expected results into arrays
					$tests = explode("\n", $dbresult['tests']);
					$results = explode("\n", $dbresult['results']);

					// Assume user provided correct solution
					$solved = true;

					// Run tests against user provided solution
					for($i = 0; $i < count($tests); $i++){
						$run = $js.PHP_EOL.$tests[$i];
						try {
							$output = $v8->executeString($run);

							// Test failed
							if($output != $results[$i]){
								$client->messages->create($roomIds[$currentRoom],
									"@".$message['fromUser']['username'].
									" Test `".$tests[$i]."` should return `".$results[$i]."`. Instead got: `".$output."`. Please try again!
								");
								$solved = false;
								break;
							}

						// JS error in user provided code solution
						} catch (V8JsException $e) {
							$output = $e->getMessage();

							$client->messages->create($roomIds[$currentRoom],
								"@".$message['fromUser']['username'].
								" There was an error with your Javascript code:\n".
								"```\n".
								$output."\n".
								"```"
							);
							$solved = false;
							break;
						}
					}

					// All tests succeeded
					if($solved){

						// Verify if user already solved the current challenge in the past
						$db->query("SELECT id FROM solved WHERE username = :user AND challengeid = :id");
						$db->bind(":user", $message['fromUser']['username']);
						$db->bind(":id", $dbresult['id']);

						try {
							$dbUserResult = $db->single();
						} catch(PDOException $e){
							echo "PDO Error: ". $e->getMessage();
							$client->messages->create($roomIds[$currentRoom],
								"@".$message['fromUser']['username'].
								" There was an error with my database. @".BOTOWNER." has been informed about this issue.\n".
								"To prevent further issues I will now shut down."
							);
							exit;
						}

						// User solved the challenge in the past
						if($db->rowCount() > 0){
							// Congratulate user
							$client->messages->create($roomIds[$currentRoom],
								"@".$message['fromUser']['username'].
								" Congratulations! Your solution is correct!\n".
								"You were not granted any points for this solution because you've already solved this challenge before."
							);
						} else {
							// Congratulate user
							$client->messages->create($roomIds[$currentRoom],
								"@".$message['fromUser']['username'].
								" Congratulations! Your solution is correct!\n".
								"A new random challenge has been selected. Keep in mind that this could be the same challenge as before. The chance of this happening will decrease as more challenges are added to the database.\n".
								"Message \"challenge\" to me to see the new challenge!"
							);

							// Grant user a point for the correct solution
							$db->query("INSERT INTO users (`username`,`points`) VALUES (:user, 1) ON DUPLICATE KEY UPDATE points = points + 1");
							$db->bind(":user", $message['fromUser']['username']);

							try {
								$db->execute();
							} catch(PDOException $e){
								echo "PDO Error: ". $e->getMessage();
								$client->messages->create($roomIds[$currentRoom],
									"@".$message['fromUser']['username'].
									" There was an error with my database. @".BOTOWNER." has been informed about this issue.\n".
									"To prevent further issues I will now shut down."
								);
								exit;
							}
						}

						// Add the user the list of users that has solved the challenge
						$db->query("INSERT INTO solved (`challengeid`,`username`) VALUES (:id, :user)");
						$db->bind(":id", $dbresult['id']);
						$db->bind(":user", $message['fromUser']['username']);

						try {
							$db->execute();
						} catch(PDOException $e){
							echo "PDO Error: ". $e->getMessage();
							$client->messages->create($roomIds[$currentRoom],
								"@".$message['fromUser']['username'].
								" There was an error with my database. @".BOTOWNER." has been informed about this issue.\n".
								"To prevent further issues I will now shut down."
							);
							exit;
						}

						// Remove the current status from the challenge
						$db->query("UPDATE challenges SET solved = solved + 1, current = 0 WHERE current = 1");

						try {
							$db->execute();
						} catch(PDOException $e){
							echo "PDO Error: ". $e->getMessage();
							$client->messages->create($roomIds[$currentRoom],
								"@".$message['fromUser']['username'].
								" There was an error with my database. @".BOTOWNER." has been informed about this issue.\n".
								"To prevent further issues I will now shut down."
							);
							exit;
						}

						// Select a random new challenge
						$db->query("SELECT id FROM challenges WHERE status = 2 ORDER BY RAND() LIMIT 1");

						try {
							$dbresult = $db->single();
						} catch(PDOException $e){
							echo "PDO Error: ". $e->getMessage();
							$client->messages->create($roomIds[$currentRoom],
								"@".$message['fromUser']['username'].
								" There was an error with my database. @".BOTOWNER." has been informed about this issue.\n".
								"To prevent further issues I will now shut down."
							);
							exit;
						}

						// Set the random selected challenge to current
						$db->query("UPDATE challenges SET current = 1 WHERE id = :id");
						$db->bind(":id", $dbresult['id']);

						try {
							$db->execute();
						} catch(PDOException $e){
							echo "PDO Error: ". $e->getMessage();
							$client->messages->create($roomIds[$currentRoom],
								"@".$message['fromUser']['username'].
								" There was an error with my database. @".BOTOWNER." has been informed about this issue.\n".
								"To prevent further issues I will now shut down."
							);
							exit;
						}
					}

				// User did not provide a solution
				} else {
					$client->messages->create($roomIds[$currentRoom],
						"@".$message['fromUser']['username'].
						" Please provide your solution between 3 backticks."
					);
				}
			
			// Command 'ranks' received
			} else if(preg_match("/^ranks/i", $msg[1])){
				$db->query("SELECT username, points FROM users ORDER BY points DESC LIMIT 5");

				try {
					$dbresults = $db->resultset();
				} catch(PDOException $e){
					echo "PDO Error: ". $e->getMessage();
					$client->messages->create($roomIds[$currentRoom],
						"@".$message['fromUser']['username'].
						" There was an error with my database. @".BOTOWNER." has been informed about this issue.\n".
						"To prevent further issues I will now shut down."
					);
					exit;
				}

				if($db->rowCount() > 0){
					$list = "";
					$place = 1;

					foreach($dbresults as $result){
						$list .= $place .". ". $result['username'] ." with ". $result['points'] ." points!\n";
						$place++;
					}

					$client->messages->create($roomIds[$currentRoom], "@".$message['fromUser']['username']." \n".$list);
				} else {
					$client->messages->create($roomIds[$currentRoom],
						"@".$message['fromUser']['username'].
						" No one has received any points yet!"
					);
				}

			// Command 'points' received
			} else if(preg_match("/^points/i", $msg[1])){
				$db->query("SELECT points FROM users WHERE username = :user");
				$db->bind(":user", $message['fromUser']['username']);

				try {
					$dbresult = $db->single();
				} catch(PDOException $e){
					echo "PDO Error: ". $e->getMessage();
					$client->messages->create($roomIds[$currentRoom],
						"@".$message['fromUser']['username'].
						" There was an error with my database. @".BOTOWNER." has been informed about this issue.\n".
						"To prevent further issues I will now shut down."
					);
					exit;
				}

				if($db->rowCount() > 0){
					$client->messages->create($roomIds[$currentRoom],
						"@".$message['fromUser']['username'].
						" You currently have: ". $dbresult['points'] ." points!"
					);
				} else {
					$client->messages->create($roomIds[$currentRoom],
						"@".$message['fromUser']['username'].
						" You did not receive any points yet. Try being the first to solve a challenge and start earning points!"
					);
				}

			// No command received. User is trying to run JS code
			} else if(preg_match("/```(.*)```/s", $msg[1], $result)){

				// Sandbox user provided code
				$js = <<<EOT
$result[1]
EOT;

				try {
					$output = $v8->executeString($js);

					if(strlen($output) > 200){
						$client->messages->create($roomIds[$currentRoom],
							"@".$message['fromUser']['username'].
							" The result of your Javascript code is too lengthy and would disrupt the room.\n".
							"Please narrow the result down to below 200 characters."
						);
					} else {
						$client->messages->create($roomIds[$currentRoom],
							"@".$message['fromUser']['username'].
							" The result of your Javascript code is:\n".
							"```\n".
							$output."\n".
							"```"
						);
					}
				
				// JS error in user provided code
				} catch (V8JsException $e) {
					$output = $e->getMessage();

					$client->messages->create($roomIds[$currentRoom],
						"@".$message['fromUser']['username'].
						" There was an error with your Javascript code:\n".
						"```\n".
						$output."\n".
						"```"
					);
				}
			}

			// Mark instantiated objects for the garbage collector
			// This is needed because V8Js retains code in memory
			unset($v8);
			unset($db);
		}
	});
	$currentRoom++;
}

// Connect to stream!
$client->connect();
