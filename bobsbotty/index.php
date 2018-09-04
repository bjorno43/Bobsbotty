<?php

require_once '../settings.php';

?>

<!DOCTYPE html>
<html>
	<head>
		<meta charset="utf-8"/>
		<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
		
		<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet"/>
		<link type="text/css" rel="stylesheet" href="css/materialize.min.css"  media="screen,projection"/>

		<style>
		.editor {
			width: 500px;
			height: 300px;
		}
		</style>

		<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
		<script type="text/javascript">
		let showExample, addTest;
		let num = 2;
		$(document).ready(function(){
			let editor = ace.edit("solution");
			editor.session.setMode("ace/mode/javascript");

			const modalElems = document.querySelectorAll('.modal');
			const modalInstances = M.Modal.init(modalElems);

			showExample = function(example){
				let txt = "";
				switch(example){
					case 'desc':
						txt = "Create a function that takes a string as a parameter and returns the longest word in that string.";
						break;
					case 'cond':
						txt = "- The name of the function must be: `longestWord`.<br/>- In case multiple words are the longest, the last one must be returned.";
						break;
					case 'example':
						txt = "`longestWord('Welcome to FCC!\');` should return `Welcome`.";
						break;
					case 'tests':
						txt = "longestWord('This is a test');";
						break;
					default:
						txt = "Unknown function call";
				}

				document.getElementById('example-text').innerHTML = txt;
				modalInstances[0].open();
			}

			addTest = function(){
				const ele = $("#input-tests");

				ele.append(
					'<div class="input-field col s5">'+
						'<i class="material-icons prefix">mode_edit</i>'+
						'<input id="test-'+num+'" type="text" name="tests[]" class="validate">'+
						'<label for="test-'+num+'">Test</label>'+
					'</div>'+
					'<div class="input-field col s5 offset-s1">'+
						'<i class="material-icons prefix">mode_edit</i>'+
						'<input id="out-'+num+'" type="text" name="outs[]" class="validate">'+
						'<label for="out-'+num+'">Expected result</label>'+
					'</div>'
				);
				num++;
			}

			$("#challenge").on("submit", function(e){
				e.preventDefault();

				let formData = new FormData(this);
				formData.append('solution', editor.getValue());
								
				const titleEle = $("#title");
				const textEle = $("#result-text");

				let result;

				$.ajax({
					url: 'process.php',
					data: formData,
					processData: false,
					contentType: false,
					type: 'POST',
					success: function(data){
						console.log(data);
						result = JSON.parse(data);
						
						if(result[0] !== 'success'){
							titleEle.html("Error");
							textEle.html("");
							for(let i = 0; i < result.length; i++){
								textEle.html(textEle.html() + '<br/>' + result[i]);
							}
							modalInstances[1].open();
							grecaptcha.reset();
						} else {
							titleEle.html("Succes!");
							textEle.html("Your challenge was succesfully added to the database.<br/>Click <a href='status.php'>here</a> to keep an eye on the status of your challenge.<br/>Once verified, your challenge will be added to "+ <?php echo BOTUSER; ?> +". Thank you for your contribution!");
							modalInstances[1].open();
						}
					}
				});
			});
		});
		</script>

		<title><?php echo BOTUSER; ?> - Submit a challenge!</title>
	</head>

	<body>
		<div class="container">
			<div class="row">
				<form class="col s12" id="challenge" method="POST">
					<div class="row">
						<div class="col s12">
							<h5>Name</h5>
							<p>Give your challenge a name. The name will not be displayed with your challenge. This is just for you to make it easy to check your challenge's current status after submission.</p>
						</div>
						<div class="input-field col s12">
							<i class="material-icons prefix">mode_edit</i>
							<input id="name" name="name" type="text" class="validate">
							<label for="name">Name</label>
						</div>
						<div class="col s12">
							<h5>Description</h5>
							<p>Write a description of your challenge. Make sure it's as clear as possible, but don't add more details than absolutely nessesary!</p>
						</div>
						<div class="input-field col s11">
							<i class="material-icons prefix">mode_edit</i>
							<textarea id="desc" name="desc" class="materialize-textarea"></textarea>
							<label for="desc">Description</label>
						</div>
						<div class="col s1">
							<div data-target="modal-example" class="waves-effect waves-light btn modal-trigger" onclick="showExample('desc');">
								<span>Example</span>
							</div>
						</div>
						<div class="col s12">
							<h5>Conditions</h5>
							<p>Here you'll add the conditions for your challenge. Write each condition on a new line. Conditions should at least include:</p>
							<ul>
								<li>- The name of the function to use</li>
							</ul>
						</div>
						<div class="input-field col s11">
							<i class="material-icons prefix">mode_edit</i>
							<textarea id="cond" name="cond" class="materialize-textarea"></textarea>
							<label for="cond">Conditions</label>
						</div>
						<div class="col s1">
							<div data-target="modal-example" class="waves-effect waves-light btn modal-trigger" onclick="showExample('cond');">
								<span>Example</span>
							</div>
						</div>
						<div class="col s12">
							<h5>Example</h5>
							<p>Add an example of how your tests look like and what it should return. For example:</p>
							<ul>
								<li>`yourFunction(parameter);` should return: `result`.</li>
							</ul>
							<p>Be sure to include the ` ! If you don't, <?php echo BOTUSER; ?> will send your example as normal text to Gitter chat, which might be unclear for those trying to solve your challenge.</p>
						</div>
						<div class="input-field col s11">
							<i class="material-icons prefix">mode_edit</i>
							<textarea id="example" name="example" class="materialize-textarea"></textarea>
							<label for="example">Example</label>
						</div>
						<div class="col s1">
							<div data-target="modal-example" class="waves-effect waves-light btn modal-trigger" onclick="showExample('example');">
								<span>Example</span>
							</div>
						</div>
						<div class="col s12">
							<h5>Tests</h5>
							<p>Add each of your tests here. Make sure that each test is valid Javascript syntax and ends with a semicolon (;)! Otherwise the server will return a Javascript error and it might not be clear that it's one of your tests that caused it. Your challenge should at least have 5 tests.</p>
							<p>If your challenge returns an array, make sure to add a condition explaining that the result must be JSON stringified! Put the array in the expected result like:</p>
							<ul>
								<li>['result-1','result-2']</li>
							</ul>
						</div>
						<div id="input-tests">
							<div class="input-field col s5">
								<i class="material-icons prefix">mode_edit</i>
								<input id="test-1" type="text" name="tests[]" class="validate">
								<label for="test-1">Test</label>
							</div>
							<div class="col s1">
								<div data-target="modal-example" class="waves-effect waves-light btn modal-trigger" onclick="showExample('tests');">
									<span>Example</span>
								</div>
							</div>
							<div class="input-field col s5">
								<i class="material-icons prefix">mode_edit</i>
								<input id="out-1" type="text" name="outs[]" class="validate">
								<label for="out-1">Expected result</label>
							</div>
							<div class="col s1">
								<div class="btn" onclick="addTest();">
									<span>+</span>
								</div>
							</div>
						</div>
						<div class="col s12">
							<h5>Solution</h5>
							<p>Lastly, add a possible solution to your challenge. This solution will only be used to verify there's a working solution to your challenge. In case no one has been able to solve your challenge, a Moderator is able to display this solution and allow <?php echo BOTUSER; ?> to load a new challenge. Make sure there are no syntax errors before submitting!</p>
						</div>
						<div class="col s12">
							<div id="solution" class="editor"></div>
						</div>
						<div class="col s12">
							<p>Please verify that you're a human being.</p>
							<div class="g-recaptcha" data-sitekey="6LeEFm0UAAAAABVBVkMn5ecPxASkBQIsla7WqRd1"></div>
						</div>
						<div class="col s12">
							<button class="btn waves-effect waves-light" type="submit" name="action">Submit
								<i class="material-icons right">send</i>
							</button>
						</div>
					</div>
				</form>
			</div>
		</div>
		<div id="modal-example" class="modal">
			<div class="modal-content">
				<h4>Example</h4>
				<p id="example-text"></p>
			</div>
			<div class="modal-footer">
				<a href="#" class="modal-close waves-effect waves-green btn-flat">Ok</a>
			</div>
		</div>
		<div id="modal-result" class="modal">
			<div class="modal-content">
				<h4 id="title"></h4>
				<p id="result-text"></p>
			</div>
			<div class="modal-footer">
				<a href="#" class="modal-close waves-effect waves-green btn-flat">Ok</a>
			</div>
		</div>

		<script type="text/javascript" src="js/materialize.min.js"></script>
		<script src="js/ace.js" type="text/javascript" charset="utf-8"></script>
		<script src="js/mode-javascript.js" type="text/javascript" charset="utf-8"></script>
		<script src="https://www.google.com/recaptcha/api.js"></script>
	</body>
</html>