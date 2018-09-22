<?php
set_error_handler(function($errno, $errstr) {
	if ($errno == E_USER_ERROR) {
		session_unset();
		session_destroy();
	}
	throw new Exception($errstr); 
});
$playerID = isset($_COOKIE['playerID']) ? intval($_COOKIE['playerID']) : null;
// connect to database
$db = parse_url(getenv('DATABASE_URL'));
$conn = new PDO(sprintf("pgsql:host=%s;port=%s;user=%s;password=%s;dbname=%s",
	$db['host'], $db['port'], $db['user'], $db['pass'], ltrim($db['path'], '/'))
);
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$results = $conn->query("SELECT * FROM CHESSPLAYERS", PDO::FETCH_ASSOC)->fetchAll();
$numPlayers = count($results);
if ($numPlayers > 2) trigger_error('Too many players! Check table', E_USER_ERROR);
$playerFlag = 0;  // 0 = new player, 1 = old player, 2 = reject (too many players)
$playerNum = 'null';  // these need to be strings because we're using them as JavaScript variables later
$playerName = 'null';
foreach ($results as $row) {
	if ($playerID == $row['playerid']) {
		$playerFlag = 1;
		$playerNum = $row['playernum'];
		$playerName = $row['playername'];
		break;
	}
}
if ($playerFlag == 0) {
	if ($_SERVER['REQUEST_METHOD'] == 'POST') {
		if (!isset($_POST['playerName'])) trigger_error('Player name missing from request', E_USER_ERROR);
		if (!preg_match('/^[ a-zA-Z0-9_-]+$/', $_POST['playerName'])) {
			trigger_error('Invalid player name: ', E_USER_ERROR);  // TODO: change this to a more user-friendly error
		}
		if ($numPlayers == 0) {
			$conn->exec("DELETE FROM CHESSBOARD");
			$board = array(
				array(0,'br0','bn0','bb0','bk0','bq0','bb1','bn1','br1'),
				array(1,'bp0','bp1','bp2','bp3','bp4','bp5','bp6','bp7'),
				array(2,'000','000','000','000','000','000','000','000'),
				array(3,'000','000','000','000','000','000','000','000'),
				array(4,'000','000','000','000','000','000','000','000'),
				array(5,'000','000','000','000','000','000','000','000'),
				array(6,'wp0','wp1','wp2','wp3','wp4','wp5','wp6','wp7'),
				array(7,'wr0','wn0','wb0','wk0','wq0','wb1','wn1','wr1')
			);
			$stmt = $conn->prepare("INSERT INTO CHESSBOARD VALUES (?,?,?,?,?,?,?,?,?)");
			foreach ($board as $row) $stmt->execute($row);
			$conn->exec("DELETE FROM RECENTMOVE");
			$conn->exec("INSERT INTO RECENTMOVE (STAMP) VALUES (NOW())");
			$playerNum = 0;
		} elseif ($numPlayers == 1) {
			$playerNum = 1;
		} else {
			trigger_error('POST request rejected: 2 players already present', E_USER_ERROR);
		}
		// keep the htmlspecialchars filter in case more chars are later added to the regex
		$playerName = htmlspecialchars($_POST['playerName']);
		if (strlen($playerName) > 250) trigger_error('Player name is too long', E_USER_ERROR);
		$playerFlag = 1;
		$stmt = $conn->prepare("INSERT INTO CHESSPLAYERS VALUES (?,?,?)");
		$playerID = random_int(PHP_INT_MIN, PHP_INT_MAX);
		$stmt->execute(array($playerID, $playerName, $playerNum));
		setcookie('playerID', strval($playerID), strtotime('tomorrow'), '/');
	} else {
		if ($numPlayers == 2) $playerFlag = 2;
	}
}
$conn = null;
$mostRecentMove = array('old'=>null,'new'=>null,'piece'=>null,
	'incheck'=>null,'checkmate'=>null,'pawntoqueen'=>null);
?>
<!DOCTYPE html>
<html>
<head>
	<title>Chess</title>
	<meta charset="utf-8">
	<link rel="stylesheet" href="chess.css">
</head>
<body>
<script>
	var playerFlag = <?php echo $playerFlag; ?>;
	var playerNum = <?php echo $playerNum; ?>;
	var playerName = "<?php echo $playerName; ?>";
</script>
<script src="chess.js"></script>
</body>
</html>
