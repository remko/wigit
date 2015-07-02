<html xmlns="http://www.w3.org/1999/xhtml">
	<?php $historyTitle = "History" . (getPage() == "" ? "" : " of " . getPage()); ?>

	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
		<title><?php print getTitle() ?> &raquo; <?php print $historyTitle ?></title>
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
			<h1 id="title"><?php print $historyTitle ?></h1>
			<p>[ <a href="<?php print getViewURL(getPage())?>">view</a> |
				<a href="<?php print getEditURL()?>">edit</a> ]</p>
		</div>

		<div id="history">
			<p>
			<table>
				<tr><th>Date</th><th>Author</th><th>Page</th><th>Message</th></tr>
			<?php 
				foreach ($wikiHistory as $item) {
					print "<tr>"
						. "<td>" . $item["date"] . "</td>"
						. "<td class='author'>" . $item["linked-author"] . "</td>"
						. "<td class='page'><a href=\"" . getViewURL($item["page"]) . "\">" . $item["page"] . "</a></td>"
						. "<td>" . $item["message"] . "</td>"
						. "<td>" . "<a href=\"" . getViewURL($item["page"], $item["commit"]) . "\">View</a></td>"
						. "<td>" . "</td>"
						. "</tr>\n";
				}
			?>
			</table>
			</p>
		</div>
		<div id="plug">
			<p>
				Powered by <a href="http://el-tramo.be/software/wigit">WiGit</a>
			</p>
		</div>
	</body>
</html>
