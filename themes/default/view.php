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
			<?php if (getUser() != "") { ?>| Logged in as <?php print getUser(); } ?>
			</p>
		</div>

		<div id="header">
			<h1 id="title"><?php print getPage() ?></h1>
			<p>[ <a href="<?php print getEditURL()?>">edit</a> | 
				   <a href="<?php print getHistoryURL()?>">history</a> ]</p>
		</div>

		<div id="content">
			<?php print $wikiContent; ?>
		</div>

		<div id="footer">
			<p>Last modified on <?php print date("F d Y H:i:s", filemtime($wikiFile)); ?> </p>
		</div>
	</body>
</html>
