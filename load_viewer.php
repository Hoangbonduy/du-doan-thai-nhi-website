<?php
//////////////////////////////////////////////////////////////////////////////////////////////////////////
// loadViewer.php
// -------------------
// receives HTTP GET and opens Orthanc's built-in web viewer, example:
// - loadViewer.php?series=1.25.667.774535725 (SeriesInstanceUID) -> directly opens the web viewer
// - loadViewer.php?study=1.25.667.7745357254 (StudyInstanceUID) -> shows multiple series within the study
// - loadViewer.php?patient=123456 (PatientID) -> shows multiple studies within the patient
//
// emsy chan
//////////////////////////////////////////////////////////////////////////////////////////////////////////

//Global params
include('config.php');

if ($_GET['series']) {

	if (file_get_contents($orthanc . "/series/" . $_GET['study'])) {
		$seriesUUID = $_GET['series'];
		} else {
		//Use lookup to retrieve SeriesUUID from SeriesUID
		$data = $_GET['series'];
		$curl = curl_init();
		curl_setopt ($curl, CURLOPT_URL, $orthanc . 'tools/lookup');
		curl_setopt ($curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt ($curl, CURLOPT_POST, 1);
		curl_setopt ($curl, CURLOPT_POSTFIELDS, $data);
		$resp = curl_exec($curl);
		curl_close($curl);
		$series = json_decode($resp, TRUE);
		$seriesUUID = $series[0]["ID"];
		}	
	//Use SeriesUUID to load Orthanc webviewer
	if ($seriesUUID != "") {
		echo "<META http-equiv=\"refresh\" content=\"0;URL=" . $orthanc . "/web-viewer/app/viewer.html?series=" . $seriesUUID . "\">";
//		echo "<META http-equiv=\"refresh\" content=\"0;URL=" . $orthanc . "/mcdcm/app/viewer.html?series=" . $seriesUUID . "\">";
		} else {
		echo "Series Not Found";
		}
	} else

if ($_GET['study']) {

	if (file_get_contents($orthanc . "/studies/" . $_GET['study'])) {
		$studyUUID = $_GET['study'];
		} else {
		//Use lookup to retrieve StudyUUID from StudyUID
		$data = $_GET['study'];
		$curl = curl_init();
		curl_setopt ($curl, CURLOPT_URL, $orthanc . 'tools/lookup');
		curl_setopt ($curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt ($curl, CURLOPT_POST, 1);
		curl_setopt ($curl, CURLOPT_POSTFIELDS, $data);
		$resp = curl_exec($curl);
		curl_close($curl);
		$studies = json_decode($resp, TRUE);
		$studyUUID = $studies[0]["ID"];
		}

	//Retrieve list of series from StudyUID
	$study = json_decode(file_get_contents($orthanc . "/studies/" . $studyUUID), TRUE);
	$patientInfo = json_decode(file_get_contents($orthanc . "/patients/" . $study["ParentPatient"]), TRUE);

	$allseries = json_decode(file_get_contents($orthanc . "/studies/" . $studyUUID . "/series"), TRUE);
	//draw IFRAMEd viewer
	echo "<body bgcolor=000000 text=ffffff link=00ffff vlink=00ffff onResize=\"changeHeight()\">\n";
	echo "<table width=100%><tr>\n";
	echo "<td width=200 valign=top>\n";
	echo "<b><font size=+2>" . $patientInfo["MainDicomTags"]["PatientName"] . "</font><br>" . $patientInfo["MainDicomTags"]["PatientID"] . "</b><hr size=1>\n";

	if (!$study["MainDicomTags"]["StudyDescription"]) { $study["MainDicomTags"]["StudyDescription"] = "undefined"; }
	echo "<b>" . $study["MainDicomTags"]["StudyDescription"] . "</b>:<br>\n";
	echo $study["MainDicomTags"]["StudyDate"] . "<br><br>\n";
	foreach ($allseries as $series) {
		if (!$series["MainDicomTags"]["SeriesDescription"]) { $series["MainDicomTags"]["SeriesDescription"] = "undefined"; }
		echo "<a href=" . $orthanc . "/web-viewer/app/viewer.html?series=" . $series["ID"] . " target=\"viewFrame\">" . $series["MainDicomTags"]["SeriesDescription"] . "</a><br>";
//		echo "<a href=" . $orthanc . "/mcdcm/viewer.html?series=" . $series["ID"] . " target=\"viewFrame\">" . $series["MainDicomTags"]["SeriesDescription"] . "</a><br>";
		}

	echo "</td><td valign=top><iframe border=2 width=100% height=600 id=\"viewFrame\" name=\"viewFrame\"></iframe></td></tr></table>\n";
	echo "</body>\n";
	echo "<script>\n";
	echo "document.getElementById(\"viewFrame\").height = window.innerHeight - 50;\n";
	echo "function changeHeight(){\n";
	echo "	document.getElementById(\"viewFrame\").height = window.innerHeight - 50;\n";
	echo "	}\n";
	echo "</script>\n";

	} else

if ($_GET['patient']) {

	if (file_get_contents($orthanc . "/patients/" . $_GET['patient'])) {
		$patientUUID = $_GET['patient'];
		} else {
		//Use lookup to retrieve PatientUUID from PatientUID
		$data = $_GET['patient'];
		$curl = curl_init();
		curl_setopt ($curl, CURLOPT_URL, $orthanc . 'tools/lookup');
		curl_setopt ($curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt ($curl, CURLOPT_POST, 1);
		curl_setopt ($curl, CURLOPT_POSTFIELDS, $data);
		$resp = curl_exec($curl);
		curl_close($curl);
		$patient = json_decode($resp, TRUE);
		$patientUUID = $patient[0]["ID"];
		}
	//Retrieve list of series from StudyUID
	$patientInfo = json_decode(file_get_contents($orthanc . "/patients/" . $patientUUID), TRUE);

	//draw IFRAMEd viewer
	echo "<body bgcolor=000000 text=ffffff link=00ffff vlink=00ffff onResize=\"changeHeight()\">\n";
	echo "<table width=100%><tr>\n";
	echo "<td width=200 valign=top>\n";
	echo "<b><font size=+2>" . $patientInfo["MainDicomTags"]["PatientName"] . "</font><br>" . $patientInfo["MainDicomTags"]["PatientID"] . "</b><hr size=1>\n";

	$studies = json_decode(file_get_contents($orthanc . "/patients/" . $patientUUID . "/studies"), TRUE);
	foreach ($studies as $study) {
		if (!$study["MainDicomTags"]["StudyDescription"]) { $study["MainDicomTags"]["StudyDescription"] = "undefined"; }
		echo "<b>" . $study["MainDicomTags"]["StudyDescription"] . "</b>:<br>\n";
		echo $study["MainDicomTags"]["StudyDate"] . "<br><br>\n";
		//Retrieve list of series from StudyUID
		$allseries = json_decode(file_get_contents($orthanc . "/studies/" . $study["ID"] . "/series"), TRUE);
		foreach ($allseries as $series) {
			if (!$series["MainDicomTags"]["SeriesDescription"]) { $series["MainDicomTags"]["SeriesDescription"] = "undefined"; }
			echo "<a href=" . $orthanc . "/web-viewer/app/viewer.html?series=" . $series["ID"] . " target=\"viewFrame\">" . $series["MainDicomTags"]["SeriesDescription"] . "</a><br>";
//			echo "<a href=" . $orthanc . "/mcdcm/viewer.html?series=" . $series["ID"] . " target=\"viewFrame\">" . $series["MainDicomTags"]["SeriesDescription"] . "</a><br>";
			}
		echo "<br><hr size=1><br>\n";
	}
	echo "</td><td valign=top><iframe border=2 width=100% height=600 id=\"viewFrame\" name=\"viewFrame\"></iframe></td></tr></table>\n";
	echo "</body>\n";
	echo "<script>\n";
	echo "document.getElementById(\"viewFrame\").height = window.innerHeight - 50;\n";
	echo "function changeHeight(){\n";
	echo "	document.getElementById(\"viewFrame\").height = window.innerHeight - 50;\n";
	echo "	}\n";
	echo "</script>\n";

	}

?>


