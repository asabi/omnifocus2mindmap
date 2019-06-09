#!/usr/bin/php
<?php
date_default_timezone_set('America/Vancouver');
error_reporting(E_ALL);
ini_set('display_errors',"On");

define('DUE_SOON','flag-yellow');
define('FLAGGED','flag-orange');
define('OVERDUE','flag-pink');


class MyDB extends SQLite3
{
    function __construct()
    {
		// There is a difference between the omnigroup store and the app store versions of the app (they store the database in different places)
		$dbFilePath = $_SERVER['HOME'].'/Library/Containers/com.omnigroup.OmniFocus3/Data/Library/Application Support/OmniFocus/OmniFocus Caches/OmniFocusDatabase';

		if (!file_exists($dbFilePath)) {
			$dbFilePath = str_replace('.OmniFocus3', '.OmniFocus3.MacAppStore',$dbFilePath);
		}

		if (!file_exists($dbFilePath)) {
			echo "This script was made for omnifocus 3. It doesn't look like you have it installed.";
			exit;
		}
		
        $this->open($dbFilePath);
    }
}

$db = new MyDB();

$strSQL = "SELECT 
task.persistentIdentifier, 
Task.parent,
task.dueSoon,
task.flagged,
CASE WHEN task.ininbox = 1 THEN 'Inbox' ELSE projectInfo.folder END AS folder,
REPLACE(task.repetitionRuleString,'FREQ=','') AS repetition,
task.plainTextNote AS description,
strftime('%Y-%m-%dT%H:%M:00', datetime(Task.dateToStart + 978307200 - 28800, 'unixepoch')) AS `start`,
strftime('%Y-%m-%dT%H:%M:00', datetime(Task.dateDue + 978307200 - 28800, 'unixepoch')) AS due,
Task.name AS title,
projectInfo.numberOfRemainingTasks AS remaining,
projectInfo.numberOfOverdueTasks AS overdue,
projectInfo.numberOfAvailableTasks AS available,
group_concat(Context.name) AS contexts
FROM
Task
LEFT JOIN TaskToTag ON TaskToTag.Task = Task.persistentIdentifier
LEFT JOIN context ON context.persistentIdentifier = TaskToTag.tag
LEFT JOIN projectInfo ON projectInfo.task = Task.persistentIdentifier
WHERE
Task.dateCompleted IS NULL AND (projectInfo.status = 'active' OR projectInfo.status IS NULL)
GROUP BY task.persistentIdentifier
ORDER BY task.parent ASC";

$resultArr = array();

$result = $db->query($strSQL);
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
	if ($row['dueSoon']) {
		$row['icons'][] = 'flag-yellow'; // This task is due soon
	}
	if ($row['flagged']) {
		$row['icons'][] = 'flag-orange'; // This task is flagged
	}
    if ($row['due'] != '') {
		$dueDate = strtotime($row['due']);
		$now = strtotime('now');

		if ($now > $dueDate) {
			$row['icons'][] = 'flag-pink'; // This task is overdue
		}
	}

	$row['link'] = "omnifocus:///task/{$row['persistentIdentifier']}";
		
	unset($row['dueSoon']);
	unset($row['flagged']);

    $resultArr[] = $row;
}

$arrTasksHeirarchi = convertToHierarchy($resultArr, 'persistentIdentifier', 'parent', 'children');

$resultFolders = $db->query("SELECT name AS title, parent, persistentidentifier FROM folder WHERE active=1 ORDER BY parent ASC");

// Artificially add the Inbox
$position = 'right';
$row = array();
$row['title'] = 'Inbox';
$row['parent'] = null;
$row['persistentIdentifier'] = 'Inbox';
$row['link'] = 'omnifocus:///perspective/Inbox';
$row['position'] = $position;
$row['bounded'] = true;

$resultFolderArr['Legend'] = getLegend();

$resultFolderArr['Inbox'] = $row;


while ($row = $resultFolders->fetchArray(SQLITE3_ASSOC)) {
	$position = ($position == 'right')? 'left':'right';
	$row['position'] = $position;
	$row['bounded'] = true;
	$row['link'] = "omnifocus:///folder/{$row['persistentIdentifier']}";
	

	$resultFolderArr[$row['persistentIdentifier']] = $row;
}

// Attach the top level tasks with the folders

foreach ($arrTasksHeirarchi as $task) {
   if ($task['folder'] != '' && isset($resultFolderArr[$task['folder']])) {
       $resultFolderArr[$task['folder']]['children'][] = $task;
   }
}


$arrHeirarchiFolder = convertToHierarchy($resultFolderArr, 'persistentIdentifier', 'parent', 'children');


// Removes things we don't need in the mindmap
recursive_unset($arrHeirarchiFolder,'persistentIdentifier');
recursive_unset($arrHeirarchiFolder,'parent');
recursive_unset($arrHeirarchiFolder,'folder');

// Removes empty values
$arrHeirarchiFolder = array_map('array_filter', $arrHeirarchiFolder);
$arrHeirarchiFolder = array_filter($arrHeirarchiFolder);

$stdClassRequest = new stdClass();

$stdClassRequest->mapName = 'Omnifocus '.date('l F jS Y h:i:s A');
$stdClassRequest->nodes = $arrHeirarchiFolder;

$map = callCURL('json.tomindmap.com',json_encode($stdClassRequest, true), 'POST');

file_put_contents($_SERVER['HOME'].'/Desktop/'.$stdClassRequest->mapName.'.mm',$map);



function callCURL($url, $data_string, $method)
{

//    echo $url."\n\n";exit;

    $method = strtoupper($method);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_COOKIESESSION, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

    if ($method == 'POST') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
    }
    
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    return $result;
}

/**
 * Thank you !!!
 * https://gist.github.com/ubermaniac/8834601
 */
function convertToHierarchy($results, $idField='id', $parentIdField='parent', $childrenField='children') {
	$hierarchy = array(); // -- Stores the final data
	$itemReferences = array(); // -- temporary array, storing references to all items in a single-dimention
	foreach ( $results as $item ) {
		$id       = $item[$idField];
		$parentId = $item[$parentIdField];
		if (isset($itemReferences[$parentId])) { // parent exists
			$itemReferences[$parentId][$childrenField][$id] = $item; // assign item to parent
			$itemReferences[$id] =& $itemReferences[$parentId][$childrenField][$id]; // reference parent's item in single-dimentional array
		} elseif (!$parentId || !isset($hierarchy[$parentId])) { // -- parent Id empty or does not exist. Add it to the root
			$hierarchy[$id] = $item;
			$itemReferences[$id] =& $hierarchy[$id];
		}
	}
	unset($results, $item, $id, $parentId);
	// -- Run through the root one more time. If any child got added before it's parent, fix it.
	foreach ( $hierarchy as $id => &$item ) {
		$parentId = $item[$parentIdField];
		if ( isset($itemReferences[$parentId] ) ) { // -- parent DOES exist
			$itemReferences[$parentId][$childrenField][$id] = $item; // -- assign it to the parent's list of children
			unset($hierarchy[$id]); // -- remove it from the root of the hierarchy
		}
	}
	unset($itemReferences, $id, $item, $parentId);
	return $hierarchy;
}

function recursive_unset(&$array, $unwanted_key) {
    unset($array[$unwanted_key]);
    foreach ($array as &$value) {
        if (is_array($value)) {
            recursive_unset($value, $unwanted_key);
        }
    }
}

function getLegend() {
	$row = array();
	$row['title'] = 'Legend';
	$row['bounded'] = true;
	$row['position'] = 'right';
	$row['persistentIdentifier'] = 'Lgegend';
	$row['parent'] = null;
	$row['folder'] = null;
	$row['children'] = array();
	$row['children'][] = array('title'=>'Due Soon','icons'=>array(DUE_SOON));
	$row['children'][] = array('title'=>'Flagged','icons'=>array(FLAGGED));
	$row['children'][] = array('title'=>'Overdue','icons'=>array(OVERDUE));

	return $row;
}