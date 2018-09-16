<?php
set_error_handler(function($errno, $errstr) { throw new Exception($errstr); });
$db = parse_url(getenv('DATABASE_URL'));
$conn = new PDO(sprintf("pgsql:host=%s;port=%s;user=%s;password=%s;dbname=%s",
	$db['host'], $db['port'], $db['user'], $db['pass'], ltrim($db['path'], '/'))
);
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$results = $conn->query("SELECT PLAYERID, PLAYERNUM FROM CHESSPLAYERS", PDO::FETCH_ASSOC)->fetchAll();
if (count($results) != 2) die('2 players not present in game');
$playerID = isset($_COOKIE['playerID']) ? intval($_COOKIE['playerID']) : null;
$playerNum = null;
foreach ($results as $row) {
	if ($row['playerid'] == $playerID) {
		$playerNum = $row['playernum'];
		break;
	}
}
if (!isset($playerNum)) die('Game has ended or you are not part of game');
$conn = null;

if (!isset($_POST['before']) and isset($_POST['after']) and isset($_POST['piece']))
	die('Variables missing from request');
if (!(preg_match("/^[0-7]:[0-7]$/", $_POST['before']) and
	preg_match("/^[0-7]:[0-7]$/", $_POST['after']) and
	preg_match("/^[wb][prnbkq][0-7]$/", $_POST['piece']))) die('Data in invalid format');
$before = $_POST['before'];
$after = $_POST['after'];
$piece = $_POST['piece'];

$oldy = intval($before[0]);
$oldx = intval($before[2]);
$newy = intval($after[0]);
$newx = intval($after[2]);
$board = file('chessboard.txt', FILE_IGNORE_NEW_LINES);
$homecolor = $board[8] == 0 ? 'w' : 'b';
$oppcolor = $board[8] == 0 ? 'b' : 'w';

if ($playerNum != $board[8]) die('Not your turn!');
if ($piece[0] != $homecolor) die("Must move your own piece");
foreach (array($oldy, $oldx, $newy, $newx) as $x) {
	if ($x < 0 or $x > 7) die('Invalid coordinates passed');
}
if ($oldx == $newx and $oldy == $newy) die('Must move piece to different position');
for ($i = 0; $i < 8; $i++) $board[$i] = explode(' ', $board[$i]);
if ($board[$oldy][$oldx] != $piece) die('Piece not in position specified');
if (!validMove($piece, $oldy, $oldx, $newy, $newx, $playerNum)) die('Invalid move');
$board[$oldy][$oldx] = '000';
$board[$newy][$newx] = $piece;
if (inCheck($homecolor, $oppcolor, $board)) die('King would be in check');

$extraInfo = "";
// if the piece was a pawn and it reached the end of the board, turn it into a queen
if ($piece[1] == 'p' and (($homecolor == 'w' and $newy == 0) or ($homecolor == 'b' and $newy == 7))) {
	$newID = $homecolor . 'q' . nextQueen($homecolor);
	$board[$newy][$newx] = $newID;
	$extraInfo .= " " . $newID;
}
$checkFlag = inCheck($oppcolor, $homecolor, $board);
if ($checkFlag) $extraInfo .= " check";
if (isCheckmate($oppcolor, $homecolor)) {
	$extraInfo .= $checkFlag ? "mate" : " checkmate";
}
$f = fopen("chessboard.txt", 'w');
for ($i = 0; $i < 8; $i++) {
	fwrite($f, implode(' ', $board[$i]) . "\n");
}
fwrite($f, $board[8] == 0 ? '1' : '0');
fclose($f);
file_put_contents('recentMove.txt', $before . ' ' . $after . ' ' . $piece . $extraInfo);
setcookie('playerID', strval($playerID), strtotime('tomorrow'), '/');  // increase the expiration date
echo 'success';

function sign($x) {
	if ($x < 0) return -1;
	if ($x > 0) return 1;
	return 0;
}
function validMove($p, $oy, $ox, $ny, $nx, $playerNum) {
	// assumes that $p is the correct piece at $board[$ny][$nx]
	// assumes that $oy, $ox, $ny and $nx are valid positions on the board
	$board = $GLOBALS['board'];
	if ($board[$ny][$nx][0] == $p[0]) return false;  // can't take own piece
	$dy = $ny - $oy;
	$dx = $nx - $ox;
	if ($p[1] == 'p') {
		$reldy = $playerNum == 0 ? $dy : -$dy;  // makes calculations a bit easier
		if ($board[$ny][$nx] != '000') {
			// recall: the topmost row is y=0
			if (abs($dx) != 1 or $reldy != -1) return false;
		} else {
			if ($dx != 0) return false;
		}
		if (($playerNum == 0 and $oy == 6) or ($playerNum == 1 and $oy == 1)) {
			if ($reldy != -2 and $reldy != -1) return false;
		} else {
			if ($reldy != -1) return false;
		}
	} elseif ($p[1] == 'r') {
		if ($dy != 0 and $dx != 0) return false;
	} elseif ($p[1] == 'n') {
		if (!((abs($dx) == 2 and abs($dy) == 1) or (abs($dx) == 1 and abs($dy) == 2))) return false;
	} elseif ($p[1] == 'b') {
		if (abs($dy) != abs($dx)) return false;
	} elseif ($p[1] == 'k') {
		if (abs($dy) > 1 or abs($dx) > 1) return false;
	} elseif ($p[1] == 'q') {
		if (!($dy == 0 or $dx == 0 or abs($dy) == abs($dx))) return false;
	} else {
		die('Unknown piece submitted');
	}
	if ($p[1] == 'n') return true;
	// now check if the piece jumped over any other pieces
	$incx = sign($dx);
	$incy = sign($dy);
	$len = max(abs($dx), abs($dy));
	for ($i = 1; $i < $len; $i++) {
		if ($board[$oy + $incy * $i][$ox + $incx * $i] != '000') return false;
	}
	return true;
}
function inCheck($hc, $oc, $board) {
	// get king's position
	for ($i = 0; $i < 8; $i++) {
		for ($j = 0; $j < 8; $j++) {
			if ($board[$i][$j][0] == $hc and $board[$i][$j][1] == 'k') {
				$ky = $i;
				$kx = $j;
				break;
			}
		}
		if (isset($kx)) break;
	}
	if (!isset($kx)) die('King is not present; file corrupted');
	// check for pawns
	$incy = $hc == 'w' ? -1 : 1;
	if ($ky + $incy >= 0 and $ky + $incy < 8) {
		if ($kx > 0) {
			$p = $board[$ky + $incy][$kx - 1];
			if ($p[0] == $oc and $p[1] == 'p') return true;
		}
		if ($kx < 7) {
			$p = $board[$ky + $incy][$kx + 1];
			if ($p[0] == $oc and $p[1] == 'p') return true;
		}
	}
	// check diagonals (bishops and queen)
	$incs = array(array(1, 1), array(1, -1), array(-1, 1), array(-1, -1)); 
	foreach ($incs as $inc) {
		$incx = $inc[0];
		$incy = $inc[1];
		$len = min($incx == 1 ? 7 - $kx : $kx, $incy == 1 ? 7 - $ky : $ky);
		for ($i = 1; $i <= $len; $i++) {
			$p = $board[$ky + $incy * $i][$kx + $incx * $i];
			if ($p == '000') continue;
			if ($p[0] == $oc and ($p[1] == 'b' or $p[1] =='q')) return true;
			break;
		}
	}
	// check row and column (rooks and queen)
	$incs = array(array(1, 0), array(-1, 0), array(0, 1), array(0, -1));
	foreach ($incs as $inc) {
		$incx = $inc[0];
		$incy = $inc[1];
		if ($incy == 0) $len = $incx == 1 ? 7 - $kx : $kx;
		else $len = $incy == 1 ? 7 - $ky : $ky;
		for ($i = 1; $i <= $len; $i++) {
			$p = $board[$ky + $incy * $i][$kx + $incx * $i];
			if ($p == '000') continue;
			if ($p[0] == $oc and ($p[1] == 'r' or $p[1] =='q')) return true;
			break;
		}
	}
	// adjacent squares (king)
	$incs = array(array(1, 0), array(-1, 0), array(0, 1), array(0, -1),
				array(1, 1), array(1, -1), array(-1, 1), array(-1, -1));
	foreach ($incs as $inc) {
		$incx = $inc[0];
		$incy = $inc[1];
		if ($kx + $incx < 0 or $kx + $incx > 7 or $ky + $incy < 0 or $ky + $incy > 7) continue;
		$p = $board[$ky + $incy][$kx + $incx];
		if ($p[0] == $oc and $p[1] == 'k') return true; 
	}
	// check all L-shapes (knights)
	$incs = array(array(2, 1), array(2, -1), array(-2, 1), array(-2, -1), 
				array(1, 2), array(1, -2), array(-1, 2), array(-1, -2));
	foreach ($incs as $inc) {
		$incx = $inc[0];
		$incy = $inc[1];
		if ($kx + $incx < 0 or $kx + $incx > 7 or $ky + $incy < 0 or $ky + $incy > 7) continue;
		$p = $board[$ky + $incy][$kx + $incx];
		if ($p[0] == $oc and $p[1] == 'n') return true; 
	}
	// if all tests passed
	return false;
}
function isCheckmate($hc, $oc) {
	$board = $GLOBALS['board'];
	$playerNum = $hc == 0 ? 'w' : 'b';
	for ($i = 0; $i < 8; $i++) {
		for ($j = 0; $j < 8; $j++) {
			$p = $board[$i][$j];
			if ($p[0] != $hc) continue;
			$incs = array();
			if ($p[1] == 'p') {
				$reldy = $playerNum == 0 ? -1 : 1;
				$incs = array(array(0, $reldy), array(0, 2 * $reldy), array(1, $reldy), array(-1, $reldy));
			} elseif ($p[1] == 'r') {
				for ($k = 1; $k < 8; $k++) {
					$incs[] = array(-$k, 0);
					$incs[] = array($k, 0);
					$incs[] = array(0, $k);
					$incs[] = array(0, -$k);
				}
			} elseif ($p[1] == 'n') {
				$incs = array(array(2, 1), array(2, -1), array(-2, 1), array(-2, -1), 
					array(1, 2), array(1, -2), array(-1, 2), array(-1, -2));
			} elseif ($p[1] == 'b') {
				for ($k = 1; $k < 8; $k++) {
					$incs[] = array($k, $k);
					$incs[] = array($k, -$k);
					$incs[] = array(-$k, $k);
					$incs[] = array(-$k, -$k);
				}
			} elseif ($p[1] == 'k') {
				$incs = array(array(1, 0), array(-1, 0), array(0, 1), array(0, -1),
					array(1, 1), array(1, -1), array(-1, 1), array(-1, -1));
			} elseif ($p[1] == 'q') {
				for ($k = 1; $k < 8; $k++) {
					$incs[] = array($k, $k);
					$incs[] = array($k, -$k);
					$incs[] = array(-$k, $k);
					$incs[] = array(-$k, -$k);
					$incs[] = array(-$k, 0);
					$incs[] = array($k, 0);
					$incs[] = array(0, $k);
					$incs[] = array(0, -$k);
				}
			} else {
				die('Unknown piece present');
			}
			foreach ($incs as $inc) {
				$ny = $i + $inc[1];
				$nx = $j + $inc[0];
				if ($nx < 0 or $nx > 7 or $ny < 0 or $ny > 7) continue;
				if (validMove($p, $i, $j, $ny, $nx, $playerNum, $board)) {
					$temp = $board[$ny][$nx];
					$board[$i][$j] = '000';
					$board[$ny][$nx] = $p;
					if (!inCheck($hc, $oc, $board)) return false;
					$board[$i][$j] = $p;
					$board[$ny][$nx] = $temp;
				}
			}
		}
	}
	return true;
}
function nextQueen($c) {
	$board = $GLOBALS['board'];
	$maxQueen = -1;
	for ($i = 0; $i < 8; $i++) {
		for ($j = 0; $j < 8; $j++) {
			$p = $board[$i][$j]; 
			if ($p[0] == $c and $p[1] == 'q') $maxQueen = max($maxQueen, intval($p[2]));
		}
	}
	return $maxQueen + 1;
}
?>
