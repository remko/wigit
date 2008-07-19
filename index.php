<?php 
	require_once('classTextile.php');

	// Default configuration. FIXME: use isset() and move after source config.php
	$GIT = "git";
	$BASE_URL = "/wigit";
	$SCRIPT_URL = "$BASE_URL/index.php?r=";
	$TITLE = "WiGit";
	$DATA_DIR = "data";
	$DEFAULT_PAGE = "Home";
	$DEFAULT_AUTHOR = 'Anonymous <anonymous@wigit>';
  $AUTHORS = array();

	// Load user config
	if (file_exists('config.php')) {
		require_once('config.php');
	}

	function wikify($text) {
		return $text;
	}

	function getHistory($file = "") {
		$output = array();
		git("log --pretty=format:'%an>%ae>%aD>%s' -- $file", $output);
		$history = array();
		foreach ($output as $line) {
			$logEntry = split(">", $line, 4);
			$history[] = array(
				"author" => $logEntry[0], 
				"email" => $logEntry[1],
				"linked-author" => ($logEntry[1] == "" ? $logEntry[0] : "<a href=\"mailto:$logEntry[1]\">$logEntry[0]</a>"),
				"date" => $logEntry[2], 
				"message" => $logEntry[3]);
		}
		return $history;
	}

	function getAuthorForUser($user) {
		global $AUTHORS, $DEFAULT_AUTHOR;

		if (isset($AUTHORS[$user])) {
			return $AUTHORS[$user];
		}
		else if ($user != "") {
			return "$user <$user@wiggit>";
		}
		return $DEFAULT_AUTHOR;
	}

	function getHTTPUser() {
		// This code is copied from phpMyID. Thanks to the phpMyID dev(s).
		if (function_exists('apache_request_headers') && ini_get('safe_mode') == false) {
			$arh = apache_request_headers();
			$hdr = $arh['Authorization'];
		} elseif (isset($_SERVER['PHP_AUTH_DIGEST'])) {
			$hdr = $_SERVER['PHP_AUTH_DIGEST'];
		} elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
			$hdr = $_SERVER['HTTP_AUTHORIZATION'];
		} elseif (isset($_ENV['PHP_AUTH_DIGEST'])) {
			$hdr = $_ENV['PHP_AUTH_DIGEST'];
		} elseif (isset($_REQUEST['auth'])) {
			$hdr = stripslashes(urldecode($_REQUEST['auth']));
		} else {
			$hdr = null;
		}
		$digest = substr($hdr,0,7) == 'Digest '
			?  substr($hdr, strpos($hdr, ' ') + 1)
			: $hdr;
		if (!is_null($digest)) {
			$hdr = array();
			preg_match_all('/(\w+)=(?:"([^"]+)"|([^\s,]+))/', $digest, $mtx, PREG_SET_ORDER);
			foreach ($mtx as $m) {
				if ($m[1] == "username") {
					return $m[2] ? $m[2] : str_replace("\\\"", "", $m[3]);
				}
			}
		}
		return "";
	}

	function git($command, &$output = "") {
		global $GIT, $DATA_DIR;

		$gitDir = dirname(__FILE__) . "/$DATA_DIR/.git";
		$gitWorkTree = dirname(__FILE__) . "/$DATA_DIR";
		$gitCommand = "$GIT --git-dir=$gitDir --work-tree=$gitWorkTree $command";
		$output = array();
		$result;
		// FIXME: Only do the escaping and the 2>&1 if we're not in safe mode 
		// (otherwise it will be escaped anyway).
		// FIXME: Removed escapeShellCmd because it clashed with author.
		exec($gitCommand . " 2>&1", $output, $result);
		// FIXME: The -1 is a hack to avoid 'commit' on an unchanged repo to
		// fail.
		if ($result != 0 && $result != 1) {
			// FIXME: HTMLify these strings
			print "Error running " . $gitCommand;
			print "Error message: " . join("\n", $output);
			print "Error code: " . $result;
		}
		return 1;
	}

	function sanitizeName($name) {
		return ereg_replace("[^A-Za-z0-9]", "_", $name);
	}

	function parseResource($resource) {
		global $DEFAULT_PAGE;

		$matches = array();
		$page = "";
		$type = "";
		if (ereg("/(.*)/(.*)", $resource, $matches)) {
			$page = sanitizeName($matches[1]);
			$type = $matches[2];
		}
		else if (ereg("/(.*)", $resource, $matches)) {
			$page = sanitizeName($matches[1]);
		}
		if ($page == "") {
			$page = $DEFAULT_PAGE;
		}
		if ($type == "") {
			$type = "view";
		}
		return array("page" => $page, "type" => $type);
	}

	// Get the page
	$resource = parseResource($_GET['r']);

	// Get the HTTP user.
	$user = getHTTPUser();

	// Some common variables
	$wikiTitle = $TITLE;
	$wikiFile = $DATA_DIR . "/" . $resource["page"];
	$wikiFile = $wikiFile;
	$wikiPage = $resource["page"];
	$wikiPageViewURL = "$SCRIPT_URL/$wikiPage";
	$wikiPageEditURL = "$SCRIPT_URL/$wikiPage/edit";
	$wikiPageHistoryURL = "$SCRIPT_URL/$wikiPage/history";
	$wikiHistoryURL = "$SCRIPT_URL/history";
	$wikiCSS = $CSS;
	$wikiHome = "$SCRIPT_URL/";
	$wikiUser = $user;

	if (isset($_POST['data'])) {
		if (trim($_POST['data']) == "") {
			// Delete
			if (file_exists($wikiFile)) {
				if (!git("rm $wikiPage")) { return; }

				$commitMessage = addslashes("Deleted $wikiPage");
				$author = addslashes(getAuthorForUser($user));
				if (!git("commit --message='$commitMessage' --author='$author'")) { return; }
				if (!git("gc")) { return; }
			}
			header("Location: $wikiHome");
			return;
		}
		else {
			// Save
			$handle = fopen($wikiFile, "w");
			fputs($handle, stripslashes($_POST['data']));
			fclose($handle);

			$commitMessage = addslashes("Changed $wikiPage");
			$author = addslashes(getAuthorForUser($user));
			if (!git("init")) { return; }
			if (!git("add $wikiPage")) { return; }
			if (!git("commit --message='$commitMessage' --author='$author'")) { return; }
			if (!git("gc")) { return; }
			header("Location: $wikiPageViewURL");
			return;
		}
	}
	// Get operation
	else {
		// Global history
		if ($resource["page"] == "history") {
			$wikiHistory = getHistory();
			$wikiPage = "";
			include('templates/history.php');
		}
		// Viewing
		else if ($resource["type"] == "view") {
			if (!file_exists($wikiFile)) {
				header("Location: " . $SCRIPT_URL . "/" . $resource["page"] . "/edit");
				return;
			}

			// Open the file
			$handle = fopen($wikiFile, "r");
			$data = fread($handle, filesize($wikiFile));
			fclose($handle);

			// Add wiki links and other transformations
			$wikifiedData = wikify($data);

			// Textilify
			$textile = new Textile();
			$formattedData = $textile->TextileThis($wikifiedData);

			// Put in template
			$wikiContent = $formattedData;
			include('templates/view.php');
		}
		// Editing
		else if ($resource["type"] == "edit") {
			if (file_exists($wikiFile)) {
				$handle = fopen($wikiFile, "r");
				$data = fread($handle, filesize($wikiFile));
			}

			// Put in template
			$wikiData = $data;
			$wikiPagePostURL = "$SCRIPT_URL/$wikiPage";
			include('templates/edit.php');
		}
		else if ($resource["type"] == "history") {
			$wikiHistory = getHistory($wikiPage);
			include('templates/history.php');
		}
		// Error
		else {
			print "Unknown type: " . $resource["type"];
		}
	}

?>
