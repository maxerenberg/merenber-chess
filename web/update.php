<?php
set_error_handler(function($errno, $errstr) { throw new Exception($errstr); });
$conn = new PDO(sprintf("pgsql:host=%s;port=%d;user=%s;dbname=%s",
	'localhost', 5432, 'maxer', 'postgres')
);
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
if (!isset($_GET['q'])) trigger_error('Invalid request', E_USER_ERROR);
if ($_GET['q'] == 'initboard') {
	$results = $conn->query(
		"SELECT COL0,COL1,COL2,COL3,COL4,COL5,COL6,COL7 FROM CHESSBOARD ORDER BY ROWNUM", PDO::FETCH_NUM
	)->fetchAll();
	if (count($results) != 8) trigger_error('CHESSBOARD table is malformed', E_USER_ERROR);
} elseif ($_GET['q'] == 'updateboard') {
	$results = $conn->query("SELECT * FROM RECENTMOVE", PDO::FETCH_ASSOC)->fetch();
} else {
	trigger_error('Invalid request', E_USER_ERROR);
}
echo json_encode($results);
$conn = null;
?>
