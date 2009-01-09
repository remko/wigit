<?php 

	/* 
	 * WiGit
	 * (c) Remko Tronçon (http://el-tramo.be)
	 * See COPYING for details
	 */

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

	function getGitHistory($file = "") {
		$output = array();
		// FIXME: Find a better way to find the files that changed than --name-only
		git("log --name-only --pretty=format:'%H>%T>%an>%ae>%aD>%s' -- $file", $output);
		$history = array();
		$historyItem = array();
		foreach ($output as $line) {
			$logEntry = explode(">", $line, 6);
			if (sizeof($logEntry) > 1) {
				// Populate history structure
				$historyItem = array(
						"author" => $logEntry[2], 
						"email" => $logEntry[3],
						"linked-author" => (
								$logEntry[3] == "" ? 
									$logEntry[2] 
									: "<a href=\"mailto:$logEntry[3]\">$logEntry[2]</a>"),
						"date" => $logEntry[4], 
						"message" => $logEntry[5],
						"commit" => $logEntry[0]
					);
			}
			else if (!isset($historyItem["page"])) {
				$historyItem["page"] = $line;
				$history[] = $historyItem;
			}
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
		return $_SERVER['PHP_AUTH_USER'];
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
		$oldUMask = umask(0022);
		exec($gitCommand . " 2>&1", $output, $result);
		$umask = $oldUMask;
		// FIXME: The -1 is a hack to avoid 'commit' on an unchanged repo to
		// fail.
		if ($result != 0) {
			// FIXME: HTMLify these strings
			print "<h1>Error</h1>\n<pre>\n";
			print "$" . $gitCommand . "\n";
			print join("\n", $output) . "\n";
			//print "Error code: " . $result . "\n";
			print "</pre>";
			return 0;
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
	// Wikify
	// --------------------------------------------------------------------------

	function wikify($text) {
		global $SCRIPT_URL;

		// FIXME: Do not apply this in <pre> and <notextile> blocks.

		// Linkify
		$text = preg_replace('@([^:])(https?://([-\w\.]+)+(:\d+)?(/([-\w/_\.]*(\?\S+)?)?)?)@', '$1<a href="$2">$2</a>', $text);

		// WikiLinkify
		$text = preg_replace('@\[([A-Z]\w+)\]@', '<a href="' . $SCRIPT_URL . '/$1">$1</a>', $text);
		$text = preg_replace('@\[([A-Z]\w+)\|([\w\s]+)\]@', '<a href="' . $SCRIPT_URL . '/$1">$2</a>', $text);

		// Textilify
		$textile = new Textile();
		return $textile->TextileThis($text);
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
		global $BASE_URL;
		return "$BASE_URL/" . getThemeDir() . "/style.css";
	}

	function getThemeDir() {
		global $THEME;
		return "themes/$THEME";
	}

	function getFile() {
		global $wikiFile;
		return $wikiFile;
	}

	function getContent() {
		global $wikiContent;
		return $wikiContent;
	}

	function getRawData() {
		global $wikiData;
		return $wikiData;
	}

	// --------------------------------------------------------------------------
	// Initialize globals
	// --------------------------------------------------------------------------

	$wikiUser = getHTTPUser();

	$resource = parseResource($_GET['r']);
	$wikiPage = $resource["page"];
	$wikiSubPage = $resource["type"];
	$wikiFile = $DATA_DIR . "/" . $wikiPage;


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
				if (!git("commit --allow-empty --no-verify --message='$commitMessage' --author='$author'")) { return; }
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
			if (!git("commit --allow-empty --no-verify --message='$commitMessage' --author='$author'")) { return; }
			if (!git("gc")) { return; }
			header("Location: " . getViewURL($wikiPage));
			return;
		}
	}
	// Get operation
	else {
		// Global history
		if ($wikiPage == "history") {
			$wikiHistory = getGitHistory();
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

			// Put in template
			$wikiContent = wikify($data);
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
		// History
		else if ($wikiSubPage == "history") {
			$wikiHistory = getGitHistory($wikiPage);
			include(getThemeDir() . "/history.php");
		}
		// Specific version
		else if (eregi("[0-9A-F]{20,20}", $wikiSubPage)) {
			$output = array();
			if (!git("cat-file -p " . $wikiSubPage . ":$wikiPage", $output)) {
				return;
			}
			$wikiContent = wikify(join("\n", $output));
			include(getThemeDir() . "/view.php");
		}
		else {
			print "Unknow subpage: " . $wikiSubPage;
		}
	}

?>
