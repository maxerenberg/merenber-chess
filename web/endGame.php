<?php
set_error_handler(function($errno, $errstr) { throw new Exception($errstr); });
$db = parse_url(getenv('DATABASE_URL'));
$conn = new PDO(sprintf("pgsql:host=%s;port=%s;user=%s;password=%s;dbname=%s",
	$db['host'], $db['port'], $db['user'], $db['pass'], ltrim($db['path'], '/'))
);
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$conn->exec("DELETE FROM CHESSPLAYERS");
$conn->exec("DELETE FROM CHESSBOARD");
// update the RECENTMOVE table so that the other player knows the game was
//  forcefully ended (i.e. it wasn't a checkmate)
$conn->exec("UPDATE RECENTMOVE SET ENDGAME = '1'");
$conn = null;
setcookie('playerID', '', time() - 3600);
echo "game successfully ended";
?>
