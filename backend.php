<?php

// Must be called from within the application itself.
if (!defined("TinyList")) {
	exit;
}

const LISTDIR = "lists/";
const ITEMDELIM_BEGIN = "BeginItems";
const ITEMDELIM_END = "EndItems";
const SUBLISTDELIM = ":";

const COLOURS = array("green", "red", "blue", "orange", "purple", "yellow");

// Returns whether a given string is a valid list filename.
// A valid filename consists of lowercase letters, numbers, and the following characters: _-()
function isListName($filename) {
	return (preg_match('/^[a-z0-9_\-()]+\.list$/', $filename) === 1);
}

// Returns a flattened string.
// Flattening decapilises the string and removes any character not allowed by isListName.
function flattenListName($listname) {
	$filename = str_replace(" ", "_", $listname);
	$filename = strtolower($filename);
	$filename = preg_replace('/[^a-z0-9_\-()]/', '', $filename);

	return $filename;
}

// Returns whether a list exists.
function listExists($filename) {
	return file_exists(LISTDIR . $filename . '.list');
}

// Returns a string containing a list of all existing lists.
function listOfLists() {
	$listnames = scandir(LISTDIR);
	$listnames = array_filter($listnames, "isListName");

	$body = '<ul class="lists">';

	foreach($listnames as $filename) {
		$T = new TinyList($filename);
		$body .= sprintf('<a href="?list=%s"><li><div class="content %s">%s (%s)</div></li></a>', substr($filename, 0, strlen($filename) - 5), $T->getColour(), $T->getName(), $T->getItemCount());
	}

	// Add a form for adding a new list.
	$body .= '<form class="singleline white" method="post" action="' . htmlspecialchars($_SERVER['PHP_SELF']) . '" role="form">';
	$body .= '<input class="" type="text" name="name" placeholder="New list name" style="margin-right: -76px;" required />';
	$body .= '<input type="hidden" name="new-list" />';
	$body .= '<button type="submit" class="blue">New list</button>';
	$body .= '</form>';

	$body .= '</ul>';

	return $body;
}

// Creates a new list.
function addList($listname, &$body) {
	$filename = flattenListName($listname);
	if (!isListName($filename . '.list')) {
		$body .= sprintf('<p class="note success">Invalid list name: %s.</p>', $listname);
		return false;
	}
	if (listExists($filename)) {
		$body .= sprintf('<p class="note success">Filename %s already taken.</p>', $filename);
		return false;
	}

	$file = fopen(LISTDIR . $filename . '.list', 'w');
	if ($file === false) {
		$body .= sprintf('<p class="note warning">Some technical error occured when opening the file for writing.</p>');
		return false;
	}
	fwrite($file, sprintf("Name %s\n", $listname));
	fwrite($file, "\n");
	fwrite($file, "BeginItems\n");
	fwrite($file, "EndItems\n");
	fclose($file);

	// chmod the new list so the user (and anyone) can edit it freely in a text editor.
	// Only useful for local installations where the user actually wants to edit the lists as plain text.
	// Comment the line below if you do not want this.
	chmod(LISTDIR . $filename . '.list', 0777);

	return true;
}

// Removes a list.
function removeList($filename, &$body) {
	if (!isListName($filename . '.list')) {
		$body .= sprintf('<div class="note orange">Invalid file name: %s.</div>', $filename);
		return false;
	}
	if (!listExists($filename)) {
		$body .= sprintf('<div class="note orange">List %s does not exist.</div>', $filename);
		return false;
	}

	if (!unlink(LISTDIR . $filename . '.list')) {
		$body .= sprintf('<div class="note orange">Some technical error occured when attempting to unlink the file.</div>');
		return false;
	}

	$body .= sprintf('<div class="note blue">List %s was successfully removed.</div>', $filename);
	return true;
}

// List container object.
class TinyList {
	private $filename, $name, $colour, $items, $firstitem, $lastitem, $sublists;

	// Constructor.
	public function __construct($filename) {
		// TODO: File existence check.

		$this->filename = LISTDIR . $filename;

		// TODO: Check if valid list.

		$this->parseFile();
	}

	// Parse the file again to load changes.
	public function reload() {
		$this->parseFile();
	}

	// Parses a file containing a list.
	private function parseFile() {
		// Set the colour at random.
		$this->colour = COLOURS[rand(0, count(COLOURS) - 1)];
		$this->name = '';
		$this->items = array();
		$this->sublists = array();
		$this->firstitem = 0;
		$this->lastitem = 0;

		// $lines becomes an array with every line of the file, one line per index.
		$filecontent = file_get_contents($this->filename);
		$lines = explode("\n", $filecontent);
		$linemax = count($lines);
		$linenumber = 0;

		// Parse header for name and colour.
		while ($linenumber < $linemax && $lines[$linenumber] != ITEMDELIM_BEGIN) {
			if (strpos($lines[$linenumber], "Name ") === 0) {
				$this->name = htmlspecialchars(substr($lines[$linenumber], strlen("Name ")));
			}
			else if (strpos($lines[$linenumber], "Colour ") === 0) {
				// TODO: Check if valid colour.
				$this->colour = htmlspecialchars(substr($lines[$linenumber], strlen("Colour ")));
			}

			$linenumber++;
		}

		// Parse list of items.
		while ($linenumber < $linemax && $lines[$linenumber] != ITEMDELIM_END) {

			if (strpos($lines[$linenumber], "Item ") === 0) {
				$name = substr($lines[$linenumber], strlen("Item "));
				// Check if sublist needed
				if (($separator = strpos($name, SUBLISTDELIM)) !== false) {
					$sublistname = substr($name, 0, $separator);
					$sublist = $this->getSublist($sublistname);

					// Add the sublist if it doesn't exist yet.
					if ($sublist === -1) {
						$this->items[] = new SubList($sublistname, $linenumber + 1);
						$this->sublists[$sublistname] = count($this->items) - 1;
						$sublist = $this->getSublist($sublistname);

						$this->items[$sublist]->init();
					}
					
					// Add the item to the sublist.
					$this->items[$sublist]->addItem(substr($name, $separator + 1), $linenumber);
				}
				else {
					// Add the item to the main list.
					$this->items[] = new ListItem($name, $linenumber + 1);
				}

				// Update the first and last item indices.
				if ($this->firstitem == 0)
					$this->firstitem = $linenumber + 1;
				$this->lastitem = $linenumber + 1;
			}

			$linenumber++;
		}

		// Parse footer.
		{
			// Nothing yet.
		}

	}

	// Returns the name of the list.
	public function getName() {
		return $this->name;
	}

	// Returns the colour of the list.
	public function getColour() {
		return $this->colour;
	}

	// Returns the number of items in the list.
	// Recursively also counts items in sublists.
	public function getItemCount() {
		$count = 0;
		foreach($this->items as $item) {
			if ($item instanceof SubList)
				$count += $item->getItemCount();
			else
				$count += 1;
		}

		return $count;
	}

	// Returns a given sublist, or -1 on failure.
	public function getSublist($name) {
		if (isset($this->sublists[$name]))
			return $this->sublists[$name];
		return -1;
	}

	// Returns a formatted string representing the list.
	public function printList() {
		$body = '';

		$body .= sprintf('<h1>%s</h1>', $this->name);

		$body .= '<ul class="items">';

		foreach($this->items as $item) {
			$body .= '<li class="white">';
			$body .= $item->printItem();

			$body = str_replace("{{LISTNAME}}", flattenListName($this->name), $body);

			$body .= '</li>';
		}

		// Add a form for adding a new item.
		$body .= sprintf('<form class="singleline white" method="post" action="%s?list=%s" role="form">', htmlspecialchars($_SERVER['PHP_SELF']), flattenListName($this->name));
		$body .= '<input type="text" name="name" placeholder="New item name" style="margin-right: -88px;" required />';
		$body .= '<input type="hidden" name="new-item" />';
		$body .= '<button type="submit" class="blue">New item</button>';
		$body .= '</form>';

		// Add a form for removing the list.
		$body .= '<br class="empty" />';
		$body .= sprintf('<form class="singleline white" method="post" action="%s" role="form">', htmlspecialchars($_SERVER['PHP_SELF']));
		$body .= '<input type="text" name="confirm" placeholder="Type \'delete\'" style="margin-right: -189px;" required />';
		$body .= sprintf('<input type="hidden" name="name" value="%s" />', flattenListName($this->name));
		$body .= '<input type="hidden" name="remove-list" />';
		$body .= '<button type="submit" class="red">Remove the list entirely</button>';
		$body .= '</form>';

		$body .= '</ul>';

		$body .= '<p><a class="button blue" href="?">Back to the overview</a></p>';

		return $body;
	}

	// Adds a new item to the list.
	// TODO: Check if this function is airtight.
	public function addItem($name) {
		$filecontent = file_get_contents($this->filename);
		$lines = explode("\n", $filecontent);

		$end = array_search(ITEMDELIM_END, $lines);
		$lines[$end + 1] = $lines[$end];
		$lines[$end] = sprintf("Item %s", htmlspecialchars($name));

		// Sort the items alphabetically
		$this->sortItems($lines);

		$newcontent = implode("\n", $lines);

		$file = fopen($this->filename, 'w');
		fwrite($file, $newcontent);
		fclose($file);
	}

	// Alters an item in the list.
	// TODO: Check if this function is airtight.
	public function alterItem($linenumber, $newname) {
		$filecontent = file_get_contents($this->filename);
		$lines = explode("\n", $filecontent);

		$lines[$linenumber - 1] = sprintf("Item %s", htmlspecialchars($newname));

		// Sort the items alphabetically
		$this->sortItems($lines);

		$newcontent = implode("\n", $lines);

		$file = fopen($this->filename, 'w');
		fwrite($file, $newcontent);
		fclose($file);
	}

	// Removes an item from the list.
	// TODO: Check if this function is airtight.
	public function removeItem($linenumber) {
		$filecontent = file_get_contents($this->filename);
		$lines = explode("\n", $filecontent);

		unset($lines[$linenumber - 1]);

		$newcontent = implode("\n", $lines);

		$file = fopen($this->filename, 'w');
		fwrite($file, $newcontent);
		fclose($file);
	}

	// Sorts the items in the list.
	private function sortItems(&$lines) {
		$firstitem = array_search(ITEMDELIM_BEGIN, $lines) + 1;
		$lastitem = array_search(ITEMDELIM_END, $lines);

		$header = array_slice($lines, 0, $firstitem);

		$items = array_slice($lines, $firstitem, $lastitem - $firstitem);
		sort($items);

		$footer = array_slice($lines, $lastitem);

		$lines = array_merge($header, $items, $footer);
	}
}

// Container for a single list item.
class ListItem {
	// Contains a name and a line number.
	protected $name, $linenumber;

	// Constructor.
	public function __construct($name, $linenumber) {
		$this->name = $name;
		$this->linenumber = $linenumber;
	}

	// Returns a formatted string representing the item.
	public function printItem() {
		$body = '';

		$body .= sprintf('<div class="content" title="%s"><div class="inner" id="listitem-%s">%s</div></div>', $this->name, $this->linenumber, $this->name);

		// Add a form for removing this item.
		$body .= sprintf('<form class="inline" method="post" action="%s?list=%s" role="form">', htmlspecialchars($_SERVER['PHP_SELF']), "{{LISTNAME}}");
		$body .= sprintf('<input type="hidden" name="linenumber" value="%s" />', $this->getLinenumber());
		$body .= '<input type="hidden" name="remove-item" />';
		$body .= '<button type="submit" class="red">Remove</button>';
		$body .= '</form>';

		return $body;
	}

	// Returns the item's line number.
	public function getLinenumber() {
		return $this->linenumber;
	}

	// Returns the item's name.
	public function getName() {
		return $this->name;
	}
}

// Container for a sublist of items.
class SubList extends ListItem {
	// Contains a list of items, a list of sublists and a colour.
	protected $items;
	protected $sublists;
	protected $colour;

	// Initialiser function.
	public function init() {
		$this->items = array();
		$this->sublists = array();
		// Sets the colour at random.
		$this->colour = COLOURS[rand(0, count(COLOURS) - 1)];
	}

	// Returns a formatted string representing the sublist.
	public function printItem() {
		$body = '';

		$body .= sprintf('<div class="content nofloat %s" title="%s"><div class="inner" id="sublist-%s">%s</div></div>', $this->colour, $this->name, flattenListName($this->name), $this->name);

		$body .= sprintf('<div class="indent">');

		foreach($this->items as $item) {
			$body .= $item->printItem();
		}

		$body .= sprintf('</div>');

		return $body;
	}

	// Adds an item to the sublist.
	public function addItem($name, $linenumber = -2) {
		$name = trim($name);
		// Check if a new sublist is needed
		if (($separator = strpos($name, SUBLISTDELIM)) !== false) {
			$sublistname = substr($name, 0, $separator);
			$sublist = $this->getSublist($sublistname);

			// Add the sublist if it doesn't exist yet.
			if ($sublist === -1) {
				$this->items[] = new SubList($sublistname, $linenumber + 1);
				$this->sublists[$sublistname] = count($this->items) - 1;
				$sublist = $this->getSublist($sublistname);

				$this->items[$sublist]->init();
			}

			$this->items[$sublist]->addItem(substr($name, $separator + 1), $linenumber);
		}
		else {
			$this->items[] = new ListItem($name, $linenumber + 1);
		}
	}

	// Returns the number of items in the sublist.
	// Recursively counts items in the sublists' sublists.
	public function getItemCount() {
		$count = 0;

		foreach($this->items as $item) {
			if ($item instanceof SubList)
				$count += $item->getItemCount();
			else
				$count += 1;
		}
		
		return $count;
	}

	// Returns a given sublist, or -1 on failure.
	public function getSublist($name) {
		if (isset($this->sublists[$name]))
			return $this->sublists[$name];
		return -1;
	}
}

?>