<?php

//error_reporting(0);
ini_set('error_reporting', E_ALL);
error_reporting(E_ALL);

function get_fighter_search_data($firstname, $lastname, $nickname, $association, $weight, $page, $link) {
	$url = "";
	
	if ($link == "") {
		$url  = "http://www.sherdog.com/fightfinder.php?";
		$url .= "firstname=" . urlencode($firstname) . "&"; 
		$url .= "lastname=" . urlencode($lastname) . "&"; 
		$url .= "nickname=" . urlencode($nickname) . "&"; 
		$url .= "association=" . urlencode($association) . "&"; 
		$url .= "weight=" . urlencode($weight) . "&";
		$url .= "page=" . urlencode($page);
	} else {
		$url = "http://www.sherdog.com" . urldecode($link);
	} 
	
	$ch = curl_init();
	$options = array( 
        CURLOPT_RETURNTRANSFER => true,     // return web page 
        CURLOPT_HEADER         => true,    // return headers 
        CURLOPT_FOLLOWLOCATION => true,     // follow redirects 
        CURLOPT_ENCODING       => "",       // handle all encodings 
        CURLOPT_USERAGENT      => "spider", // who am i 
        CURLOPT_AUTOREFERER    => true,     // set referer on redirect 
        CURLOPT_CONNECTTIMEOUT => 120,      // timeout on connect 
        CURLOPT_TIMEOUT        => 120,      // timeout on response 
        CURLOPT_MAXREDIRS      => 10,       // stop after 10 redirects 
    ); 
	
	$ch      = curl_init( $url ); 
    curl_setopt_array( $ch, $options ); 
    $content = curl_exec( $ch ); 
    $err     = curl_errno( $ch ); 
    $errmsg  = curl_error( $ch ); 
    $header  = curl_getinfo( $ch ); 
    curl_close( $ch );
	return $content;
}

function getFightFromRow($row) {
	$i = 0;
	$fight = array(
		"result" => "",
		"opponent" => "",
		"method" => "",
		"event" => "",
		"date" => "",
		"round" => "",
		"time" => ""
	);
	foreach($row->getElementsByTagName("td") as $cell) {
		if ($i == 0) {
			$fight["result"] = trim($cell->textContent);
		} else if ($i == 1) {
			$fight["opponent"] = trim($cell->textContent);	
			$elems = $cell->getElementsByTagName("a");
			if ($elems->length > 0) {
				$fight['link'] = $elems->item(0)->getAttribute('href');
			}
		} else if ($i == 2) {
			$fight["method"] = trim($cell->textContent);	
		} else if ($i == 3) {
			$fight["event"] = trim($cell->textContent);	
		} else if ($i == 4) {
			$fight["date"] = trim($cell->textContent);	
		} else if ($i == 5) {
			$fight["round"] = trim($cell->textContent);	
		} else if ($i == 6) {
			$fight["time"] = trim($cell->textContent);	
		}
		$i++;
	}	
	
	return $fight;
}

$FIRSTNAME = isset($_GET["firstname"]) ? $_GET["firstname"] : "";
$LASTNAME = isset($_GET["lastname"]) ? $_GET["lastname"] : "";
$NICKNAME = isset($_GET["nickname"]) ? $_GET["nickname"] : "";
$ASSOCIATION = isset($_GET["association"]) ? $_GET["association"] : "";
$WEIGHT = isset($_GET["weight"]) ? $_GET["weight"] : "";
$LINK = isset($_GET["link"]) ? $_GET["link"] : "";

$json = array(
	"success" => true,
	"info" => "",
	"data" => ""
);
$scrape = get_fighter_search_data($FIRSTNAME, $LASTNAME, $NICKNAME, $ASSOCIATION, $WEIGHT, 1, $LINK);
$fighters = array();

// determine how many fighters we found
if (preg_match('/We found <b>(\d+)<\/b> Fighter\(s\) matching your search criteria/ism', $scrape, $matches)) {
	$json["info"] = "list";

	// send warning if over 100 match
	if ($matches[1] > 100) {
		$json["success"] = false;
		$json["info"] = "Over 100 fighters matches, keep filtering!";
	// create json for fighter list
	}  else {
		preg_match_all('/href="(\/fighter\/.+?)".*?>(.+?)<.+?<\s*td.+?>(.*?)<.+?<\s*td.+?>(.*?)<.+?<\s*td.+?>(.*?)</ms', $scrape, $matches, PREG_SET_ORDER);
		if (count($matches) > 0) {
			foreach ($matches as $m) {
				$fighters[] = array(
					"link" => $m[1],
					"name" => $m[2],
					"nick" => $m[3],
					"ht" => $m[4],
					"wt" => $m[5]
				);
			}
			$json["data"] = $fighters;
		}			
	}
} else if (preg_match('/<span[^>]+?id="fighter_picture"[^>]*?>\s*<img[^>]+?src=\'([^\']+?)\'/ism', $scrape, $matches)) {
	$json["info"] = "detail";
	
	// fighter pic
	$image_link = $matches[1];
	
	// fighter profile
	preg_match('/<span[^>]+?id="fighter_profile"[^>]*?>(.+?<\/table>)/ims', $scrape, $matches);
	$tables = $matches[1];
	
	$doc = new DOMDocument();
	$doc->loadXML('<?xml version="1.0"?>' . str_replace('&', '&amp;', $tables));
	
	$profile = array();
	foreach($doc->getElementsByTagName("tr") as $row) {
		$i = 0;
		$label = $value = "";
		foreach($row->getElementsByTagName("td") as $cell) {
			if ($i == 0) {
				$label = trim($cell->textContent);
			} else if ($i == 1) {
				$value = trim($cell->textContent);	
			}
			$i++;
		}
		
		if ($label == "Sherdog Store") {
			continue;
		} else if ($label == "Record") {
			$value = preg_replace('/\s+/', "", $value);
			$pos = strpos($value, "(");
			if ($pos !== FALSE) {
				$value = substr($value, 0, $pos);		
			}	
		} else if ($label == "Wins" || $label == "Losses") {
			$value = trim(str_replace("%)", "%)\n", preg_replace('/\s+/', "", $value)));	
			$value = preg_replace('/\([^\)]+?%\)/', '', $value);
			$value = preg_replace('/(\d+)/', '$1 ', $value);
		}
		
		$profile[] = array(
			"k" => $label,
			"v" => $value
		);
	}
	
	// fighter stats
	$fights = array();
	
	preg_match('/<div[^>]+?id="fighter_stat"[^>]*?>\s*?<table[^>]*?>(.+?<\/table>)/ims', $scrape, $matches);
	$tables = $matches[1];
	if ($tables == "") {
		$tables = substr($scrape, strpos($scrape, 'id="fighter_stat"'));
		$tables = str_replace("&nbsp;", "", preg_replace('/<img[^>]*?>/ims', '', $tables));
		$tables = substr($tables, strpos($tables, '<tr'));
		$tables = substr($tables, 0, strpos($tables, '</table>'));
		
		$offset = -1;
		while (($offset = strpos($tables, '<tr', $offset+1)) !== FALSE) {
			$endOffset = strpos($tables, '</tr>', $offset) + 5;
			$tr = substr($tables, $offset, $endOffset - $offset);
			$doc = new DOMDocument();
			$doc->loadXML('<?xml version="1.0"?>' . str_replace('&', '&amp;', $tr));
			$fight = getFightFromRow($doc);
			if ($fight["opponent"] == "") {
				continue;	
			}
			
			$fights[] = $fight;	
		}		
	} else {
		$doc = new DOMDocument();
		$tables = str_replace("&nbsp;", "", preg_replace('/<img[^>]*?>/ims', '', $tables));
		$doc->loadXML('<?xml version="1.0"?><table>' . str_replace('&', '&amp;', $tables));
		
		foreach($doc->getElementsByTagName("tr") as $row) {
			$fight = getFightFromRow($row);
			
			if ($fight["opponent"] == "") {
				continue;	
			}
			$fights[] = $fight;
		}
	}
	
	$json["data"] = array(
		"imagelink" => $image_link,
		"profile" => $profile,
		"fights" => $fights
	);
} else {
	$json["success"] = false;
	$json["info"] = "No fighters found. $LINK";
}
	
echo json_encode($json);

?>