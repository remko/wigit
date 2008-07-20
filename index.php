<?php 
	require_once('classTextile.php');

	// --------------------------------------------------------------------------
	// Configuration
	// --------------------------------------------------------------------------

	if (file_exists('config.php')) {
		require_once('config.php');
	}
	if (!isset($GIT)) { $GIT = "git"; }
	if (!isset($BASE_URL)) { $BASE_URL = "/wigit"; }
	if (!isset($SCRIPT_URL)) { $SCRIPT_URL = "$BASE_URL/index.php?r="; }
	if (!isset($TITLE)) { $TITLE = "WiGit"; }
	if (!isset($DATA_DIR)) { $DATA_DIR = "data"; }
	if (!isset($DEFAULT_PAGE)) { $DEFAULT_PAGE = "Home"; }
	if (!isset($DEFAULT_AUTHOR)) { $DEFAULT_AUTHOR = 'Anonymous <anonymous@wigit>'; }
  if (!isset($AUTHORS)) { $AUTHORS = array(); }
  if (!isset($THEME)) { $THEME = "default"; }


	// --------------------------------------------------------------------------
	// Helpers
	// --------------------------------------------------------------------------

	function wikify($text) {
		return $text;
	}

	function getHistory($file = "") {
		$output = array();
		git("log --pretty=format:'%H>%T>%an>%ae>%aD>%s' -- $file", $output);
		$history = array();
		foreach ($output as $line) {
			$logEntry = explode(">", $line, 6);

			// Find out which file was edited
			$treeOutput = array();
			if (!git("ls-tree ". $logEntry[1], $treeOutput) || sizeof($treeOutput) == 0) {
				continue;
			}
			$page = end(split("\x09", $treeOutput[0]));

			// Populate history structure
			$history[] = array(
					"author" => $logEntry[2], 
					"email" => $logEntry[3],
					"linked-author" => (
							$logEntry[3] == "" ? 
								$logEntry[2] 
								: "<a href=\"mailto:$logEntry[3]\">$logEntry[2]</a>"),
					"date" => $logEntry[4], 
					"message" => $logEntry[5],
					"page" => $page,
					"commit" => $logEntry[0]
				);
		}
		return $history;
	}

	function getThemeDir() {
		global $THEME;
		return "themes/$THEME";
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

	// --------------------------------------------------------------------------
	// Utility functions (for use inside templates)
	// --------------------------------------------------------------------------
	
	function getViewURL($page, $version = null) {
		global $SCRIPT_URL;
		if ($version) {
			return "$SCRIPT_URL/$page/$version";
		}
		else {
			return "$SCRIPT_URL/$page";
		}
	}

	function getPostURL() {
		global $SCRIPT_URL;
		$page = getPage();
		return "$SCRIPT_URL/$page";
	}

	function getEditURL() {
		global $SCRIPT_URL;
		$page = getPage();
		return "$SCRIPT_URL/$page/edit";
	}

	function getHistoryURL() {
		global $SCRIPT_URL;
		$page = getPage();
		return "$SCRIPT_URL/$page/history";
	}
	
	function getGlobalHistoryURL() {
		global $SCRIPT_URL;
		return "$SCRIPT_URL/history";
	}

	function getHomeURL() {
		global $SCRIPT_URL;
		return "$SCRIPT_URL/";
	}

	function getUser() {
		global $wikiUser;
		return $wikiUser;
	}

	function getTitle() {
		global $TITLE;
		return $TITLE;
	}

	function getPage() {
		global $wikiPage;
		return $wikiPage;
	}

	function getCSSURL() {
		return getThemeDir() . "/style.css";
	}

	// --------------------------------------------------------------------------
	// Initialize globals
	// --------------------------------------------------------------------------

	$wikiUser = getHTTPUser();

	$resource = parseResource($_GET['r']);
	$wikiPage = $resource["page"];
	$wikiSubPage = $resource["type"];

	$wikiFile = $DATA_DIR . "/" . $wikiPage;
	$wikiCSS = $CSS;


	// --------------------------------------------------------------------------
	// Process request
	// --------------------------------------------------------------------------

	if (isset($_POST['data'])) {
		if (trim($_POST['data']) == "") {
			// Delete
			if (file_exists($wikiFile)) {
				if (!git("rm $wikiPage")) { return; }

				$commitMessage = addslashes("Deleted $wikiPage");
				$author = addslashes(getAuthorForUser(getUser()));
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
			$author = addslashes(getAuthorForUser(getUser()));
			if (!git("init")) { return; }
			if (!git("add $wikiPage")) { return; }
			if (!git("commit --message='$commitMessage' --author='$author'")) { return; }
			if (!git("gc")) { return; }
			header("Location: " . getViewURL($wikiPage));
			return;
		}
	}
	// Get operation
	else {
		// Global history
		if ($wikiPage == "history") {
			$wikiHistory = getHistory();
			$wikiPage = "";
			include(getThemeDir() . "/history.php");
		}
		// Viewing
		else if ($wikiSubPage == "view") {
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
			include(getThemeDir() . "/view.php");
		}
		// Editing
		else if ($wikiSubPage == "edit") {
			if (file_exists($wikiFile)) {
				$handle = fopen($wikiFile, "r");
				$data = fread($handle, filesize($wikiFile));
			}

			// Put in template
			$wikiData = $data;
			include(getThemeDir() . "/edit.php");
		}
		else if ($wikiSubPage == "history") {
			$wikiHistory = getHistory($wikiPage);
			include(getThemeDir() . "/history.php");
		}
		else {
			// Try commit.
			// FIXME: Try to put this in an else if
			$output = array();
			if (git("cat-file -p " . $wikiSubPage . ":$wikiPage", $output)) {
				$data = join("\n", $output);
				// FIXME Factor this out
				// Add wiki links and other transformations
				$wikifiedData = wikify($data);

				// Textilify
				$textile = new Textile();
				$formattedData = $textile->TextileThis($wikifiedData);

				// Put in template
				// FIXME: Remove edit links
				$wikiContent = $formattedData;
				include(getThemeDir() . "/view.php");
				return;
			}

			// Fallback
			print "Unknow subpage: " . $wikiSubPage;
		}
	}

?>
