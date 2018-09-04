<?php

require_once '../settings.php';
require_once '../pdo.class.php';

$db = new Database;

$db->query("SELECT name, status FROM challenges");

try {
	$rows = $db->resultset();
} catch(PDOException $e){
	die($e->getMessage());
}

?>

<!DOCTYPE html>
<html>
	<head>
		<meta charset="utf-8"/>
		<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
		
		<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet"/>
		<link type="text/css" rel="stylesheet" href="css/materialize.min.css"  media="screen,projection"/>

		<title>Bobsbotty - Staus of challenges!</title>
	</head>

	<body>
		<div class="container">
			<div class="row">
				<div class="col s12">
					<table class="responsive-table">
						<thead>
							<tr>
								<th>Challenge</th>
								<th>Status</th>
							</tr>
						</thead>
						<tbody>
							<?php
								foreach($rows as $row){
									if($row['status'] === '0'){
										$color = 'red darken-1';
										$status = 'Not reviewed';
									} else if($row['status'] === '1'){
										$color = 'orange darken-1';
										$status = 'Under review';
									} else if($row['status'] === '2'){
										$color = 'green darken-1';
										$status = 'Accepted!';
									} else if($row['status'] === '3'){
										$color = 'blue-grey darken-1';
										$status = 'Declined :(';
									} else {
										$color = 'grey darken-1';
										$status = 'Retired!';
									}
									
									?>
							<tr>
								<td><?php echo $row['name']; ?></td>
								<td class="<?php echo $color; ?>"><?php echo $status; ?></td>
							</tr>
							<?php } ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</body>
</html>

								