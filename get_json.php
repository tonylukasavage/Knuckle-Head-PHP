<?php 

function createSelectJSON($content, $pattern, $filename) {
	if (preg_match($pattern, $content, $m, PREG_OFFSET_CAPTURE)) {
		$offset = $m[1][1];
		$weights = substr($content, $offset, strpos($content, '</select>', $offset) - $offset);
		$doc = new DOMDocument();
		$doc->loadXML('<?xml version="1.0"?><root>' . str_replace('selected', '', str_replace('&', '&amp;', $weights)) . '</root>');
		$pairs = array();
		$associations = $doc->getElementsByTagName("option");
		
		foreach($associations as $assoc) {
			$pairs[] = array(
				"k" => $assoc->nodeValue,
				"v" => $assoc->attributes->getNamedItem("value")->nodeValue
			);
		}
		
		$handle = fopen($filename, 'w');
		fwrite($handle, json_encode($pairs));
		fclose($handle);
	}
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

$ch      = curl_init( "http://www.sherdog.com/stats/fightfinder" ); 
curl_setopt_array( $ch, $options ); 
$content = curl_exec( $ch ); 
$err     = curl_errno( $ch ); 
$errmsg  = curl_error( $ch ); 
$header  = curl_getinfo( $ch ); 
curl_close( $ch );

createSelectJSON($content, '/<select[^>]+?name="organization_id"[^>]*>\s*(<option)/ims', 'associations.json');
createSelectJSON($content, '/<select[^>]+?name="weight"[^>]*>\s*(<option)/ims', 'weightclasses.json');

?>