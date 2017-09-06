<?php

require __DIR__ . '/vendor/autoload.php';

ini_set("memory_limit", "-1");
set_time_limit(0);
error_reporting(E_ALL);
$now = 1504512000;
$timestamp = 1708040800;



function distance(
  $latitudeFrom, $longitudeFrom, $latitudeTo, $longitudeTo, $earthRadius = 6371000)
{
  // convert from degrees to radians
  $latFrom = deg2rad($latitudeFrom + 0);
  $lonFrom = deg2rad($longitudeFrom + 0);
  $latTo = deg2rad($latitudeTo + 0);
  $lonTo = deg2rad($longitudeTo + 0);

  $lonDelta = $lonTo - $lonFrom;
  $a = pow(cos($latTo) * sin($lonDelta), 2) +
    pow(cos($latFrom) * sin($latTo) - sin($latFrom) * cos($latTo) * cos($lonDelta), 2);
  $b = sin($latFrom) * sin($latTo) + cos($latFrom) * cos($latTo) * cos($lonDelta);

  $angle = atan2(sqrt($a), $b);
  return $angle * $earthRadius;
}
// Creating output folder

mkdir("output/".date("ymdHi",$now));

// Loading sources

$data = array();

foreach(array_map("str_getcsv", file("input/170701/shops.csv",FILE_SKIP_EMPTY_LINES)) as $shop) {
	$shops[intval($shop[0])]=["name"=>$shop[1],"lat"=>$shop[2],"lon"=>$shop[3]];
}

foreach(array_map("str_getcsv", file("input/170701/codes.csv",FILE_SKIP_EMPTY_LINES)) as $code) {
	$codes[intval($code[0])]=["value"=>$code[0],"lat"=>$code[1],"lon"=>$code[2]];
}

// Calcul des localisations moyennes des codes postaux

$companyScope = 0;

foreach ($shops as $shopKey => $shop) {

	if($shop["lat"]!='') {

		echo $shopKey." : ".$shop["name"].PHP_EOL;
		echo "postcode,shopcode,flydist,distance,duration".PHP_EOL;
		// echo "Sélection<br>";

		$shopScope = 0;
		$shopData = array();
		$destinations = array();
		$apirequests = array();
		$apiresults = array();

		foreach($codes as $codeKey => $code) {

			$flydist = round(distance($shop["lat"],$shop["lon"],$code["lat"],$code["lon"]),0);

			if($flydist< 1000 * 200)
			{
				echo $code["value"].",".$shop["lat"].",".$shop["lon"].",".$code["lat"].",".$code["lon"].",".$flydist.PHP_EOL;

				$shops[$shopKey]["postcodes"][$shopScope]=array($code["value"],$shop["lat"], $shop["lon"], $code["lat"], $code["lon"], $flydist);
				$destinations[] = $code["lat"].",".$code["lon"];

				$shopScope++;
				$companyScope++;

				if($shopScope % 99 == 0)
				{

					$file_get_contents = json_decode(file_get_contents("https://maps.googleapis.com/maps/api/distancematrix/json?key=AIzaSyD5HLO-kSS3rsELg0FwiMTLSfPeiElsUbo&origins=".$shop["lat"].",".$shop["lon"]."&destinations=".implode("|", $destinations)."&departure_time=".$timestamp."&language=fr-FR"),1);

					if(!isset($file_get_contents['rows'][0]))
					{
						echo "https://maps.googleapis.com/maps/api/distancematrix/json?key=AIzaSyD5HLO-kSS3rsELg0FwiMTLSfPeiElsUbo&origins=".$shop["lat"].",".$shop["lon"]."&destinations=".implode("|", $destinations)."&departure_time=".$timestamp."&language=fr-FR";
						die(json_encode($file_get_contents));					
					}

					array_push($apiresults, $file_get_contents['rows'][0]['elements']);
					$destinations=array();
					sleep(5);
				}
			}

		}
		if(count($destinations))
		{
			$file_get_contents = json_decode(file_get_contents("https://maps.googleapis.com/maps/api/distancematrix/json?key=AIzaSyD5HLO-kSS3rsELg0FwiMTLSfPeiElsUbo&origins=".$shop["lat"].",".$shop["lon"]."&destinations=".implode("|", $destinations)."&departure_time=".$timestamp."&language=fr-FR"),1);
			if(!isset($file_get_contents['rows'][0]))
			{
				echo "https://maps.googleapis.com/maps/api/distancematrix/json?key=AIzaSyD5HLO-kSS3rsELg0FwiMTLSfPeiElsUbo&origins=".$shop["lat"].",".$shop["lon"]."&destinations=".implode("|", $destinations)."&departure_time=".$timestamp."&language=fr-FR";
				die(json_encode($file_get_contents));						
			}

			array_push($apiresults, $file_get_contents['rows'][0]['elements']);
			$destinations=array();
			sleep(5);	
		}


		$apiresults = array_merge(...$apiresults);

		foreach($apiresults as $resultKey => $result)
		{
			if(isset($result["distance"]["value"]) && isset($result["duration"]["value"]))
			{
				array_push($shopData, array(
					"shopcode"	=>	substr("0".$shopKey,-5),
					"shoplat"	=>	$shops[$shopKey]["postcodes"][$resultKey][1],
					"shoplon"	=>	$shops[$shopKey]["postcodes"][$resultKey][2],
					"postcode"	=>	$shops[$shopKey]["postcodes"][$resultKey][0],
					"codelat"	=>	$shops[$shopKey]["postcodes"][$resultKey][3],
					"codelon"	=>	$shops[$shopKey]["postcodes"][$resultKey][4],
					"flydist"	=>	$shops[$shopKey]["postcodes"][$resultKey][5],
					"distance"	=>	$result["distance"]["value"],
					"duration"	=>	$result["duration"]["value"]
					)
				);				
			}
		}

		$fp = fopen("output/".date("ymdHi",$now)."/".substr("0".$shopKey,-5).".csv", 'w');

		fputcsv($fp, ["shopcode", "shoplat", "shoplon", "postcode", "codelat", "codelon", "flydist", "distance", "duration"]);

		foreach ($shopData as $row) {
		    fputcsv($fp, $row);
		}
		fclose($fp);

		array_push($data, $shopData);
	}
}

$data = array_merge(...$data);

$fp = fopen('data.csv', 'w');

fputcsv($fp, ["shopcode", "shoplat", "shoplon", "postcode", "codelat", "codelon", "flydist", "distance", "duration"]);

foreach ($data as $row) {
    fputcsv($fp, $row);
}

fclose($fp);



/*
raccordement à faire
*/

// PROVISOIRE
$now = 1501826400;
$data = array_map("str_getcsv", file("output/".date("ymdHi",$now)."/data.csv",FILE_SKIP_EMPTY_LINES));
array_shift($data);


// Analyse par code postal

$postData = array();

foreach($data as $row) {
	if($row[8] > 0 && $row[8] <= 2 * 3600)
		$postData[$row[3]][]=["postcode"=>$row[3],"shopcode"=>$row[0],"flydist"=>$row[6],"distance"=>$row[7],"duration"=>$row[8]];
}

foreach($postData as $key=>$row) {

	$duration = array();
	foreach ($row as $subkey => $subrow)
	{
	    $duration[$subkey] = $subrow['duration'];
	}
	array_multisort($duration, SORT_ASC, $row);

	$break = 4;
	foreach ($row as $subkey => $subrow)
	{
		if($subrow['duration'] > 3600 || $subkey > 3)
		{
			$break  = $subkey + 1;
			break;
		}
	}
	$postData[$key] = array_slice($row, 0, $break);

	// $postcodes[$row[0]][]=["postcode"=>$row[0],"shopcode"=>$row[1],"flydist"=>$row[2],"distance"=>$row[3],"duration"=>$row[4]];
}

ksort($postData);

$postData = array_merge(...array_values($postData));

$fp = fopen("output/".date("ymdHi",$now)."/data-filtered.csv", 'w');

fputcsv($fp, ["postcode", "shopcode", "flydist", "distance", "duration"]);

foreach ($postData as $row) {
    fputcsv($fp, [$row["postcode"], $row["shopcode"], $row["flydist"], $row["distance"], $row["duration"]]);
}

fclose($fp);

// Génération des fichiers destinés aux magasins

if(!is_dir("output/".date("ymdHi",$now)."/shops"))
	mkdir("output/".date("ymdHi",$now)."/shops");

$shopData = array();

foreach($postData as $row) {
	$shopData[$row["shopcode"]][]=["shopcode"=>$row["shopcode"],"postcode"=>$row["postcode"],"distance"=>round($row["distance"]/1000,0),"duration"=>gmdate('H\hi',$row["duration"])];
}

foreach($shopData as $key=>$row) {

	$duration = array();
	foreach ($row as $subkey => $subrow)
	{
	    $duration[$subkey] = $subrow['duration'];
	}
	array_multisort($duration, SORT_ASC, $row);

	$shopData[$key] = $row;
}



foreach ($shopData as $key=>$row) {

	echo "/shops/".substr("0".$key,-5).".xlsx".PHP_EOL;
	$objPHPExcel = new PHPExcel;
	$s = $objPHPExcel->getActiveSheet();
	$s->fromArray(["Magasin", "Code postal", "Distance (km)", "Trajet"], null, 'A1');
	$s->fromArray($row, null, 'A2');
	$writer = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
	$writer->save("output/".date("ymdHi",$now)."/shops/".substr("0".$key,-5).'.xlsx');

}
