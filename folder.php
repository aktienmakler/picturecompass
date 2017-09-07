<!DOCTYPE html>
<?php
define('IMG_FOLDER_REL', 'upload/AMT_Baudezernat/');
define('WWW_ROOT', dirname(__FILE__)."/");

chdir(IMG_FOLDER_REL);
foreach (glob("*") as $filename) {
	$tmpData = getFileData(IMG_FOLDER_REL.$filename);
	$imgs['raw'][]		= $tmpData;
	$imgs['width'][] 	= $tmpData['width'];
	$imgs['height'][] 	= $tmpData['height'];
	$imgs['exif'][] 	= $tmpData['exif'];
}

$imgSizeMax['width'] = max($imgs['width']);
$imgSizeMax['height'] = max($imgs['height']);
	

function getFileData($path) {
	$pathAbs = WWW_ROOT.$path;
	$tmpSize 	= getimagesize($pathAbs);
	// $pathinfo	=	pathinfo($pathAbs, PATHINFO_FILENAME);
	return array(
		'filename'	=>	pathinfo($pathAbs, PATHINFO_FILENAME),
		'pathAbs'	=>	$pathAbs,
		'pathRel'	=>	$path,
		'exif'		=>	exif_read_data($pathAbs, 0, true),
		'width'		=>	$tmpSize[0],
		'height'	=>	$tmpSize[1],
	);
}

function calculate_string( $mathString ) {
	$mathString = trim($mathString);     // trim white spaces
	$mathString = preg_replace ('[^0-9\+-\*\/\(\) ]', '', $mathString);    // remove any non-numbers chars; exception for math operators
	eval( '$result = (' . $mathString . ');' );
    return $result;
}

function getSensorSize($cam, $orientation=1) {
	switch($cam) {
		default:
		case "NIKON D3000":
			$return = array('width'=>23.60, 'height'=>15.80);
		break;
	}
	if($orientation>4) {
		$tmp = $return;
		$return = array('width'=>$tmp['height'], 'height'=> $tmp['width']);
	}
	return $return;
}

function calcFOV($sensorSize, $focalLength) {
	return round(2*rad2deg (atan(sqrt($sensorSize['width']*$sensorSize['width'] + $sensorSize['height']*$sensorSize['height'])/(2*$focalLength))), 2);
}

function gps($coordinate, $hemisphere=NULL) {
		if(!is_array($coordinate)) return $coordinate;
		for ($i = 0; $i < 3; $i++) {
			$part = explode('/', $coordinate[$i]);
			if (count($part) == 1) {
				$coordinate[$i] = $part[0];
			} else if (count($part) == 2) {
				$coordinate[$i] = (floatval($part[1]==0) ? 0 : floatval($part[0])/floatval($part[1]));
			} else {
				$coordinate[$i] = 0;
			}
		}
		list($degrees, $minutes, $seconds) = $coordinate;
		$sign = ($hemisphere == 'W' || $hemisphere == 'S') ? -1 : 1;
		return $sign * ($degrees + $minutes/60 + $seconds/3600);
}


?>
<html>
<head>
<title>Bilderkompass</title>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<script src="jquery/jquery.min.js"></script>
<link rel="stylesheet" href="leaflet/leaflet-1.2.css" />
<script src="leaflet/leaflet-1.2.js"></script>
<!--
<link rel="stylesheet" href="leaflet/leaflet-0.7.css" />
<script src="leaflet/leaflet-0.7.js"></script>
-->
<script src="Semicircle.js"></script>
<script>
var imgSizeMaxWidth = <?php echo $imgSizeMax['width'];?>;
var imgSizeMaxHeight = <?php echo $imgSizeMax['height'];?>;


$( document ).ready(function() {
	$(".markerButtons").click(function() {
		$(".markerButtons").each(function() {
			$(this).removeAttr('disabled');
		});
		$(this).attr('disabled', 'disabled');
	});

	function storeImageData(id, lat, lng, dir) {
		var img=$('img#imgId_'+id);
		$(img).attr('data-p_lat', lat);
		$(img).attr('data-p_lng', lng);
		$(img).attr('data-p_dir', dir);
		$(img).attr('data-exif_altered', 1);
		$(img).attr('title', 'P_lat:' + lat + ' P_lng:' + lng + ' P_dir: '+dir);
	}
	
	function getMarkerButtons(map) {
		return "<button class='marker-button' data='P'>P</button><button class='marker-button' data='1'>1</button><button class='marker-button' data='2'>2</button><button class='marker-button-delete'>Löschen</button>";
	}

	L.LatLng.prototype.bearingTo = function(other) {
		var lat1	= Math.radians(this.lat);
		var lat2	= Math.radians(other.lat);
		var dLon	= Math.radians(other.lng-this.lng);
		var y		= Math.sin(dLon) * Math.cos(lat2);
		var x		= Math.cos(lat1)*Math.sin(lat2) - Math.sin(lat1)*Math.cos(lat2)*Math.cos(dLon);
		var brng	= Math.atan2(y, x);
		brng		= parseInt( Math.degrees(brng));
		brng		= (brng + 360) % 360;
		return brng;
	};

	function onMapClick(e) {
		var marker = L.marker(e.latlng, {
			draggable: 	true, 
			mapId: 		this.options.mapId,
			posType: 	0,
			prevType: 	0,
			icon: defaultIcon
		}).bindPopup("<span id='marker-lat'>lat</span><br/><span id='marker-lng'>lng</span><br/>"+getMarkerButtons(this.options.mapId));
		marker.on("popupopen", onPopupOpen);
		marker.addTo(this);
	}

	function onPopupOpen() {
		var tempMarker	=	this;
		var mapName		=	tempMarker.options.mapId;
		var map			=	eval(mapName);
		var result 		= 	tempMarker.getLatLng();

		$('.marker-button-delete:visible').click(function () {
			map.removeLayer(tempMarker);
			jQuery('#'+mapName+'_'+tempMarker.options.posType+'_lat').val('');
			jQuery('#'+mapName+'_'+tempMarker.options.posType+'_lng').val('');
		});
		//	Move
		jQuery('#'+mapName+'_'+tempMarker.options.posType+'_lat').val(result.lat);
		jQuery('#'+mapName+'_'+tempMarker.options.posType+'_lng').val(result.lng);
		
		jQuery('#marker-lat').text(result.lat);
		jQuery('#marker-lng').text(result.lng);
		//	Click
		$('.marker-button:visible').click(function () {
			tempMarker.options.posType = $(this).attr('data');
			tempMarker.setIcon(eval('iconMarker'+tempMarker.options.posType));
			

			//	empty
			jQuery('#'+mapName+'_'+tempMarker.options.prevType+'_lat').val('');
			jQuery('#'+mapName+'_'+tempMarker.options.prevType+'_lng').val('');	
			tempMarker.options.prevType = $(this).attr('data');

			//	set new
			jQuery('#'+mapName+'_'+tempMarker.options.posType+'_lat').val(result.lat);
			jQuery('#'+mapName+'_'+tempMarker.options.posType+'_lng').val(result.lng);
		});

		var picMap_distH_m1m2 = Math.abs($('#picMap_1_lng').val()-$('#picMap_2_lng').val());
		jQuery('#picMap_distH_m1m2').val(picMap_distH_m1m2);
		var osmMap_latlng_P = L.latLng($('#osmMap_P_lat').val(), $('#osmMap_P_lng').val());
		var osmMap_latlng_1 = L.latLng($('#osmMap_1_lat').val(), $('#osmMap_1_lng').val());
		var osmMap_latlng_2 = L.latLng($('#osmMap_2_lat').val(), $('#osmMap_2_lng').val());
		var a = osmMap_latlng_P.distanceTo(osmMap_latlng_1);
		var b = osmMap_latlng_P.distanceTo(osmMap_latlng_2);
		var c = osmMap_latlng_1.distanceTo(osmMap_latlng_2);
		jQuery('#osmMap_dist_p-m1').val(a);
		jQuery('#osmMap_dist_p-m2').val(b);
		jQuery('#osmMap_dist_m1-m2').val(c);

		var osmMap_winkel_M1PM2 = Math.degrees(Math.acos((Math.pow(a,2)+Math.pow(b,2)-Math.pow(c,2))/(2*a*b)));
		var picMap_winkel = img.width*osmMap_winkel_M1PM2/$('#picMap_distH_m1m2').val();

		$('#bildwinkel').val(picMap_winkel);
		var picMap_center = {x: img.width/2, y: img.height/2};
		var picMap_distH_Z_m1 = $('#picMap_1_lng').val() - picMap_center.x;
		var picMap_winkel_M1ZN = osmMap_winkel_M1PM2*picMap_distH_Z_m1/picMap_distH_m1m2;
		var osmMap_Z_winkel1 = (osmMap_latlng_P.bearingTo(osmMap_latlng_1) - picMap_winkel_M1ZN + 360) % 360;
		$('#sichtachse').val(osmMap_Z_winkel1);
	}

	// Converts from radians to degrees.
	Math.degrees = function(radians) {
		return radians * 180 / Math.PI;
	};

	// Converts from degrees to radians.
	Math.radians = function(degrees) {
		return degrees * Math.PI / 180;
	};
	
	/**************************************************************************************/
	var container = {width:$("#picContainer").width(), height:$("#picContainer").height()}; 
	/* Die PHP-Bildgrößen könnten noch ein Problem werden. */
	var img = {width:imgSizeMaxWidth, height:imgSizeMaxHeight}; 
	var zoom = Math.floor(Math.log2(Math.max(container.height/img.height, container.width/img.width)));
	var LeafIcon = L.Icon.extend({
		options: {
			iconSize: [25, 41],
			iconAnchor: [12, 41],
			popupAnchor: [1, -34],
			shadowUrl: 'leaflet/images/marker-shadow.png'
		}
	});
	var iconMarkerP = new LeafIcon({
		iconUrl: 'leaflet/images/marker-icon-blue.png',
	});
	var iconMarker1 = new LeafIcon({
		iconUrl: 'leaflet/images/marker-icon-green.png',
	});
	var iconMarker2 = new LeafIcon({
		iconUrl: 'leaflet/images/marker-icon-red.png',
	});
	var defaultIcon = new LeafIcon({
		iconUrl: 'leaflet/images/marker-icon-violet.png',
	});

	/* Foto */
	var picMap = L.map('picContainer', {
		crs: L.CRS.Simple,
		minZoom: 	-10,
		maxZoom: 	13,
		center: 	[0, 0],
		zoom: 		zoom,
		mapId: 		'picMap',
	});

	var bounds = [[0, 0], [img.height, img.width]];
	picMap.setMaxBounds(bounds);
	var picPos = {'lat':0, 'lng': 0};

	/* Karte */
	var osmMap = L.map('mapContainer', {mapId:'osmMap'});
	osmMap.on('click', onMapClick);
	picMap.on('click', onMapClick);
	osmMap.setView([50.864, 12.185], 13);

	L.tileLayer('http://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
		maxZoom: 18,
		attribution: '',
		id: 'mapbox.streets'
	}).addTo(osmMap);

	$('img.images').click(function() {
		var id=$(this).attr('data-imgId');
		$('input#currentImg').val(id);

		L.imageOverlay($(this).attr('src'), bounds).addTo(picMap);
		if($(this).attr('data-p_lat')!=0 && $(this).attr('data-p_lng')!=0) {
			centerPoint = {'lat':$(this).attr('data-p_lat'), 'lng':$(this).attr('data-p_lng'), 'dir':$(this).attr('data-P_dir'), 'fov':$(this).attr('data-fov')};
		} else {
			centerPoint = false;
		}

		//	lösche alle bisherigen Marker und Sichtbereiche
		osmMap.eachLayer(function (layer) { 
			if(layer.options.posType!==undefined || layer.options.radius !== undefined) {
				osmMap.removeLayer(layer);
			}
		});

		if(centerPoint) {
			var marker = L.marker(centerPoint, {
				draggable: 	true, 
				mapId: 		'osmMap',
				posType: 	'P',
				prevType: 	0,
				icon: iconMarkerP
			}).bindPopup(getMarkerButtons(osmMap));
			// marker.setLatLng([centerPoint.lat, centerPoint.lng]);
			marker.on("popupopen", onPopupOpen);
			marker.addTo(osmMap);


			$('#osmMap_P_lat').val(centerPoint.lat);
			$('#osmMap_P_lng').val(centerPoint.lng);
			osmMap.setView([centerPoint.lat, centerPoint.lng], 13);
			var latlngPoint = new L.latLng(centerPoint.lat, centerPoint.lng);
			osmMap.panTo(latlngPoint);

			L.semiCircle(latlngPoint, {
				radius: 250,
				startAngle: parseFloat(centerPoint.dir) - parseFloat(centerPoint.fov/2),
				stopAngle: parseFloat(centerPoint.dir) + parseFloat(centerPoint.fov/2)
			}).addTo(osmMap);

		}
	});
	

	$('button#setData').click(function() {
		if($('#osmMap_P_lat').val()!='' && $('#osmMap_P_lng').val()!='' && $('#sichtachse').val()!='') {
			storeImageData($('input#currentImg').val(), $('#osmMap_P_lat').val(), $('#osmMap_P_lng').val(), $('#sichtachse').val());
		} else {
			alert("nicht genügend Daten zum Speichern vorhanden");
		}
	});
	$('button#getExiftoolCmd').click(function() {
		var output='';
		$('div.allImages img.images').each(function() {
			if($(this).attr('data-exif_altered')==1) {
				output=output.concat('exiftool -exif:gpslatitude='+ $(this).attr('data-p_lat') +' -exif:gpslatituderef=N -exif:gpslongitude='+ $(this).attr('data-p_lng') +' -exif:gpslongituderef=E -exif:gpsimgdirection='+ $(this).attr('data-p_dir') +' -exif:gpsimgdirectionref=T  ' + $(this).attr('src'))+'\n'; 
			}
		});
		alert(output);
	});
	
	// osmMap.fire('click', <Object> data?);
});

</script>
<style type="text/css" rel="StyleSheet">
label {
	width: 200px !important; 
	border: 0px solid red;
	display: block;
}
input {
	width: 200px;
	display: block;
}

</style>

</head>
<body>


<h1>Bilderkompass</h1>
<div class="allImages">

<?php

for($i=0; $i<count($imgs['raw']);$i++) {
	$img = $imgs['raw'][$i];
	$lat = gps($img['exif']["GPS"]["GPSLatitude"], $img['exif']["GPS"]["GPSLatitudeRef"]);
	$lng = gps($img['exif']["GPS"]["GPSLongitude"], $img['exif']["GPS"]["GPSLongitudeRef"]);
?>
	<img src="<?php echo $img['pathRel'];?>" style="float:left; width:200px;" class="images" id="imgId_<?php echo $i;?>" data-imgId="<?php echo $i;?>" data-p_lat="<?php echo $lat;?>" data-p_lng="<?php echo $lng;?>" data-P_dir="<?php 
		echo ($img['exif']["GPS"]["GPSImgDirection"] ? $img['exif']["GPS"]["GPSImgDirection"] : "");?>" data-FOV="<?php
		echo calcFOV(getSensorSize($img['exif']['IFD0']['Model'], $img['exif']['IFD0']['Orientation']), calculate_string($img['exif']['EXIF']['FocalLength']));
		?>" title=""/>
<?php
	if($i>3) break;
}
?>
</div>
<hr style="clear:both;"/>

<div style="float:left; width: 50%;">
	<fieldset>
		<legend>Data</legend>
		<button class="submit" id="setData">setData</button>
		<button class="submit" id="getExiftoolCmd">getExiftoolCmd</button>
	</fieldset>

</div>

<div style="clear:both;float: left; width: 50%;">
	<button id="picContainerMarkerP" style="background-color:#00f;" disabled="disabled">P</button>
	<button id="picContainerMarker1" style="background-color:#0f0;">1</button>
	<button id="picContainerMarker2" style="background-color:#f00;">2</button>

	<div id="picContainer" style="width:100%; height: 400px"></div>
</div>

<div style="float: right; width: 50%;">
	<div id="mapContainer" style="width:100%; height: 400px"></div>
</div>
<div style="clear:both;"></div>
	<fieldset style="display:block;">
		<legend>Marker</legend>
		<input type="hidden" id="picMap_dist_m1m2"/>
		<input type="hidden" id="picMap_distH_m1m2"/>
		<input type="hidden" id="osmMap_dist_p-m1"/>
		<input type="hidden" id="osmMap_dist_p-m2"/>
		<input type="hidden" id="osmMap_dist_m1-m2"/>
		<table>
			<thead>
				<tr>
					<th>Auswahl</th>
					<th>Foto</th>
					<th>Karte</th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<th>Position</th>
					<td>
					</td>
					<td>
						<p><input type="text" readonly="readonly" id="osmMap_P_lat"/></p>
						<p><input type="text" readonly="readonly" id="osmMap_P_lng"/></p>
						<p>&nbsp;</p>
					</td>
				</tr>
				<tr>
					<th style="background-color:green;">Marker 1</th>
					<td>
						<input type="text" readonly="readonly" id="picMap_1_lat"/>
						<input type="text" readonly="readonly" id="picMap_1_lng"/>
					</td>
					<td>
						<input type="text" readonly="readonly" id="osmMap_1_lat"/>
						<input type="text" readonly="readonly" id="osmMap_1_lng"/>
					</td>
				</tr>
				<tr>
					<th style="background-color:red;">Marker 2</th>
					<td>
						<input type="text" readonly="readonly" id="picMap_2_lat"/>
						<input type="text" readonly="readonly" id="picMap_2_lng"/>
					</td>
					<td>
						<input type="text" readonly="readonly" id="osmMap_2_lat"/>
						<input type="text" readonly="readonly" id="osmMap_2_lng"/>
					</td>
				</tr>
				<tr>
					<td></td>
					<td>
						<label for="bildwinkel">Bildbreite in °</label>
						<input type="text" readonly="readonly" id="bildwinkel"/>
						<label for="sichtachse">Sichtachse, Blickrichtung ° (0=Nord, rechtsorientiert)</label>
						<input type="text" readonly="readonly" id="sichtachse"/>
					</td>
					<td></td>
				</tr>
			</tbody>
		</table>
		<input type="hidden" id="currentImg"/>
</fieldset>

</body>
</html>