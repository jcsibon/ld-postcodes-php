<?php
// PROVISOIRE

$now = 1501826400;

mkdir("output/".date("ymdHi",$now));

$data = array_map("str_getcsv", file("output/".date("ymdHi",$now)."/data.csv",FILE_SKIP_EMPTY_LINES));
array_shift($data);

foreach(array_map("str_getcsv", file("data/170701/codes.csv",FILE_SKIP_EMPTY_LINES)) as $code) {
	$codes[intval($code[0])]=["value"=>$code[0],"lat"=>$code[1],"lon"=>$code[2],"city"=>$code[2]];
}

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