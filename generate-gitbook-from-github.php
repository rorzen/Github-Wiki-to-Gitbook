#!/usr/bin/php
<?php
/*
Marc Farré
https://marc.fun

https://github.com/marc-fun/Github-Wiki-to-Gitbook
Version 0.3
2017-08-25
*/

require_once(dirname(__FILE__) . "/config.inc.php");


if ($_SERVER["DOCUMENT_ROOT"] != '') // If the script is executed from a web browser at this URL : www.yoursite.ext/Github-Wiki-to-Gitbook/generate-gitbook-from-github.php
	$bookPath = str_ireplace("/_book", "", $_SERVER["DOCUMENT_ROOT"]);

function readContent ($fileName) {
	$handle = fopen($fileName, "r");
	$content = fread($handle, filesize($fileName));
	fclose($handle);
	return $content;
}

function writeContent ($fileName, $content) {
	$fp = fopen($fileName, 'w');
	fwrite($fp, $content);
	fclose($fp);
}

function convertSyntax ($content, $isSummary = false) {
	global $mdFileListArray;

	$contentArray = explode('[[', $content);
	$newContent = $contentArray[0];
	for ($i=1; $i < (count($contentArray)); $i++) {
		$valueArray = explode(']]', $contentArray[$i]);
		$fileName = str_ireplace(" ", "-", $valueArray[0]);
		$newContent .= "[".$valueArray[0]."](".$fileName;
		if ($isSummary) {
			$newContent .= ".md)";
			$mdFileListArray[] = $fileName.".md";
		}
		else
			$newContent .= ".html)";
		$newContent .= $valueArray[1];
	}

	if ($isSummary) {
		$newContent = str_ireplace("* [", "    * [", $newContent);
		$newContent = str_ireplace('###### ', "$$$ ", $newContent);
		$newContent = str_ireplace('##### ', "$$$ ", $newContent);
		$newContent = str_ireplace('#### ', "$$$ ", $newContent);
		$newContent = str_ireplace('### ', "$$$ ", $newContent);
		$newContent = str_ireplace('## ', "$$$ ", $newContent);
		$newContent = str_ireplace('# ', "$$$ ", $newContent);

		$contentArray = explode('$$$', $newContent);
		$newContent = '# Sommaire

'.$contentArray[0];
		for ($i=1; $i < (count($contentArray)); $i++) {
			$value = $contentArray[$i];
			if (strpos($value, '[') < strpos($value, '*'))
				$newContent .= '* '.strstr($value,'[');
			else
				$newContent .= '*'.$value;
		}
	}
	return $newContent;
}

function convertFile ($from, $to, $isSummary = false) {
	global $bookPath, $editLinkName, $githubWikiName, $githubWikiURL, $error;

	if (file_exists($bookPath."/".$githubWikiName."/".$from)) {
		$content = readContent($bookPath."/".$githubWikiName."/".$from);
		$contentConverted = convertSyntax($content, $isSummary);
		writeContent ($bookPath."/".$to, $contentConverted);
	}
	else
		$error[] = $from;
}

system('git -C '.$bookPath.'/'.$githubWikiName.' pull;');
convertFile ("_Sidebar.md", "SUMMARY.md", true);
convertFile ("Home.md", "README.md");
$allMdFileslistArray = array_diff(scandir($bookPath."/".$githubWikiName), array('.', '..', '_Sidebar.md', 'SUMMARY.md', 'Home.md', 'README.md'));
foreach ($allMdFileslistArray as $key => $fileName) {
	convertFile ($fileName, $fileName);
}

// Adding MdFiles not present in the Summary
if ($allMdFileslistArray > $mdFileListArray) {
	$handle = fopen($bookPath.'/SUMMARY.md', "r");
	$content = fread($handle, filesize($bookPath.'/SUMMARY.md'));
	fclose($handle);
	$content .= '
* '.$otherPagesChapterName;
	foreach ($allMdFileslistArray as $key => $fileName) {
		if (!in_array($fileName, $mdFileListArray) AND substr($fileName, -3) == ".md") {
			$content .= '
    * ['.str_ireplace("-", " ", str_ireplace(".md", "", $fileName)).']('.$fileName.')';
		}
	}
	$handle = fopen($bookPath.'/SUMMARY.md', "w");
	fwrite($handle, $content);
	fclose($handle);
}
?>

<!DOCTYPE html>
<html class="client-nojs" lang="fr" dir="ltr">
<head>
<meta charset="UTF-8"/>
<title>GitHub Wiki to Gitbook generator</title>
</head>
<body>
<h1>Finished ! Your Gitbook and PDF manual has been generated from the Github Wiki.</h1>
<br />
<p><?php echo sizeof($allMdFileslistArray); ?> pages have been converted and imported.</p>
<?php
	if (sizeof($error) > 0) {
		?><p style="color: red; font-weight: bold;">CAUTION ! The following internal links of the Github wiki sidebar don't correspond to a valid name of a wiki page (case sensitive) :</p>
		<ul><?php
		foreach ($error as $key => $value)
			echo "<li>".$value."</li>";
		?></ul><?php
	}
?>
<br />
<h2>IMPORTANT :</h2>
<p><b>Now check <a href="/">the generated Gitbook wiki</a> to check if everithing ok.</b></p>
<br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br />

<?php
if ($_SERVER["DOCUMENT_ROOT"] != '') // If the script is executed from the web browser
	system('mv '.$bookPath.'/_book/'.$pdfBookName.'.pdf '.$bookPath.';'); // otherwise gitbook will overwrite it
system('
	gitbook build '.$bookPath.';
	cp -r '.$bookPath.'/Github-Wiki-to-Gitbook/gitbook-custom-template/* '.$bookPath.'/_book/gitbook;
	
');
if ($_SERVER["DOCUMENT_ROOT"] == '') // If the script is executed from the server
	system('gitbook pdf '.$bookPath.' '.$bookPath.'/_book/'.$pdfBookName.'.pdf;');
else // If the script is executed from the web browser
	system('mv '.$bookPath.'/'.$pdfBookName.'.pdf '.$bookPath.'/_book/;');
system('rm '.$bookPath.'/*.md;');


// Adding edit button and footer
if (file_exists($bookPath."/".$githubWikiName."/_Footer.md"))
	$footerText = shell_exec("markdown -b ".$bookPath."/".$githubWikiName."/_Footer.md");
else
	$footerText = "";
$allGitbookFileslistArray = scandir($bookPath."/_book");
foreach ($allGitbookFileslistArray as $key => $fileName) {
	if (substr($fileName, -5) == ".html") {
		$content = readContent($bookPath."/_book/".$fileName);
		if ($fileName == "index.html")
			$pageName = "Home";
		else
			$pageName = str_ireplace(".html", "", $fileName);
		$contentConverted = str_ireplace(
			'</section>',
			'<footer>'.$footerText.'</footer></section>',
			str_ireplace(
				'<!-- Title -->',
				'<a aria-label="" href="'.$githubWikiURL.'/'.$pageName.'/_edit" class="btn pull-left"><i class="fa fa-pencil"></i></a>',
				$content)
			);
		writeContent ($bookPath."/_book/".$fileName, $contentConverted);
	}
}


?>

</body>
</html>
