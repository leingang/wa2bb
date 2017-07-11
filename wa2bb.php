<?php

session_start();

if ($_SERVER['REQUEST_METHOD']=='GET') {
	form_get();	
}
elseif ($_SERVER['REQUEST_METHOD']=='POST') {
	if (form_valid()) {
		process_form();
		send_file();
	} else {
		http_send_status(400); // Bad Request
		form_get();
	}
}
else {
	http_send_status(405); // Method Not Allowed
	header("Allow: GET, POST");
}

function form_valid() {
	print "<pre>\n"; print_r($_FILES); print "</pre>\n";
	return 1;
}

function process_form() {
	$waFile=file($_FILES['waFile']['tmp_name']);
	$bbFile=file($_FILES['bbFile']['tmp_name'],FILE_TEXT);
	
	// print "<pre>\n"; print_r($waFile); print "</pre>\n";
	
    $bbData_utf16=join('',$bbFile);
	$bbData_utf8=iconv('UTF-16','UTF-8',$bbData_utf16);
	$bbFile=preg_split("/\n/m",$bbData_utf8);

	// print "<pre>\n"; print_r($bbFile); print "</pre>\n";
	
	$waArray=parse_waFile($waFile);	
	$bbArray=parse_bbFile($bbFile);
	
	// print "<pre>waArray: \n"; print_r($waArray); print "</pre>\n";
	// print "<pre>bbArray\n"; print_r($bbArray); print "</pre>\n";
	
	// merge
	foreach ($waArray as $netID=>$score) {
		if (array_key_exists($netID,$bbArray)) {
		$keys = array_keys($bbArray[$netID]);	
		$bbArray[$netID][$keys[3]]=$score;
		}
		else {
			print ("Bad username: $netID<br />\n");
		}
	}
	//print "<pre>bbArray after merge\n"; print_r($bbArray); print "</pre>\n";
	
	$bbData=serialize_bbArray($bbArray);
	print "<pre>bbData\n"; print_r($bbData); print "</pre>\n";
	
	// save to temp file
	$dir = dirname($_FILES['bbFile']['tmp_name']);
	$fileName=$dir . '/'. str_replace('column','webassign',$_FILES['bbFile']['name']);
	$FH = fopen($fileName,'w');
	fwrite($FH,$bbData);
	fclose($FH);
	
	$_SESSION['fileName'] = $fileName;
	
	print "Process complete!";
}

/**
 * process the lines of the WebAssign gradebook
 * 
 * @param array $waFile
 * @return hash of scores, indexed by NetID
 */
function parse_waFile($waFile) {
	$n=0;
	$return=Array();
	foreach ($waFile as $line) {
		// skip the first 9 lines
		if ($n++ < 9) {
			continue;
		}
		// skip blank lines
		if (preg_match('/^\s*$/',$line)) {
			continue;
		}
		// strip off newlines
		$line=preg_replace('/\n$/','',$line);
		list($fullname,$username,$nnumber,$final,$homework) = preg_split('/\t/',$line);
		$return[$username]=$homework;
	}
	return $return;
}

/**
 * parse the lines of the Blackboard file, 
 * @param array $bbFile
 * @return mixed hash of hashes, indexed by NetID
 */
function parse_bbFile($bbFile) {
	$n=0;
	$return = Array();
	foreach ($bbFile as $line) {
		$line=preg_replace('/\"/','',$line);
		if ($n++ == 0) {
			$keys = preg_split('/\t/',$line);
		}
		else {
			$vals = preg_split('/\t/',$line);
			$id=$vals[2];
			if ($id) {
				$m=0;
				$rec=Array();
				reset($keys);
				foreach ($keys as $key) {
					$rec[$key]=$vals[$m++];
				}
				$return[$id]=$rec;
			}
		}
	}
	return $return;
}

/**
 * convert the Blackboard array back to a string
 * @param unknown_type $bbArray
 * @return unknown_type
 */
function serialize_bbArray($bbArray) {
	$return_lines=array();
	$n=0;
	foreach ($bbArray as $netID=>$rec){
		if ($n++==0) {
			$fields=Array();
			foreach ($rec as $key=>$val) {
				$fields[] = '"' . $key . '"';
			}
			$return_lines[]=join("\t",$fields);
		}
		// we do this on all lines, including the first
		$vals=Array();
		foreach ($rec as $key=>$val) {
			$vals[] = '"' . $val . '"';
		}
		$return_lines[]=join("\t",$vals);
	}
	return join("\n",$return_lines);
}

function send_file() {
	return 1;
}


function form_get() {
	document_head();
	page_head();
	print <<<EOM
<h1>Download from WebAssign</h1>

<p>
Login to <a href="http://www.webassign.net/">WebAssign</a> and select "Download Manager" from the Tasks menu.
Click the checkbox next to your section and then the "GradeBook" button.
On the following screen, select "Tab-Delimited Text--single file"
</p>

<p>
The next screen will be a list of your students.  Save this page and make a note of where it is in your filesystem (Desktop, Downloads folder, course folder, etc.).  
How you save depends on your browser, but it's often an item in the "File" pull-down menu.  The file should be saved as plain text, not HTML.  The default file name is 
something like "gradebook_nnnnnnnnnn.txt", where <em>nnnnnnnnnn</em> is some 10-digit number.
</p>

<h1>Download from Blackboard</h1>

<p>
This step is optional if you have never uploaded WebAssign data to Blackboard.  
If you want to upload WebAssign data overwriting old WebAssign data, you need this step 
so that the uploaded data goes in the right place.
</p>

<p>Via <a href="http://home.nyu.edu/">Home</a>, login to your section's Blackboard site.  Select "Control Panel", then "Grade Center."</p>

<h2>Creating a Blackboard Column</h2>

<p>
(You only need to do this once, if at all. Blackboard will help you create a column upon uploading if you haven't done so already.)
</p>

<p>
In the Blackboard Grade Center, select "Add Grade Column." On the next page give the column a name ("WebAssign" is a good name).  
Fill in "100" for the total points possible.  The other items can be left at their defaults.  Click "Submit" to create the column.
</p>


<h2>Downloading Column Data</h2>

<p>
Again in the Blackboard Grade Center, select "Download" from the "Manage" pull-down menu.  Near "Select Data to Download", select the "Selected Column" radio button 
and your WebAssign column from the nearby pull-down menu.  Leave the rest of the fields the way they are and click "Submit".
</p>

<p>
The next screen tells you the data has been prepared, but you still need to download it.  Click "Download" to do this (clicking "OK" will take you back to the 
Grade Center without downloading the file).  Unlike with WebAssign, where you were shown the file and needed to save it, this file is sent automatically to your browser
and your browser will probably save it for you in its default location for downloads (could be the desktop or a special folder).  Note also that although Blackboard calls 
this file a Microsoft Excel file and gives it the .xls extension, it's actually a tab-delimited text file just like the WebAssign one.  The default file name lists your section
and the current date and time.
</p>


<h1>Upload and submit</h1>

<form id="converterForm" enctype="multipart/form-data" action="{$_SERVER['PHP_SELF']}" method="POST">
<div id="waFileDiv">
  <label for="waFile">WebAssign Gradebook File:</label>
  <input id="waFile" type="file" name="waFile" />
</div>
<div id="waFileDiv">
  <label for="waFile">Blackboard Roster File:</label>
  <input id="bbFile" type="file" name="bbFile" />
</div>
<input type="submit" name="submit" value="Submit" />
</form>

<h1>Uploading result to Blackboard</h1>

EOM;
	page_foot();
}

function document_head() {
	print <<<EOM
<html>
  <head>
    <title>WebAssign to Blackboard converter</title>
  </head>
  <body>
EOM;
}

function page_head() {
	print <<<EOM
    <h1 class="title">WebAssign to Blackboard Converter</h1>
EOM;
}

function page_foot() {
	print <<<EOM
  </body>
</html>
EOM;
}

?>