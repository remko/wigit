<html xmlns="http://www.w3.org/1999/xhtml">
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
		<title><?php print $wikiTitle ?> &raquo; <?php print $wikiPage ?></title>
		<link rel="stylesheet" type="text/css" href="<?php print $wikiCSS ?>" />
	</head>
	<body>
		<div id="navigation">
			<p><a href="<?php print $wikiHome ?>">Home</a></p>
		</div>

		<div id="header">
			<h1 id="title"><?php print $wikiPage ?></h1>
			<p><a href="<?php print $wikiPageEditURL?>">(edit)</a></p>
		</div>

		<div id="content">
			<?php print $wikiContent; ?>
		</div>

		<div id="footer">
			<p>Last modified on <?php print date("F d Y H:i:s", filemtime($wikiFile)); ?> </p>
			<?php if ($wikiUser != "") { ?>
				<p>Logged in as <?php print $wikiUser; ?></p>
			<?php } ?>
		</div>
	</body>
</html>

