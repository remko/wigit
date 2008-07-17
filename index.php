<?php 
  require_once('classTextile.php');
  require_once('config.php');

  function filterPageName($page) {
    // FIXME
    return $page;
  }

  function parseResource($resource) {
    $matches = array();
    $page = "";
    $type = "";
    if (ereg("/(.*)/(.*)", $resource, $matches)) {
      $page = filterPageName($matches[1]);
      $type = $matches[2];
    }
    else if (ereg("/(.*)", $resource, $matches)) {
      $page = filterPageName($matches[1]);
    }
    if ($page == "") {
      $page = $DEFAULT_PAGE;
    }
    if ($type == "") {
      $type = "view";
    }
    return array("page" => $page, "type" => $type);
  }


  // Get operation
  if ($_GET['r']) {
    $resource = parseResource($_GET['r']);
    $file = $DATA_DIR . "/" . $resource["page"];

    // Viewing
    if ($resource["type"] == "view") {
      if (!file_exists($file)) {
        header("Location: " . $URL_PREFIX . "/" . $resource["page"] . "/edit");
        return;
      }

      // Open the file
      $handle = fopen($file, "r");
      $data = fread($handle, filesize($file));

      // Add wiki links
      $wikiLinkedData = $data;

      // Textilify
      $textile = new Textile();
      $formattedData = $textile->TextileThis($wikiLinkedData);

      // Put in template
      print $formattedData;
    }
    // Editing
    else if ($resource["type"] == "edit") {
      print "Editing " . $resource["page"];
    }
    // Error
    else {
      print "Unknown type: " . $resource["type"];
    }
  }

  // Post
  else if ($_POST['r']) {
    $resource = parseResource($_GET['r']);
    $file = $DATA_DIR . "/" . $resource["page"];
    if (file_exists($file)) {
      // Edit & commit it
    }
    else {
      // Add & commit it
    }
  }

?>
