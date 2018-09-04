<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../settings.php';
require_once '../pdo.class.php';

$db = new Database;

if($_SERVER['REQUEST_METHOD'] === 'POST'){
	$error = false;
	$errors = array();

	$url = 'https://www.google.com/recaptcha/api/siteverify';
	$data = array('secret' => SECRETKEY, 'response' => $_POST['g-recaptcha-response']);

	$options = array(
		'http' => array(
			'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
			'method'  => 'POST',
			'content' => http_build_query($data)
		)
	);

	$context = stream_context_create($options);
	$result = file_get_contents($url, false, $context);

	if ($result === FALSE) {
		$error = true;
		array_push($errors, 'Unable to verify Captcha due to Google servers not responding. Please try again later.');
	}

	if(!$error){
		$result = json_decode($result, true);

		if(!$result['success']){
			$error = true;
			array_push($errors, 'Unable to verify Captcha. Please verify that you\'re a human being.');
		}

		if(!$error){
			if(!empty($_POST['name']) && !empty($_POST['desc']) && !empty($_POST['cond']) && !empty($_POST['example']) && !empty($_POST['tests']) && !empty($_POST['solution'])){
				$db->query("SELECT id FROM challenges WHERE name = :name");
				$db->bind(":name", $_POST['name']);

				try {
					$dbresult = $db->single();
				} catch(PDOException $e){
					$error = true;
					array_push($errors, "There was a database error: ".$e->getMessage());
				}

				if($db->rowCount() > 0){
					$error = true;
					array_push($errors, 'There\'s already a challenge with that exact name. Most likely your challenge has already been added to '. BOTUSER .'. If you\'re sure this is not the case, or want us to verify it for you, please change the name to something else.');
				}
				if(strlen($_POST['name'] < 3) && strlen($_POST['name'] > 250)){
					$error = true;
					array_push($errors, 'The challenge\'s name cannot be shorter than 3, or longer than 250 characters.');
				}
				if(!preg_match("/^[0-9a-zA-Z_\- ]+$/i", $_POST['name'])){
					$error = true;
					array_push($errors, 'The challenge\'s name may only contain letters, numbers, spaces, underscores and hyphens.');
				}
				if(strlen($_POST['desc']) < 5){
					$error = true;
					array_push($errors, 'Your description is too short. Please make sure that your description provides enough information for others to be able to solve it!');
				}
				if(!substr('- ', 0, 2)){
					$error = true;
					array_push($errors, 'Please make sure each condition starts with a hyphen followed by a space character. Your challenge should always include at least 1 condition defining which function name to use.');
				}

				if(!$error){

					if(count($_POST['tests']) < 5){
						$error = true;
						array_push($errors, 'Please add at least 5 tests to your challenge to prevent people from cheating.');
					}

					if(!$error){

						for($i = 0; $i < count($_POST['tests']); $i++){
							if(!empty($_POST['tests'][$i]) && $_POST['outs'][$i] === ''){
								$error = true;
								$testNr = $i+1;
								array_push($errors, 'Test: '. $testNr .' did not include a result. Please add the result this test is supposed to return.');
							}
						}

						if(!$error){
							$v8 = new V8Js();
							$v8->setTimeLimit(10000);
							$v8->setMemoryLimit(10240000);

							$solution = $_POST['solution'];

							$js = <<<EOT
$solution
EOT;
							for($i = 0; $i < count($_POST['tests']); $i++){
								$run = $js.PHP_EOL.$_POST['tests'][$i];
								try {
									$output = $v8->executeString($run);

									// Test failed
									if($output != $_POST['outs'][$i]){
										$error = true;
										array_push($errors, $_POST['tests'][$i] . ' - This test failed against your own solution. Expected output: '. $_POST['outs'][$i] .'. Instead got: '. $output .'. Please correct any mistakes you\'ve made and try again.');
									}
								} catch (V8JsException $e) {
									$output = $e->getMessage();
									$error = true;
									array_push($errors, 'Your Javascript code produced the following error: '.$output);
									break;
								}
							}

							if(!$error){

								$db->query("INSERT INTO challenges (`name`,`description`,`conditions`,`example`,`tests`,`results`,`solution`) VALUES (:name, :desc, :cond, :example, :tests, :results, :solution)");

								$db->bind(":name", $_POST['name']);
								$db->bind(":desc", $_POST['desc']);
								$db->bind(":cond", $_POST['cond']);
								$db->bind(":example", $_POST['example']);
								$db->bind(":tests", implode("\n", $_POST['tests']));
								$db->bind(":results", implode("\n", $_POST['outs']));
								$db->bind(":solution", $_POST['solution']);

								try {
									$db->execute();
								} catch(PDOException $e){
									$error = true;
									array_push($errors, 'There was an error adding the challenge to the database: '. $e->getMessage());
								}
							}
						}
					}
				}
			} else {
				$error = true;
				array_push($errors, 'Please make sure all required fields are filled.');
			}
		}
	}

	if($error){
		echo json_encode($errors);
	} else {
		$arr = array("success");
		echo json_encode($arr);
	}
}

?>
