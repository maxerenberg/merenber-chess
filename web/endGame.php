<?php
set_error_handler(function($errno, $errstr) { throw new Exception($errstr); });
$db = parse_url(getenv('DATABASE_URL'));
$conn = new PDO(sprintf("pgsql:host=%s;port=%s;user=%s;password=%s;dbname=%s",
	$db['host'], $db['port'], $db['user'], $db['pass'], ltrim($db['path'], '/'))
);
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$conn->exec("DELETE FROM CHESSPLAYERS");
$conn = null;
setcookie('playerID', '', time() - 3600);
file_put_contents('chessboard.txt',  '');
file_put_contents('recentMove.txt', '');
echo "game successfully ended";
?>
