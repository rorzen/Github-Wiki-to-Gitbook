#!/usr/bin/php
<?php
/*
Marc Farré
https://marc.fun

https://github.com/marc-fun/Github-Wiki-to-Gitbook
Version 0.2
2017-08-06
*/

$bookPath = "/home/user-www-data/www/your-website";
$githubWikiUrl = "https://github.com/name/repository.wiki.git";
$githubWikiName = "co2.wiki";

if ($_SERVER["DOCUMENT_ROOT"] != '') // If the script is executed from a web browser
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
		$newContent = str_ireplace('
######', "$$$", $newContent);
		$newContent = str_ireplace('
#####', "$$$", $newContent);
		$newContent = str_ireplace('
####', "$$$", $newContent);
		$newContent = str_ireplace('
###', "$$$", $newContent);
		$newContent = str_ireplace('
##', "$$$", $newContent);
		$newContent = str_ireplace('
#', "$$$", $newContent);

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
	global $bookPath, $githubWikiName, $error;

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
foreach ($mdFileListArray as $key => $fileName) {
	convertFile ($fileName, $fileName);
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
<p><?php echo sizeof($mdFileListArray); ?> pages have been converted and imported.</p>
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
system('
	gitbook build '.$bookPath.';
	cp -r '.$bookPath.'/gitbook-custom-template/* '.$bookPath.'/_book/gitbook;
	gitbook pdf ".$bookPath." ".$bookPath."/_book/communecter-manual.pdf;
	rm '.$bookPath.'/*.md;
');
?>

</body>
</html>
