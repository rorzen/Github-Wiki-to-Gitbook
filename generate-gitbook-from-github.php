#!/usr/bin/php
<?php
/*
Marc Farré
https://marc.fun

https://gitlab.com/funkycram/git-wiki-to-gitbook

version 0.8 (2017-09-23) // take the name of the page from the # title to create the summary

Version 0.7 (2017-09-18) // capacity to read pages in folders and generate the summary from this structure
Migration instructions from 0.6 :
- edit book.json
- config.inc.php -> config.json
- Book has moved from _book to book/_book (update apache and cron config)
- manually update wiki link has changed : https://website.ext/git-wiki-to-gitbook/generate-gitbook.php -> https://website.ext/update-gitbook.php

*/

$config = (object) array();
$config->rootDir = dirname ( __FILE__ , 2); // parent folder of $config->scriptDir
$config->scriptDir = dirname ( __FILE__ );
$config->bookDir = $config->rootDir . '/book';
$config->finalBookDir = $config->rootDir . '/book/_book';
$config = (object) array_merge ((array) $config, (array) json_decode(file_get_contents($config->scriptDir . "/config.json")));
if ($config->gitLabGroup != "") { // Project is on GitLab
	$config->isGitLab = true;
	$config->isGitHub = false;
	$config->gitWikiURL = "https://gitlab.com/".$config->gitLabGroup."/".$config->gitLabProject."/wikis";
	$config->wikiDir = $config->rootDir . "/" .$config->gitLabProject . ".wiki";
	$config->homeFileName = "home.md";
	$config->summaryFileName = "SUMMARY.md";
	$config->footerFileName = "FOOTER.md";
	$config->editLastURLPart = "edit";
}
else { // Repository is on GitHub
	$config->isGitHub = true;
	$config->isGitLab = false;
	$config->gitWikiURL = "https://github.com/".$config->gitHubOrganization."/".$config->gitHubRepositorie."/wiki";
	$config->wikiDir = $config->rootDir . "/" .$config->gitHubRepositorie . ".wiki";
	$config->homeFileName = "Home.md";
	$config->summaryFileName = "_Sidebar.md";
	$config->footerFileName = "_Footer.md";
	$config->editLastURLPart = "_edit";
}

if (file_exists($config->bookDir)) {
	system ("find ".$config->bookDir."/* -type f ! -name 'update-gitbook.php' -delete; find ".$config->bookDir."/* -type d ! -name '_book' -delete");
}
else
	mkdir ($config->bookDir);
copy ($config->rootDir . "/book.json", $config->bookDir . "/book.json");
if (file_exists($config->rootDir. "/LANGS.md")) {
	$config->bookIsMultilingual = true;
	copy ($config->rootDir . "/LANGS.md", $config->bookDir . "/LANGS.md");
}
else
	$config->bookIsMultilingual = false;


// Define GitBook structure (independant from GitHub or GitLab)
$bookParams = json_decode(file_get_contents($config->bookDir . '/book.json'),true);
$config->bookSummaryFileName = $bookParams["structure"]["summary"];
$config->bookHomeFileName = $bookParams["structure"]["readme"];

$summarysArray = array (); // contains the list of summaries and the list of links inside each SUMMARY.md files

function scandirSorted($path) {
	$fileList = array();
    $dirList = array();
    foreach(scandir($path) as $file) {
        if (is_dir($path . "/" . $file) OR is_dir($path . $file))
            $dirList[] = $file;
        else
            $fileList[] = $file;
    }
    return array_merge ($fileList, $dirList);
}

function recursiveScanDir ($dir, $exclude = array (), $convertToLower = true) {
	global $filesList;
	foreach (array_diff (preg_grep ('/^([^.])/', scandir($dir)), array (".", ".."), $exclude) as $fileName) {
		$filePath = $dir . "/" . $fileName;
		if (is_dir($filePath))
			recursiveScanDir ($filePath);
		else {
			if ($convertToLower)
				$filesList[] = strtolower($filePath);
			else
				$filesList[] = $filePath;
		}
	}
}

function convertFile ($from, $to, $isSummary = false) {
	global $summarysArray, $config, $error;

	if (file_exists($from)) {
		$content = file_get_contents($from);

		// Replacing internal links
		if ($config->isGitHub) {
			$contentArray = explode('[[', $content);
			$content = $contentArray[0];
			for ($i=1; $i < (count($contentArray)); $i++) {
				$valueArray = explode(']]', $contentArray[$i], 2);
				$fileName = str_ireplace(" ", "-", $valueArray[0]);
				if (strpos($fileName.".md", $config->homeFileName) != FALSE) // if the link point to a home page
					$fileName = str_ireplace("/".basename($config->homeFileName, ".md"), "/".basename($config->bookHomeFileName, ".md"), $fileName); // rename this link to the $config->bookHomeFileName
				$content .= "[".$valueArray[0]."](".strtolower($fileName);
				if ($isSummary) {
					$content .= ".md)";
					$summarysArray[$to]["customList"][] = $fileName.".md";
				}
				else
					$content .= ".html)";
				$content .= $valueArray[1];
			}		
		}
		else { //isGitLab
			$contentArray = explode('](', $content);
			$content = $contentArray[0];
			for ($i=1; $i < (count($contentArray)); $i++) {
				$valueArray = explode(')', $contentArray[$i], 2);
				$fileName = str_ireplace(" ", "-", $valueArray[0]);
				if (strpos($fileName.".md", $config->homeFileName) != FALSE) // if the link point to a home page
					$fileName = str_ireplace("/".basename($config->homeFileName, ".md"), "/".basename($config->bookHomeFileName, ".md"), $fileName); // rename this link to the $config->bookHomeFileName
				if (substr($fileName,0,8) == "/uploads") {
					$content .= "](https://gitlab.com/" . $config->gitLabGroup . "/" . $config->gitLabProject . $fileName . ")" . $valueArray[1];
				}
				elseif (substr($fileName,0,4) != "http") { // if it's an internal link
					$fileName = str_ireplace(".md", "", $fileName).".md"; // Internal links can finish by .md or not
					if (substr($fileName, 0, 1) == "/")
						$fileName = substr($fileName, 1); // remove an eventual / at the beginning
					if ($isSummary)
						$summarysArray[$to]["customList"][] = $fileName;
					if (in_array(strtolower($config->wikiDir . "/" . str_ireplace($config->bookHomeFileName, $config->homeFileName, $fileName)), $GLOBALS["listOfAllMDFiles"])) { // If the link point to an existing page
						$link = "";
						for ($j=0; $j < substr_count (substr(str_ireplace($config->bookDir, "", $to), 1), "/"); $j++) // substr to remove an eventual / at the beginning of the link
							$link .= "../";
						$link .= substr(str_ireplace($config->bookHomeFileName, "index.md", $fileName), 0, -3) . ".html";
					}
					else
						$link = $config->gitWikiURL . "/" . substr($fileName, 0, -3) . "?postTreatmentHookAddClassCreatePageOnGitLab";
					$content .= "](" . strtolower($link) . ")" . $valueArray[1];
				}
				else
					$content .= ']('.$contentArray[$i];
			}
		}

		// Deleting all betwin "(!--" and "--)"
		$contentArray = explode("(!--", $content);
		$content = $contentArray[0];
		for ($i=1; $i < (count($contentArray)); $i++) {
			$valueArray = explode('--)', $contentArray[$i], 2);
			$content .= $valueArray[1];
		}

		if ($isSummary) {
			$content = str_ireplace("* [", "    * [", $content);
			$content = str_ireplace('###### ', "$$$ ", $content);
			$content = str_ireplace('##### ', "$$$ ", $content);
			$content = str_ireplace('#### ', "$$$ ", $content);
			$content = str_ireplace('### ', "$$$ ", $content);
			$content = str_ireplace('## ', "$$$ ", $content);
			$content = str_ireplace('# ', "$$$ ", $content);

			$contentArray = explode('$$$', $content);
			$content = $contentArray[0];
			for ($i=1; $i < (count($contentArray)); $i++) {
				$value = $contentArray[$i];
				if (strpos($value, '[') < strpos($value, '*'))
					$content .= '* '.strstr($value,'[');
				else
					$content .= '*'.$value;
			}
		}

		file_put_contents (strtolower($to), $content);
	}
	else
		$error[] = $from;
}

system('git -C '.$config->wikiDir.' pull;');

// Convert all MD files
function recursiveConvertMDFiles ($dir) {
	global $config, $summarysArray;

	foreach ( array_diff (preg_grep ('/^([^.])/', scandir($dir)), array (".", "..", $config->footerFileName) ) as $fileName) {
		$filePath = $dir . "/" . $fileName;
		if (is_dir($filePath)) {
			mkdir (str_ireplace($config->wikiDir, $config->bookDir, $filePath));
			recursiveConvertMDFiles ($filePath);
		}
		else {
			$dirBook = str_ireplace($config->wikiDir, $config->bookDir, $dir);
			if ($fileName == $config->homeFileName) {
				if ($dir == $config->wikiDir OR ($config->bookIsMultilingual AND dirname($dir) == $config->wikiDir)) // if it is the root README.md or a README.md for the root lang directory
					convertFile ($filePath, $dirBook."/".$config->bookHomeFileName);
				else
					convertFile ($filePath, $dirBook."/index.md");
			}
			elseif ($fileName == $config->summaryFileName) {
				$summarysArray[$dirBook."/".$config->bookSummaryFileName]["dir"] = $dirBook;
				convertFile ($filePath, $dirBook."/".$config->bookSummaryFileName, true);
			}
			elseif (substr($fileName, -3) == ".md")
				convertFile ($filePath, $dirBook."/".strtolower($fileName));
		}
	}
}

$filesList = array ();
recursiveScanDir ($config->wikiDir);

$GLOBALS["listOfAllMDFiles"] = $filesList;
recursiveConvertMDFiles ($config->wikiDir, $filesList);


// Adding lacking summaries files
if (!isset($summarysArray[$config->bookDir."/".$config->bookSummaryFileName])) {
	$summarysArray[$config->bookDir."/".$config->bookSummaryFileName]["dir"] = $config->bookDir;
	$summarysArray[$config->bookDir."/".$config->bookSummaryFileName]["customList"] = array ();
}
if ($config->bookIsMultilingual)
	foreach ( array_diff (preg_grep ('/^([^.])/', scandir($config->bookDir)), array (".", "..", "_book") ) as $dirName) // preg_grep ('/^([^.])/' exclude the hidden files
		if (is_dir($config->bookDir."/".$dirName))
			if (!isset($summarysArray[$config->bookDir."/".$dirName."/".$config->bookSummaryFileName])) {
				$summarysArray[$config->bookDir."/".$dirName."/".$config->bookSummaryFileName]["dir"] = $config->bookDir."/".$dirName;
				$summarysArray[$config->bookDir."/".$dirName."/".$config->bookSummaryFileName]["customList"] = array ();
			}


// Adding lacking internal links in the summaries files
function recursiveScanDirForSummary ($dir) {
	global $pagesToAdd, $summaryArray, $config;
	$fileList = array_diff(scandirSorted ($dir), array("index.md")); // put index.md at the begining, even if not exist (gitbook will ignore it)
	if (!in_array($config->bookHomeFileName, $fileList))
		array_unshift ($fileList, "index.md");
	foreach ( array_diff (preg_grep ('/^([^.])/', $fileList), array (".", "..", $config->bookHomeFileName) ) as $fileName) {
		$filePath = $dir . "/" . $fileName;
		if (is_dir($filePath))
			recursiveScanDirForSummary ($filePath);
		elseif (substr($fileName, -3) == ".md") {
			//echo substr("etc/pas/swd.md", strpos("etc/pas/swd.md", "/"));
			
			$fileLink = str_ireplace($config->bookDir."/", "", $filePath);
			$slashPos = strpos($fileLink, "/"); // $filePath nether starts with a /
			if ($slashPos != FALSE AND $config->bookIsMultilingual) // file is not in the root and the book is devided in several books, on for each language
				$fileLink = substr($fileLink, $slashPos+1);

			if (!in_array($fileLink, $summaryArray["customList"]))
				$pagesToAdd[] = array ($filePath, $fileLink);
		}
	}
}
foreach ($summarysArray as $summaryPath => $summaryArray) {
	$pagesToAdd = array ();
	recursiveScanDirForSummary ($summaryArray["dir"]);
	if ($summaryArray["dir"] == $config->bookDir)
		$numberOfConvertedPages = sizeof($pagesToAdd);
	$content = "";
	if (file_exists($summaryPath))
		$content .= file_get_contents($summaryPath) . "\n* " . $config->summaryOtherPagesChapterLabel;
	else
		$content = "# Summary\n\n";
//		$content .= "\n* " . $config->summaryLabel;

	$pageDir = "";
	foreach ($pagesToAdd as $pageToAdd) {
		$filePath = $pageToAdd[0];
		$fileLink = $pageToAdd[1];

		if ($fileLink != "index.md" && $fileLink != "_book/index.md") {
			$pageTitle = "";
			if (file_exists($filePath)) {
				$tempContent = file_get_contents($filePath);
				if (substr($tempContent, 0, 2) == "# ") {
					$start = 2;
					$end = strpos($tempContent, "\n");
					if ($end === FALSE) // If the page has only one line
						$end = strlen($tempContent);
					$pageTitle = substr($tempContent, $start, $end - $start);
				}
			}

			$indent = "";
			for ($j=0; $j < substr_count ($fileLink, "/"); $j++)
				$indent .= "  "; // Will make <ul> inside <li> for a hierarchical summary
			if ($config->isGitLab && $pageDir != dirname($fileLink)) { // if it's a title in the summary (name of the directory)
				if ($pageTitle == "")
					$pageTitle = ucfirst(str_ireplace("-", " ", basename (dirname ($fileLink))));
				$content .= "\n" . $indent . "* [postTreatmentHookAddClassSummaryTitleStart" . ucfirst($pageTitle) . "postTreatmentHookAddClassSummaryTitleStop](" . $fileLink . ")";
				$pageDir = dirname($fileLink);
			}
			else {
				if ($pageTitle == "")
					$pageTitle = ucfirst(str_ireplace("-", " ", basename($fileLink, ".md")));
				$content .= "\n" . $indent . "  * [" . str_ireplace("-", " ", ucfirst($pageTitle)) . "](" . $fileLink . ")";
			}
		}
	}
	file_put_contents ($summaryPath, $content);
}

?>

<!DOCTYPE html>
<html class="client-nojs" lang="fr" dir="ltr">
<head>
<meta charset="UTF-8"/>
<title>Git Wiki to Gitbook generator</title>
</head>
<body>
<h1>Finished ! Your Gitbook manual has been generated from the <?php if ($config->isGitLab) echo "GitLab"; else echo "GitHub"; ?> Wiki.</h1>
<br />
<p><?php echo $numberOfConvertedPages; ?> pages have been converted and imported.</p>
<?php
	if (sizeof($error) > 0) {
		?><p style="color: red; font-weight: bold;">CAUTION ! The following internal links of the Git wiki sidebar don't correspond to a valid name of a wiki page (case sensitive) :</p>
		<ul><?php
		foreach ($error as $key => $value)
			echo "<li>".$value."</li>";
		?></ul><?php
	}
?>
<br />
<h2>IMPORTANT :</h2>
<p><b>Now check <a href="<?php echo dirname($_SERVER['PHP_SELF']); ?>">the generated Gitbook wiki</a> to check if everithing ok.</b></p>
<br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br />

<?php
system('
	/usr/bin/gitbook build '.$config->bookDir.';
	cp '.$config->scriptDir.'/for-the-book-folder.php '.$config->finalBookDir.'/update-gitbook.php;
	date > '.$config->rootDir.'/last-update-date.txt;
');
foreach ($summarysArray as $summaryPath => $summaryArray) {
	if (substr($summaryArray["dir"], -5) == "/book")
		$finalBookDir = $summaryArray["dir"] . "/_book";
	else
		$finalBookDir = dirname($summaryArray["dir"]) . "/_book/" . basename($summaryArray["dir"]);
	system('cp -r '.$config->scriptDir.'/gitbook-custom-template/* '.$finalBookDir.'/gitbook;');
}

//system('/usr/bin/gitbook pdf '.$config->bookDir.' '.$config->finalBookDir.'/'.$pdfBookName.'.pdf;'); // Commented because it doesn't work if this script is called from a browser


// POST TREATMENTS

// Adding Footer
if ($config->bookIsMultilingual) {
	$langArray = explode ("](", file_get_contents ($config->bookDir . "/LANGS.md"));
	for ($i=1; $i < count($langArray) ; $i++) {
		$lang = explode("/)", $langArray[$i]);
		$lang = $lang[0];
		if (file_exists($config->wikiDir."/".$lang."/".$config->footerFileName))
			$footerText[$lang] = str_replace('<a href=', '<a target="_blank" href=', shell_exec("markdown -b ".$config->wikiDir."/".$lang."/".$config->footerFileName));
		else
			$footerText[$lang] = "";
	}
}
else {
	if (file_exists($config->wikiDir."/".$config->footerFileName))
		$footerText[""] = str_replace('<a href=', '<a target="_blank" href=', shell_exec("markdown -b ".$config->wikiDir."/".$config->footerFileName));
	else
		$footerText[""] = "";
}

$filesList = array ();
recursiveScanDir ($config->finalBookDir, array ("update-gitbook.php", "gitbook"));
foreach ($filesList as $filePath) {
	if (substr($filePath, -5) == ".html") {
		$content = file_get_contents($filePath);
		$pageName = basename(str_ireplace("index.html", basename($config->homeFileName, ".md").".html", basename ($filePath)), ".html");
		$pageLink = str_ireplace($config->finalBookDir . "/", "", $filePath);
		$relativeDir = "";
		for ($j=0; $j < (substr_count (substr($pageLink, 1), "/")); $j++) // substr to remove an eventual / at the beginning of the link
			$relativeDir .= "../";
		if ($config->bookIsMultilingual)
			$lang = substr($pageLink, 0, strpos($pageLink, "/"));
		else
			$lang = "";

		// Adding custom CSS file
		$content = str_ireplace(
			'gitbook/style.css">',
			'gitbook/style.css"> <link id="custom-app-css-file" rel="stylesheet" href="' . $relativeDir . 'gitbook/custom-style.css">',
			$content);
		// Adding custom JS
		$content = str_ireplace( // part reloaded on page change, by ajax
			'</nav>',
			'</nav>
			<script type="text/javascript">
				if (typeof loadCustomFunction !== "undefined")
					loadCustomFunction ();	
			</script>',
			$content);
		$content = str_ireplace( // part not reloaded on page change
			'gitbook/app.js"></script>',
			'gitbook/app.js"></script>
			<script id="custom-app-js-file" src="' . $relativeDir . 'gitbook/custom-app.js"></script>',
			$content);

		if ($config->bookIsMultilingual AND $pageLink == "index.html") { // Page to select the language
			// Later enhancements to do
		}
		else {
			// Replace Documentation title by Page title in the top bar
			$contentArray = explode("<h1", $content);
			$pageOriginalTitle = substr($contentArray[1], strpos($contentArray[1], ">")+1, strpos($contentArray[1], "</h1>")-strpos($contentArray[1], ">")-1);
			if (isset($contentArray[2])) // If a # header exist in the wiki page
				$pageNewTitle = substr($contentArray[2], strpos($contentArray[2], ">")+1, strpos($contentArray[2], "</h1>")-strpos($contentArray[2], ">")-1);
			else
				$pageNewTitle = str_ireplace("-", " ", $pageName);
			$content = str_ireplace(
				"<h1>".$pageOriginalTitle."</h1>",
				"<h1>".ucfirst($pageNewTitle)."</h1>",
				$content);
			// If is a summary title
			$content = str_ireplace(
				'<title>postTreatmentHookAddClassSummaryTitleStart', // because of <title>
				'<title>',
				$content);
			$content = str_ireplace(
				'postTreatmentHookAddClassSummaryTitleStop |', // because of <title>
				' |',
				$content);
			$content = str_ireplace(
				'"postTreatmentHookAddClassSummaryTitleStart', // because of data-chapter-title=
				'"',
				$content);
			$content = str_ireplace(
				'postTreatmentHookAddClassSummaryTitleStop"', // because of data-chapter-title= and Previous page: 
				'"',
				$content);
			$content = str_ireplace(
				': postTreatmentHookAddClassSummaryTitleStart', // because of Previous page: 
				': ',
				$content);
			$content = str_ireplace(
				'postTreatmentHookAddClassSummaryTitleStart',
				'<span class="summary-title">',
				$content);
			$content = str_ireplace(
				'postTreatmentHookAddClassSummaryTitleStop',
				'</span>',
				$content);
			// If internal link don't exist on GitLab
			$content = str_ireplace(
				'?postTreatmentHookAddClassCreatePageOnGitLab"',
				'" class="create-page-on-gitLab"',
				$content);
			// Adding edit button to the summary
			$content = str_ireplace(
				'<nav role="navigation">',
				'<nav role="navigation"><a aria-label="" href="'.$config->gitWikiURL.'/'.$lang.'/'.str_ireplace(".md","",$config->summaryFileName).'/'.$config->editLastURLPart.'" target="_blank" class="btn pull-right"><i class="fa fa-pencil"></i></a>',
				$content);
			// Adding edit button to the page
			$content = str_ireplace(
				'<!-- Title -->',
				'<a aria-label="" href="'.$config->gitWikiURL.'/'.dirName($pageLink).'/'.$pageName.'/'.$config->editLastURLPart.'" target="_blank" class="btn pull-left"><i class="fa fa-pencil"></i></a><!-- Title -->',
				$content);
			// Adding text and edit button to the footer
			$content = str_ireplace(
				'</section>',
				'<footer><a aria-label="" href="'.$config->gitWikiURL.'/'.$lang.'/'.str_ireplace(".md","",$config->footerFileName).'/'.$config->editLastURLPart.'" target="_blank" class="btn pull-right"><i class="fa fa-pencil"></i></a> '.$footerText[$lang].'</footer></section>',
				$content);
			
		}
		// Replacing the gitbook signature
		$content = str_ireplace(
			'https://www.gitbook.com',
			'https://gitlab.com/funkycram/git-wiki-to-gitbook',
			$content);
		$content = str_ireplace(
			' GitBook
',
			' Git Wiki to Gitbook
',
			$content);

		file_put_contents ($filePath, $content);
	}
}

?>

</body>
</html>
