<html xmlns="http://www.w3.org/1999/xhtml">
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
		<title><?php print getTitle() ?> &raquo; <?php print getPage() ?></title>
		<link rel="stylesheet" type="text/css" href="<?php print getCSSURL() ?>" />
	</head>
	<body>
		<div id="navigation">
			<p><a href="<?php print getHomeURL() ?>">Home</a> 
			| <a href="<?php print getGlobalHistoryURL() ?>">History</a>
<?php if ($GIT_REMOTE != "") { ?>
					| <a href="<?php print getPullURL()?>">pull</a>
					| <a href="<?php print getPushURL()?>">push</a>
<?php } ?>
			<?php if (getUser() != "") { ?>| Logged in as <?php print getUser(); } ?>
			</p>
		</div>

		<div id="header">
			<h1 id="title"><?php print getPage() ?></h1>
			<p>[ 
					<a href="<?php print getViewURL($wikiPage)?>">view</a>
					| <a href="<?php print getHistoryURL()?>">history</a>
				]</p>
		</div>

		<div id="content">
			<h2>Git action "<?php print getGitAction(); ?>" output</h2>
			<bre>
			<?php print getContent(); ?>
		</div>

		<div id="footer">
			<p>
				Last modified on <?php print date("F d Y H:i:s"); ?> 
			</p>
		</div>

		<div id="plug">
			<p>
				Powered by <a href="http://el-tramo.be/software/wigit">WiGit</a>
			</p>
		</div>
	</body>
</html>
