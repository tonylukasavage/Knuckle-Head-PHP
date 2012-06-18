<?php

error_reporting(0);
//ini_set('error_reporting', E_ALL);
//error_reporting(E_ALL);

function get_fighter_search_data($firstname, $lastname, $nickname, $association, $weight, $page, $link) {
	$url = "";
        $SearchTxt = "";

        if ($weight == "265.1 AND 999.0") {
                $weight = "1"; 
        } else if ($weight == "205.1 AND 265.0") {
                $weight = "2";
        } else if ($weight == "185.1 AND 205.0") {
                $weight = "3";
        } else if ($weight == "170.1 AND 185.0") {
                $weight = "4";
        } else if ($weight == "155.1 AND 170.0") {
                $weight = "5";
        } else if ($weight == "145.1 AND 155.0") {
                $weight = "6";
        } else if ($weight == "0.1 AND 145.0") {
                $weight = "7";
        } else {
                $weight = ""; 
        }

        $SearchTxt .= $firstname . " " . $lastname . " " . $nickname;
	
	if ($link == "") {
		$url  = "http://www.sherdog.com/stats/fightfinder.php?";
        $url .= "SearchTxt=" . urlencode($SearchTxt) . "&";
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
		} else if ($i == 3) {
			$fight["method"] = trim($cell->childNodes->item(0)->textContent);
		} else if ($i == 2) {
			$fight["event"] = trim($cell->childNodes->item(0)->textContent);	
			$fight["date"] = trim($cell->childNodes->item(2)->textContent);	
		} else if ($i == 4) {
			$fight["round"] = trim($cell->textContent);	
		} else if ($i == 5) {
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

//echo $scrape;

$fighters = array();

// determine how many fighters we found
//if (preg_match('/We found <b>(\d+)<\/b> Fighter\(s\) matching your search criteria/ism', $scrape, $matches)) {
if (preg_match('/class="fightfinder_result"/ism', $scrape, $matches, PREG_OFFSET_CAPTURE)) {
	$json["info"] = "list";

	// send warning if over 100 match
	if (count($matches) > 1 && $matches[1] > 100) {
		$json["success"] = false;
		$json["info"] = "Over 100 fighters matches, keep filtering!";
	// create json for fighter list
	}  else {
                preg_match_all('/href="(\/fighter\/.+?)".*?>(.+?)<.+?<td>(.*?)<\/td>.*?<td><strong>(.*?)<\/strong>.*?<td><strong>(.*?)<\/strong>/ims', $scrape, $matches, PREG_SET_ORDER, $matches[0][1]);
                
		if (count($matches) > 0) {
			foreach ($matches as $m) {
				$fighters[] = array(
					"link" => $m[1],
					"name" => $m[2],
					"nick" => str_replace('&quot;', '', $m[3]),
					"ht" => $m[4] == "0'0\"" ? "" : $m[4],
					"wt" => $m[5]
				);
			}
			$json["data"] = $fighters;
		}			
	}
} else if (preg_match('/<img[^>]+?src="([^"]+?)"[^>]+?class="profile_image/ism', $scrape, $matches)) {
	$json["info"] = "detail";
	
	// fighter pic
	$image_link = $matches[1];
	
	// fighter name and optional nick name
        $matches = array();
	preg_match('/<div class="module bio_fighter vcard">.*?<h1[^>]*><span[^>]*>([^<]+)<\/span>(.+?)<\/h1>/ism', $scrape, $matches);
	$name = $matches[1];
	$name_lo = $matches[2];
	$nickname = "";

        preg_match('/<span class="nickname">"<em>(.+?)</ism', $scrape, $matches);
	$nickname = $matches[1];
	
	// fighter record
	$wins = 0;
	$losses = 0;
	$draws = 0;
	if (preg_match('/<span class="result">Wins<\/span>.*?<span class="counter">(\d+)<\/span>/ism', $scrape, $matches) > 0) {
		$wins = $matches[1];
	}
	if (preg_match('/<span class="result">Losses<\/span>.*?<span class="counter">(\d+)<\/span>/ism', $scrape, $matches) > 0) {
		$losses = $matches[1];
	}
	if (preg_match('/<span class="result">Draws<\/span>.*?<span class="counter">(\d+)<\/span>/ism', $scrape, $matches) > 0) {
		$draws = $matches[1];
	}
	$record = $wins . "-" . $losses . "-" . $draws;
	
	// other attributes
	$height = "";
	$weight = "";
	$birthday = "";
	$age = "";
	$location = "";
	$country = "";
	$association = "";
	$weightclass = "";
	if (preg_match('/<span class="item height">.*?Height<br \/>.*?<strong>(.+?)<\/strong>/ism', $scrape, $matches) > 0) {
		$height = $matches[1];
	}
	if (preg_match('/<span class="item height">.*?Weight<br \/>.*?<strong>(.+?)<\/strong>/ism', $scrape, $matches) > 0) {
		$weight = $matches[1];
	}
	if (preg_match('/<span class="item birthday">.*?Birthday:\s*(?:<span[^>]*>)*(.+?)(?:<\/span>)<br \/>.*?<strong>AGE: (\d+)<\/strong>/ism', $scrape, $matches) > 0) {
		$birthday = $matches[1];
		$age = $matches[2];
	}

        if (preg_match('/<span itemprop="addressLocality" class="locality">([^<]+)<\/span>/', $scrape, $matches) > 0) {
                $location = $matches[1];
        }

        if (preg_match('/<strong itemprop="nationality">([^<]+)<\/strong>/', $scrape, $matches) > 0) {
                $country = $matches[1];
        }

	if (preg_match('/<h5 class="item association">.*?Association:.*?<strong><span[^>]*><a[^>]*><span[^>]*>(.+?)<\/span>/ism', $scrape, $matches) > 0) {
		$association = $matches[1];
	}
	if (preg_match('/<h6 class="item wclass">.*?Class: <strong[^>]*>(.+?)<\/strong>/ism', $scrape, $matches) > 0) {
		$weightclass = $matches[1];
	}
	
	// compile profile
	$profile = array();
	$profile[] = array("k" => "name", "v" => $name);
	if ($nickname != "") {
		$profile[] = array("k" => "nick name", "v" => $nickname);
	}
	$profile[] = array("k" => "record", "v" => $record);
	$profile[] = array("k" => "Height", "v" => $height);
	$profile[] = array("k" => "Weight", "v" => $weight);
	$profile[] = array("k" => "Birthday", "v" => $birthday);
	$profile[] = array("k" => "Age", "v" => $age);
	$profile[] = array("k" => "Location", "v" => trim($location));
	$profile[] = array("k" => "Country", "v" => $country);
	$profile[] = array("k" => "Association", "v" => $association);
	$profile[] = array("k" => "Weight Class", "v" => $weightclass);
	
	// fighter stats
	$fights = array();
	
	preg_match('/<h2>Fight History<\/h2>.*?<div class="content table">.*?<table border="1">.*?<tr.+?<\/tr>(.+?)<\/table>/ism', $scrape, $matches);
	$tables = $matches[1];
	
	$doc = new DOMDocument();
	$tables = str_replace("&nbsp;", "", preg_replace('/<img[^>]*?>/ims', '', $tables));
	$doc->loadXML('<?xml version="1.0"?><table>' . str_replace('&', '&amp;', $tables) . '</table>');
	
	foreach($doc->getElementsByTagName("tr") as $row) {
		$fight = getFightFromRow($row);
		
		if ($fight["opponent"] == "") {
			continue;	
		}
		$fights[] = $fight;
	}
	
	$json["data"] = array(
		"imagelink" => $image_link,
		"profile" => $profile,
		"fights" => $fights
	);
} else {
	$json["success"] = false;
	$json["info"] = "No fighters found.";
}
	
echo json_encode($json);

?>