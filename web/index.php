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
	if ($playerID == $row['PLAYERID']) {
		$playerFlag = 1;
		$playerNum = $row['PLAYERNUM'];
		$playerName = $row['PLAYERNAME'];
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
			$boardText = "br0 bn0 bb0 bk0 bq0 bb1 bn1 br1\nbp0 bp1 bp2 bp3 bp4 bp5 bp6 bp7\n"
				. str_repeat(implode(' ', array_fill(0, 8, '000')) . "\n", 4)
				. "wp0 wp1 wp2 wp3 wp4 wp5 wp6 wp7\nwr0 wn0 wb0 wk0 wq0 wb1 wn1 wr1\n0";
			file_put_contents('chessboard.txt',  $boardText);
			file_put_contents('recentMove.txt', '');
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
var mostRecentMove = "<?php echo file_get_contents('recentMove.txt'); ?>";
</script>
<script src="chess.js"></script>
</body>
</html>
