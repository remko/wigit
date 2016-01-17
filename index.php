<?php 

	/* 
	 * WiGit
	 * (c) Remko Tronçon (http://el-tramo.be)
	 * See COPYING for details
	 */

	require 'vendor/netcarver/textile/src/Netcarver/Textile/Parser.php';
	require 'vendor/netcarver/textile/src/Netcarver/Textile/DataBag.php';
	require 'vendor/netcarver/textile/src/Netcarver/Textile/Tag.php';

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
		// Workaround for git version < 1.7.7.2 (http://stackoverflow.com/a/9747584/2587532)
		$gitVersion = exec("git --version | awk '{print $3}'");
		if (version_compare($gitVersion, "1.7.7.2", "<") && preg_match("/^(pull)/", $command))
		{
			$output = array();
			// split "pull remote branch" into separat elements
			$elements = explode(" ", $command);
			// execute fetch command
			$command_part1 = "fetch ".$elements[1];
			$result_part1 = git($command_part1, $output);
			// execute merge command
			$command_part2 = "merge ".$elements[1]."/".$elements[2];
			$result_part2 = git($command_part2, $output);

			// check result
			if ($result_part1 == 1 && $result_part2 == 1) {
				return 1;
			}
			else {
				return 0;
			}
		}
		else {
			$gitCommand = "$GIT --git-dir=$gitDir --work-tree=$gitWorkTree $command";
		}
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
		return preg_replace("[^A-Za-z0-9_-\.]", "_", $name);
	}

	function parseResource($resource) {
		global $DEFAULT_PAGE;

		$matches = array();
		$page = "";
		$type = "";
		if (preg_match("~/(.*)/(.*)~", $resource, $matches)) {
			$page = sanitizeName($matches[1]);
			$type = $matches[2];
		}
		else if (preg_match("~/(.*)~", $resource, $matches)) {
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
		$text = preg_replace('@([^:"])(https?://([-\w\.]+)+(:\d+)?(/([%-\w/_\.]*(\?\S+)?)?)?)@', '$1<a href="$2">$2</a>', $text);

		// WikiLinkify
		$text = preg_replace('@\[([\w-\.]+)\]@', '<a href="' . $SCRIPT_URL . '/$1">$1</a>', $text);
		$text = preg_replace('@\[([\w-\.]+)\|([\w-\.\s]+)\]@', '<a href="' . $SCRIPT_URL . '/$1">$2</a>', $text);

		// Textilify
		$textile = new \Netcarver\Textile\Parser();
		return $textile->textileThis($text);
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

	function getGitAction() {
		global $wikiGitAction;
		return $wikiGitAction;
	}

	function getRawData() {
		global $wikiData;
		return $wikiData;
	}

	function getPullURL() {
		global $SCRIPT_URL;
		$page = getPage();
		return "$SCRIPT_URL/$page/pull";
	}

	function getPushURL() {
		global $SCRIPT_URL;
		$page = getPage();
		return "$SCRIPT_URL/$page/push";
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
		// Pull git changes from remote repository
		if ($wikiSubPage == "pull") {
			if (!git("pull $GIT_REMOTE $GIT_BRANCH", $wikiContent)) { return; }
			$wikiContent = implode("<br>\n", $wikiContent);
			$wikiGitAction = "pull $GIT_REMOTE $GIT_BRANCH";
			include(getThemeDir() . "/gitoutput.php");
		}
		// Pull git changes to remote repository
		else if ($wikiSubPage == "push") {
			if (!git("push $GIT_REMOTE $GIT_BRANCH", $wikiContent)) { return; }
			$wikiContent = implode("<br>\n", $wikiContent);
			$wikiGitAction = "push $GIT_REMOTE $GIT_BRANCH";
			include(getThemeDir() . "/gitoutput.php");
		}
		// Global history
		else if ($wikiPage == "history") {
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
