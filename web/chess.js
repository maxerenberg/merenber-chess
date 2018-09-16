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
	updateBoard();
	var mostRecentMove = 'false';
	var sendMoveMsgUpdater = setTimeout(null, 0);
	var boardUpdater = setInterval(updateBoard, 1000);
}

function initBoard() {
	var xhttp = new XMLHttpRequest();
	xhttp.onreadystatechange = function() {
		if (this.readyState == 4 && this.status == 200) {
			var tableData = JSON.parse(this.responseText);
			if (tableData.length != 8) throw "Bad response";
			for (var i = 0; i < 8; i++) {
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
		}
	};
	xhttp.open('GET', 'update.php?q=initboard');
	xhttp.send();
	// now initialize whose turn it is
	xhttp = new XMLHttpRequest();
	xhttp.onreadystatechange = function() {
		if (this.readyState == 4 && this.status == 200) {
			var data = JSON.parse(this.responseText);
			var elem = document.getElementById('playerTurn');
			if (data === false) {
				elem.innerHTML = playerNum == 0 ? "your" : "your opponent's";
				// if game just started, white player goes first
			} else {
				elem.innerHTML = homecolor == data['piece'].charAt(0) ? "your opponent's" : "your";
			}
		}
	}
	xhttp.open('GET', 'update.php?q=updateboard');
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
			if (mostRecentMove === this.responseText) return;  // for efficiency
			var data = JSON.parse(this.responseText);
			if (data === false) return;  // when the game first starts, the table will be empty
			if (data['piece'].charAt(0) != homecolor) {  // if the most recent move was our own, there's nothing to move
				var img = document.getElementById(data['piece']);
				if (img !== null) {  
				// it's possible that img is null if a pawn turned into a queen and the page was refreshed 
				// (since mostRecentMove is initialized to 'false')
					var target = document.getElementById(data['new']);
					if (target.hasChildNodes() && target.firstChild !== img) target.removeChild(target.firstChild);  
					// it's possible that target.firstChild === img if the page was refreshed
					target.appendChild(img);
				}
				document.getElementById('playerTurn').innerHTML = 'your';
			}
			mostRecentMove = data;
			document.getElementById('recentMoveMsg').innerHTML = '';
			// if there was any other info passed along, process it and act accordingly
			if (data['pawntoqueen'] !== null) {
				var img = document.getElementById(data['piece']);
				if (img !== null) {
					// it's possible that img is null if the page was refreshed
					img.setAttribute('id', data['pawntoqueen']);
					img.setAttribute('src', data['piece'].charAt(0) + 'q.png');
				}
			}
			if (data['checkmate']) {
				updateRecentMoveMsg('checkmate', data['piece']);
			} else {
				if (data['incheck']) updateRecentMoveMsg('check', data['piece']);
			}
		}
	};
	xhttp.open('GET', 'update.php?q=updateboard');
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
	a.setAttribute('href', 'index.php');
	a.innerHTML = 'Click here to play again';
	document.body.appendChild(a);
}
