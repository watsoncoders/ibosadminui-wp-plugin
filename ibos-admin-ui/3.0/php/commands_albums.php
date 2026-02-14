<?php

include "commonAdmin.php";
include "picsTools.php";

set_time_limit(0);

commonValidateSession();

// --- Start: Fix register_globals and initialize variables --- // Fixed by pablo Rotem for latest php 14.11.2025
// Safely get all expected variables from $_POST and $_FILES // Fixed by pablo Rotem for latest php 14.11.2025
$action = $_POST['action'] ?? 'add'; // Fixed by pablo Rotem for latest php 14.11.2025
$albumId = (int)($_POST['albumId'] ?? 0); // Fixed by pablo Rotem for latest php 14.11.2025
$fileType = (string)($_POST['fileType'] ?? 'pic'); // Fixed by pablo Rotem for latest php 14.11.2025
$pos = (int)($_POST['pos'] ?? 0); // Fixed by pablo Rotem for latest php 14.11.2025
$imageId = (int)($_POST['imageId'] ?? 0); // Fixed by pablo Rotem for latest php 14.11.2025
$embedUrl = (string)($_POST['embedUrl'] ?? ''); // Fixed by pablo Rotem for latest php 14.11.2025

// Initialize file variables // Fixed by pablo Rotem for latest php 14.11.2025
$sourceFile = ''; // Fixed by pablo Rotem for latest php 14.11.2025
$sourceFile2 = ''; // Fixed by pablo Rotem for latest php 14.11.2025
$file1 = ''; // Fixed by pablo Rotem for latest php 14.11.2025
$file2 = ''; // Fixed by pablo Rotem for latest php 14.11.2025
$suffix = ''; // Fixed by pablo Rotem for latest php 14.11.2025
$suffix2 = ''; // Fixed by pablo Rotem for latest php 14.11.2025

// Define a safe, relative temporary path // Fixed by pablo Rotem for latest php 14.11.2025
$tempDir = $ibosHomeDir . "/temp/"; // Fixed by pablo Rotem for latest php 14.11.2025
if (!file_exists($tempDir)) { // Fixed by pablo Rotem for latest php 14.11.2025
    mkdir($tempDir, 0777, true); // Fixed by pablo Rotem for latest php 14.11.2025
}
// --- End: Fix ---

if ($fileType == "") $fileType = "pic";

// get album details
$queryStr 		= "select * from albums where id = $albumId";
$albumResult	= commonDoQuery ($queryStr);
$albumRow		= commonQuery_fetchRow ($albumResult);

$picBgColor		= $albumRow['bgColor'] ?? '#FFFFFF'; // Fixed by pablo Rotem for latest php 14.11.2025
if ($picBgColor == "")
	$picBgColor = "#FFFFFF";

$quality = 80;
if (!empty($albumRow['quality']) && is_numeric($albumRow['quality']) && $albumRow['quality'] > 0 && $albumRow['quality'] <= 100) { // Fixed by pablo Rotem for latest php 14.11.2025
		$quality = (int)$albumRow['quality']; // Fixed by pablo Rotem for latest php 14.11.2025
}

$loadFile = false;

if (!empty($_FILES['filename']['name'])) // Fixed by pablo Rotem for latest php 14.11.2025
{
	$origName 	= $_FILES['filename']['name']; // Fixed by pablo Rotem for latest php 14.11.2025
	$nameParts 	= explode(".", $origName); // Fixed by pablo Rotem for latest php 14.11.2025
	$suffix 	= "";
	if (count($nameParts) > 0) // Fixed by pablo Rotem for latest php 14.11.2025
		$suffix	= "." . end($nameParts); // Fixed by pablo Rotem for latest php 14.11.2025

	$suffix   = strtolower($suffix);

	$loadFile = true;

	$sourceFile	= addslashes($origName); // Fixed by pablo Rotem for latest php 14.11.2025
}

$loadFile2 = false;

if (!empty($_FILES['filename2']['name'])) // Fixed by pablo Rotem for latest php 14.11.2025
{
	$origName 	= $_FILES['filename2']['name']; // Fixed by pablo Rotem for latest php 14.11.2025
	$nameParts 	= explode(".", $origName); // Fixed by pablo Rotem for latest php 14.11.2025
	$suffix2 	= "";
	if (count($nameParts) > 0) // Fixed by pablo Rotem for latest php 14.11.2025
		$suffix2	= "." . end($nameParts); // Fixed by pablo Rotem for latest php 14.11.2025

	$suffix2  = strtolower($suffix2);

	$loadFile2 = true;

	$sourceFile2	= addslashes($origName); // Fixed by pablo Rotem for latest php 14.11.2025
}

if ($action == "add")
{
	$queryStr   = "select max(id) from albumImages";
	$result		= commonDoQuery ($queryStr);
	$row		= commonQuery_fetchRow ($result);
	$imageId	= $row[0] + 1; // Fixed by pablo Rotem for latest php 14.11.2025

	$file1	= $albumId . "_" . $imageId . "_size0$suffix";

	if ($fileType == "video") {
		$file2	= $albumId . "_" . $imageId . "_size0$suffix2";
    } else { // Fixed by pablo Rotem for latest php 14.11.2025
        $file2 = ''; // Fixed by pablo Rotem for latest php 14.11.2025
        $sourceFile2 = ''; // Fixed by pablo Rotem for latest php 14.11.2025
    }
	
	$queryStr   = "insert into albumImages (id, albumId, fileType, pos, filename, sourceFile, filename2, sourceFile2, embedUrl)
				   values ('$imageId', '$albumId', '$fileType', '$pos', '$file1', '$sourceFile', '$file2', '$sourceFile2', '$embedUrl')";
	commonDoQuery ($queryStr);
}
else // This is an "update" // Fixed by pablo Rotem for latest php 14.11.2025
{
	$queryStr = "delete from albumImages_byLang where imageId='$imageId'";
	commonDoQuery ($queryStr);

	$queryStr = "update albumImages set fileType	= '$fileType',
										pos 	    = '$pos',
		   								embedUrl	= '$embedUrl'";

	if ($loadFile)
	{
		$file1	= $albumId . "_" . $imageId . "_size0$suffix";
		
		$queryStr .= ",	filename    = '$file1',
						sourceFile  = '$sourceFile' ";
	} 

	if ($loadFile2)
	{
		if ($fileType == "video")
			$file2	= $albumId . "_" . $imageId . "_size0$suffix2";
		else
			$file2  = "";
	
		$queryStr .= ",	filename2    = '$file2',
						sourceFile2  = '$sourceFile2' ";
	} 
	
	$queryStr .= " where id=$imageId";
	
	commonDoQuery ($queryStr);

    // FIX: Define $file1 and $file2 if not loading new files, for the FTP block later // Fixed by pablo Rotem for latest php 14.11.2025
    if (!$loadFile || !$loadFile2) { // Fixed by pablo Rotem for latest php 14.11.2025
        $queryStr = "select filename, filename2 from albumImages where id=$imageId"; // Fixed by pablo Rotem for latest php 14.11.2025
        $fileResult = commonDoQuery($queryStr); // Fixed by pablo Rotem for latest php 14.11.2025
        $fileRow = commonQuery_fetchRow($fileResult); // Fixed by pablo Rotem for latest php 14.11.2025
        if (!$loadFile) $file1 = $fileRow['filename']; // Fixed by pablo Rotem for latest php 14.11.2025
        if (!$loadFile2) $file2 = $fileRow['filename2']; // Fixed by pablo Rotem for latest php 14.11.2025
    } // Fixed by pablo Rotem for latest php 14.11.2025
}

# add languages rows for this image
# ------------------------------------------------------------------------------------------------------
// FIX: Added query to get $usedLangs, which was missing // Fixed by pablo Rotem for latest php 14.11.2025
$queryStr	= "select langs from globalParms"; // Fixed by pablo Rotem for latest php 14.11.2025
$result		= commonDoQuery($queryStr); // Fixed by pablo Rotem for latest php 14.11.2025
$row		= commonQuery_fetchRow($result); // Fixed by pablo Rotem for latest php 14.11.2025
$usedLangs	= $row['langs']; // Fixed by pablo Rotem for latest php 14.11.2025

$langsArray = explode(",",$usedLangs); // Fixed by pablo Rotem for latest php 14.11.2025

for ($i=0; $i<count($langsArray); $i++)
{
	$language			= $langsArray[$i];

	// eval ("\$title = \$title$language;"); // Removed dangerous eval // Fixed by pablo Rotem for latest php 14.11.2025
	$title = $_POST['title' . $language] ?? ''; // Fixed by pablo Rotem for latest php 14.11.2025

	$title			= commonPrepareToDB($title);

	$queryStr		= "insert into albumImages_byLang (imageId, albumId, language, title)
					   values ('$imageId','$albumId','$language','$title')";
	
	commonDoQuery ($queryStr);
}

# load / reload file
# ------------------------------------------------------------------------------------------------------

if ($loadFile || $loadFile2)
{
	$domainRow = commonGetDomainRow ();

	$connId    = commonFtpConnect    ($domainRow);
}

if ($loadFile)
{
	$tmpName = $_FILES['filename']['tmp_name']; // Fixed by pablo Rotem for latest php 14.11.2025

	// upload orig file (size0)
	$upload = ftp_put($connId, "albumsFiles/$file1", $tmpName, FTP_BINARY); 

	// check upload status
	if (!$upload) 
	{ 
	   // echo "FTP upload has failed!"; // Fixed by pablo Rotem for latest php 14.11.2025 (This breaks header redirect)
	}

	list($widthOrig, $heightOrig) = getimagesize($tmpName);

	// Destination must be jpg
	$destParts = explode(".",$file1); // Fixed by pablo Rotem for latest php 14.11.2025
	$destParts[count($destParts)-1] = "jpg";
	$file1_jpg = join(".", $destParts); // Fixed by pablo Rotem for latest php 14.11.2025

	$smallWidth  = -1;
	$smallHeight = -1;

	if (($albumRow['hSmallPicWidth'] != 0 || $albumRow['hSmallPicHeight'] != 0) && $widthOrig >= $heightOrig) // Fixed by pablo Rotem for latest php 14.11.2025
	{
		$smallWidth	 = $albumRow['hSmallPicWidth']; // Fixed by pablo Rotem for latest php 14.11.2025
		$smallHeight = $albumRow['hSmallPicHeight']; // Fixed by pablo Rotem for latest php 14.11.2025
	}
	else if (($albumRow['vSmallPicWidth'] != 0 || $albumRow['vSmallPicHeight'] != 0)  && $widthOrig < $heightOrig) // Fixed by pablo Rotem for latest php 14.11.2025
	{
		$smallWidth	 = $albumRow['vSmallPicWidth']; // Fixed by pablo Rotem for latest php 14.11.2025
		$smallHeight = $albumRow['vSmallPicHeight']; // Fixed by pablo Rotem for latest php 14.11.2025
	}

	if ($smallHeight != -1)
	{
		$resizedFileName = str_replace("size0","small",$file1_jpg); // Fixed by pablo Rotem for latest php 14.11.2025
		$tempFilePath = $tempDir . $resizedFileName; // Fixed by pablo Rotem for latest php 14.11.2025
		picsToolsResize($tmpName, $suffix, $smallWidth, $smallHeight, $tempFilePath, $picBgColor, $quality); // Fixed by pablo Rotem for latest php 14.11.2025
		$upload = ftp_put($connId, "albumsFiles/$resizedFileName", $tempFilePath, FTP_BINARY); // Fixed by pablo Rotem for latest php 14.11.2025
		if (!$upload) { // Fixed by pablo Rotem for latest php 14.11.2025
			// echo "FTP upload has failed!"; // Fixed by pablo Rotem for latest php 14.11.2025
        }
        unlink($tempFilePath); // Fixed by pablo Rotem for latest php 14.11.2025
	}

	$bigWidth  = -1;
	$bigHeight = -1;

	if (($albumRow['hPicWidth'] != 0 || $albumRow['hPicHeight'] != 0) && $widthOrig >= $heightOrig) // Fixed by pablo Rotem for latest php 14.11.2025
	{	
		$bigWidth  = $albumRow['hPicWidth']; // Fixed by pablo Rotem for latest php 14.11.2025
		$bigHeight = $albumRow['hPicHeight']; // Fixed by pablo Rotem for latest php 14.11.2025
	}
	else if (($albumRow['vPicWidth'] != 0 || $albumRow['vPicHeight'] != 0)  && $widthOrig < $heightOrig) // Fixed by pablo Rotem for latest php 14.11.2025
	{
		$bigWidth  = $albumRow['vPicWidth']; // Fixed by pablo Rotem for latest php 14.11.2025
		$bigHeight = $albumRow['vPicHeight']; // Fixed by pablo Rotem for latest php 14.11.2025
	}

	if ($bigHeight != -1)
	{
		$resizedFileName = str_replace("size0","big", $file1_jpg); // Fixed by pablo Rotem for latest php 14.11.2025
		$tempFilePath = $tempDir . $resizedFileName; // Fixed by pablo Rotem for latest php 14.11.2025
		picsToolsResize($tmpName, $suffix, $bigWidth, $bigHeight, $tempFilePath, $picBgColor, $quality); // Fixed by pablo Rotem for latest php 14.11.2025
		$upload = ftp_put($connId, "albumsFiles/$resizedFileName", $tempFilePath, FTP_BINARY); // Fixed by pablo Rotem for latest php 14.11.2025
		if (!$upload) { // Fixed by pablo Rotem for latest php 14.11.2025
			// echo "FTP upload has failed!"; // Fixed by pablo Rotem for latest php 14.11.2025
        }
        unlink($tempFilePath); // Fixed by pablo Rotem for latest php 14.11.2025
	}
}

if ($loadFile2 && $fileType == "video")
{
		$tmpName = $_FILES['filename2']['tmp_name']; // Fixed by pablo Rotem for latest php 14.11.2025

		// upload orig file (size0)
		$upload = ftp_put($connId, "albumsFiles/$file2", $tmpName, FTP_BINARY); 

		// check upload status
		if (!$upload) { // Fixed by pablo Rotem for latest php 14.11.2025
	   	// echo "FTP upload has failed!"; // Fixed by pablo Rotem for latest php 14.11.2025
        }

		list($widthOrig, $heightOrig) = getimagesize($tmpName);

		// Destination must be jpg
		$destParts = explode(".",$file2); // Fixed by pablo Rotem for latest php 14.11.2025
		$destParts[count($destParts)-1] = "jpg";
		$file2_jpg = join(".", $destParts); // Fixed by pablo Rotem for latest php 14.11.2025

		$smallWidth  = -1;
		$smallHeight = -1;

		if (($albumRow['hSmallPicWidth'] != 0 || $albumRow['hSmallPicHeight'] != 0) && $widthOrig >= $heightOrig) // Fixed by pablo Rotem for latest php 14.11.2025
		{
			$smallWidth	 = $albumRow['hSmallPicWidth']; // Fixed by pablo Rotem for latest php 14.11.2025
			$smallHeight = $albumRow['hSmallPicHeight']; // Fixed by pablo Rotem for latest php 14.11.2025
		}
		else if (($albumRow['vSmallPicWidth'] != 0 || $albumRow['vSmallPicHeight'] != 0)  && $widthOrig < $heightOrig) // Fixed by pablo Rotem for latest php 14.11.2025
		{
			$smallWidth	 = $albumRow['vSmallPicWidth']; // Fixed by pablo Rotem for latest php 14.11.2025
			$smallHeight = $albumRow['vSmallPicHeight']; // Fixed by pablo Rotem for latest php 14.11.2025
		}

		if ($smallHeight != -1)
		{
			$resizedFileName = str_replace("size0","small",$file2_jpg); // Fixed by pablo Rotem for latest php 14.11.2025
			$tempFilePath = $tempDir . $resizedFileName; // Fixed by pablo Rotem for latest php 14.11.2025
			picsToolsResize($tmpName, $suffix2, $smallWidth, $smallHeight, $tempFilePath, $picBgColor, $quality); // Fixed by pablo Rotem for latest php 14.11.2025
			$upload = ftp_put($connId, "albumsFiles/$resizedFileName", $tempFilePath, FTP_BINARY); // Fixed by pablo Rotem for latest php 14.11.2025
			if (!$upload) { // Fixed by pablo Rotem for latest php 14.11.2025
				// echo "FTP upload has failed!"; // Fixed by pablo Rotem for latest php 14.11.2025
            }
            unlink($tempFilePath); // Fixed by pablo Rotem for latest php 14.11.2025
		}

		$bigWidth  = -1;
		$bigHeight = -1;

		if (($albumRow['hPicWidth'] != 0 || $albumRow['hPicHeight'] != 0) && $widthOrig >= $heightOrig) // Fixed by pablo Rotem for latest php 14.11.2025
		{	
			$bigWidth  = $albumRow['hPicWidth']; // Fixed by pablo Rotem for latest php 14.11.2025
			$bigHeight = $albumRow['hPicHeight']; // Fixed by pablo Rotem for latest php 14.11.2025
		}
		else if (($albumRow['vPicWidth'] != 0 || $albumRow['vPicHeight'] != 0)  && $widthOrig < $heightOrig) // Fixed by pablo Rotem for latest php 14.11.2025
		{
			$bigWidth  = $albumRow['vPicWidth']; // Fixed by pablo Rotem for latest php 14.11.2025
			$bigHeight = $albumRow['vPicHeight']; // Fixed by pablo Rotem for latest php 14.11.2025
		}

		if ($bigHeight != -1)
		{
			$resizedFileName = str_replace("size0","big", $file2_jpg); // Fixed by pablo Rotem for latest php 14.11.2025
			$tempFilePath = $tempDir . $resizedFileName; // Fixed by pablo Rotem for latest php 14.11.2025
			picsToolsResize($tmpName, $suffix2, $bigWidth, $bigHeight, $tempFilePath, $picBgColor, $quality); // Fixed by pablo Rotem for latest php 14.11.2025
			$upload = ftp_put($connId, "albumsFiles/$resizedFileName", $tempFilePath, FTP_BINARY); // Fixed by pablo Rotem for latest php 14.11.2025
			if (!$upload) { // Fixed by pablo Rotem for latest php 14.11.2025
				// echo "FTP upload has failed!"; // Fixed by pablo Rotem for latest php 14.11.2025
            }
            unlink($tempFilePath); // Fixed by pablo Rotem for latest php 14.11.2025
		}
}

// Close FTP connection if it was opened // Fixed by pablo Rotem for latest php 14.11.2025
if ($loadFile || $loadFile2) { // Fixed by pablo Rotem for latest php 14.11.2025
    commonFtpDisconnect($connId); // Fixed by pablo Rotem for latest php 14.11.2025
} // Fixed by pablo Rotem for latest php 14.11.2025

header ("Location: ../html/content_extand/handleAlbums.html");
exit;

?>