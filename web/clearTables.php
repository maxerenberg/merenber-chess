<?php
$db = parse_url(getenv('DATABASE_URL'));
$conn = new PDO(sprintf("pgsql:host=%s;port=%s;user=%s;password=%s;dbname=%s",
	$db['host'], $db['port'], $db['user'], $db['pass'], ltrim($db['path'], '/'))
);
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$days = $conn->query("SELECT EXTRACT(DAY FROM (NOW() - STAMP)) FROM RECENTMOVE", PDO::FETCH_NUM)->fetch();
if ($days !== false and intval($days[0]) > 0) $conn->exec("DELETE FROM RECENTMOVE");
$conn = null;
?>
