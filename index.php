<?php

define("TinyList", "TinyList");
require 'backend.php';

$body = '<main>';
$title = 'Tinylist';
$T = NULL;

// Add a new list.
if (isset($_POST['new-list'])) {
	// If successfully added, display it immediately.
	if (addList($_POST['name'], $body) === true) {
		header("Location: " . htmlspecialchars($_SERVER['PHP_SELF']) . '?list=' . flattenListName($_POST['name']));
	}
}

// Remove a list.
if (isset($_POST['remove-list']) && isset($_POST['confirm']) && isset($_POST['name'])) {
	// Confirm the user really wants to delete the list.
	if ($_POST['confirm'] == 'delete') {
		removeList($_POST['name'], $body);
	} else {
		// Else, display the list with a warning message.
		$body .= '<div class="note orange">Confirm deleting by typing \'delete\' and then submitting. Typing anything else will not work.</div>';
		$_GET['list'] = $_POST['name'];
	}
}

// Show a specific list.
if (isset($_GET['list'])) {
	if (!listExists($_GET['list'])) {
		// If the list does not exist, show a warning message.
		$body .= sprintf('<div class="note orange">List %s does not exist.</div>', htmlspecialchars($_GET['list']));
	} else {
		// Else, show the list.
		$T = new TinyList($_GET['list'] . '.list');

		// Add and remove items if needed.
		if (isset($_POST['new-item'])) {
			$T->addItem($_POST['name']);
			$T->reload();
		}
		else if (isset($_POST['remove-item'])) {
			$T->removeItem($_POST['linenumber']);
			$T->reload();
		}

		$body .= $T->printList();
		$title .= ' – ' . $T->getName();
	}
}

// By default, show an overview of all existing lists.
if ($T === NULL) {
	$body .= '<h1>List overview</h1>';
	$body .= listOfLists();

	$title .= ' – List overview';
}

$body .= '</main>';

?>

<!doctype HTML>

<html lang="nl">

<title><?php echo $title; ?></title>
<meta charset="UTF-8" />
<meta content="noindex" />
<link rel="stylesheet" href="fancy.css" />
<script type="text/javascript" src="hacky.js"></script>

<body>
	<?php echo $body; ?>

	<footer class="note white" style="width: 65%; max-width: 600px;"><i>&#8220;Klein maar fijn!&#8221;</i><br />
	TinyList 2015-2017</footer>
</body>

</html>
