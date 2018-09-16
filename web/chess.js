if (playerFlag === 0) {
	var form = document.createElement('form');
	form.setAttribute('action', "index.php");
	form.setAttribute('method', 'POST');
	form.appendChild(document.createTextNode('Enter your name (alphanumeric characters only): '));
	var input = document.createElement('input');
	input.setAttribute('type', 'text');
	input.setAttribute('name', 'playerName');
	form.appendChild(input);
	document.body.appendChild(form);
} else if (playerFlag === 2) {
	document.write('Sorry! Two players already present');
	throw 'Two players already present';
} else {
	var homecolor = playerNum === 0 ? 'w' : 'b';
	var royals = ['rook', 'knight', 'bishop', 'king', 'queen', 'bishop', 'knight', 'rook'];
	var royalAbbr = {'rook':'r', 'knight':'n', 'bishop':'b', 'king':'k', 'queen':'q'};
	
	var para = document.createElement('p');
	para.innerHTML = 'Welcome, ' + playerName;
	document.body.appendChild(para);
	para = document.createElement('p');
	para.innerHTML = "It is currently <span id='playerTurn'></span> turn";
	document.body.appendChild(para);
	para = document.createElement('p');
	para.setAttribute('id', 'recentMoveMsg');
	document.body.appendChild(para);
	para = document.createElement('p');
	para.setAttribute('id', 'sendMoveMsg');
	document.body.appendChild(para);

	var board = document.createElement('table');
	for (var i = 0; i < 8; i++) {
		var row = document.createElement('tr');
		for (var j = 0; j < 8; j++) {
			var td = document.createElement('td');
			td.addEventListener('drop', function() { onDrop(event); });
			td.addEventListener('dragover', function() { onDragOver(event); });
			td.setAttribute('id', playerNum === 0 ? i + ':' + j : (7 - i) + ':' + (7 - j));
			row.appendChild(td);
		}
		board.appendChild(row);
	}
	document.body.appendChild(board);

	var button = document.createElement('button');
	button.setAttribute('id', 'exitGame');
	button.addEventListener('click', endGame);
	button.innerHTML = 'Exit game';
	document.body.appendChild(button);

	initBoard();
	var sendMoveMsgUpdater = setTimeout(null, 0);
	var boardUpdater = setInterval(updateBoard, 1000);
}

function initBoard() {
	var xhttp = new XMLHttpRequest();
	xhttp.onreadystatechange = function() {
		if (this.readyState == 4 && this.status == 200) {
			var tableData = this.responseText.replace('/\r/', '').split("\n");
			for (var i = 0; i < 8; i++) {
				tableData[i] = tableData[i].split(" ");
				for (var j = 0; j < 8; j++) {
					var id = tableData[i][j];
					if (id == '000') continue;
					var img = document.createElement('img');
					img.setAttribute('src', id.substr(0, 2) + '.png');
					img.setAttribute('id', id);
					if (id.charAt(0) === homecolor) {
						img.setAttribute('draggable', 'true');
						img.addEventListener('dragstart', function() { onDragStart(event); });
					} else {
						img.setAttribute('class', 'unselectable');
					}
					document.getElementById(i + ':' + j).appendChild(img);
				}
			}
			document.getElementById('playerTurn').innerHTML = tableData[8] == playerNum ? "your" : "your opponent's";
		}
	};
	xhttp.open('GET', 'chessboard.txt');
	xhttp.send();
}

function onDragStart(ev) {
	ev.dataTransfer.setData('text/plain', ev.target.id);
}
function onDragOver(ev) {
	ev.preventDefault();
}
function onDrop(ev) {
	ev.preventDefault();
	var imgId = ev.dataTransfer.getData('text/plain');
	var img = document.getElementById(imgId);
	sendMove(img.parentNode.id, ev.currentTarget, imgId, img);
}
function sendMove(before, target, imgId, img) {
	var xhttp = new XMLHttpRequest();
	xhttp.onreadystatechange = function() {
		if (this.readyState == 4) {
			if (this.status == 200) {
				if (this.responseText == "success") {
					if (target.hasChildNodes()) target.removeChild(target.firstChild);
					target.appendChild(img);
					document.getElementById('playerTurn').innerHTML = "your opponent's";
				} else {
					updateSendMoveMsg(this.responseText);
				}
			} else {
				updateSendMoveMsg('Request failed');
			}
		}
	};
	xhttp.open('POST', 'movePiece.php', true);
	xhttp.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
	xhttp.send('before=' + before + '&after=' + target.id + '&piece=' + imgId);
}
function updateRecentMoveMsg(msg, imgId) {
	var c = imgId.charAt(0);
	if (msg == 'check') {
		msg = 'Your ' + (c == homecolor ? "opponent's " : '') + 'king is in check';
	} else if (msg == 'checkmate') {
		msg = 'Checkmate! ' + (c == homecolor ? 'You win' :  'Game over');
		endGame();
	} else if (/^[wb]q[0-8]$/.test(msg)) {  // turn a pawn into a queen
		var img = document.getElementById(imgId);
		if (img === null) return;  // we've already turned it into a queen
		img.setAttribute('id', msg);
		img.setAttribute('src', c + 'q.png');
		return;
	}
	document.getElementById('recentMoveMsg').innerHTML = msg;
}
function updateSendMoveMsg(msg) {
	clearTimeout(sendMoveMsgUpdater);
	var elem = document.getElementById('sendMoveMsg');
	elem.innerHTML = msg;
	sendMoveMsgUpdater = setTimeout(function() { elem.innerHTML = ''; }, 2000);
}
function updateBoard() {
	var xhttp = new XMLHttpRequest();
	xhttp.onreadystatechange = function() {
		if (this.readyState == 4 && this.status == 200) {
			if (mostRecentMove == this.responseText) return;  // for efficiency
			var data = this.responseText.split(' ');
			if (data.length < 3) return;  // in case we query the file while it's being written to
			if (data[2].charAt(0) != homecolor) {  // if the most recent move was our own, there's nothing to move
				var img = document.getElementById(data[2]);
				if (img !== null) {  
				// if img is null, we previously transformed it into a queen; 
				// the (mostRecentMove == this.responseText) should prevent this from happening though
					var target = document.getElementById(data[1]);
					if (target.hasChildNodes() && target.firstChild !== img) target.removeChild(target.firstChild);  
					// it's possible that target.firstChild === img if, for whatever reason, 
					// the (mostRecentMove == this.responseText) failed
					target.appendChild(img);
				}
				document.getElementById('playerTurn').innerHTML = 'your';
			}
			mostRecentMove = this.responseText;
			document.getElementById('recentMoveMsg').innerHTML = '';
			// if there was any other info passed along, process it and act accordingly
			for (var i = 3; i < data.length; i++) updateRecentMoveMsg(data[i], data[2]);
		}
	};
	xhttp.open('GET', 'recentMove.txt');
	xhttp.send();
}
function endGame() {
	clearInterval(boardUpdater);
	var xhttp = new XMLHttpRequest();
	xhttp.onreadystatechange = function() {
		if (this.readyState == 4 && this.status == 200) {
			console.log(this.responseText);  // just for confirmation
		}
	};
	xhttp.open('GET', 'endGame.php');
	xhttp.send();
	document.body.removeChild(document.getElementById('exitGame'));
	document.getElementById('playerTurn').parentNode.innerHTML = 'Game has ended';
	var a = document.createElement('a');
	a.setAttribute('href', 'chess.php');
	a.innerHTML = 'Click here to play again';
	document.body.appendChild(a);
}
