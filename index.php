<!DOCTYPE html>
<?php
$wwwRoot = dirname(__FILE__);
$imgRel = "/upload/testbild.jpg";
if(isset($_FILES['upload']['tmp_name']) 
	&& file_exists($_FILES['upload']['tmp_name'])
	&& move_uploaded_file($_FILES['upload']['tmp_name'], dirname(__FILE__)."/upload/" . basename( $_FILES['upload']['name']))) {
    	$imgRel = "/upload/" . basename( $_FILES['upload']['name']);
}
$imgAbs = $wwwRoot.$imgRel;
$imgSize = getimagesize($imgAbs);
$exif = exif_read_data($imgAbs, 0, true);
?>
<html>
<head>
<title>Bilderkompass</title>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="leaflet/leaflet.css" />
<script src="functions.js"></script>
<script src="leaflet/leaflet.js"></script>
<script src="jquery/jquery.min.js"></script>
<script>
$( document ).ready(function() {
	$(".markerButtons").click(function() {
		$(".markerButtons").each(function() {
			$(this).removeAttr('disabled');
		});
		$(this).attr('disabled', 'disabled');
	});
	L.LatLng.prototype.bearingTo = function(other) {
		var d2r		= L.LatLng.DEG_TO_RAD;
		var r2d		= L.LatLng.RAD_TO_DEG;
		var lat1	= this.lat * d2r;
		var lat2	= other.lat * d2r;
		var dLon	= (other.lng-this.lng) * d2r;
		var y		= Math.sin(dLon) * Math.cos(lat2);
		var x		= Math.cos(lat1)*Math.sin(lat2) - Math.sin(lat1)*Math.cos(lat2)*Math.cos(dLon);
		var brng	= Math.atan2(y, x);
		brng		= parseInt( brng * r2d );
		brng		= (brng + 360) % 360;
		return brng;
	};

	function onMapClick(e) {
		var marker = L.marker(e.latlng, {
			draggable: 	true, 
			mapId: 		this.options.mapId,
			posType: 	0,
			prevType: 	0,
		}).bindPopup("<button class='marker-button' data='P'>P</button><button class='marker-button' data='1'>1</button><button class='marker-button' data='2'>2</button><button class='marker-button-delete'>Löschen</button>");
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

		// var picMap_latlng_1 = L.latLng($('#picMap_1_lat').val(), $('#picMap_1_lng').val());
		// var picMap_latlng_2 = L.latLng($('#picMap_2_lat').val(), $('#picMap_2_lng').val());
		// jQuery('#picMap_dist_m1m2').val(picMap_latlng_1.distanceTo(picMap_latlng_2));

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
		// var picMap_distH_Z_m2 = $('#picMap_2_lng').val() - picMap_center.x;
		var picMap_winkel_M1ZN = osmMap_winkel_M1PM2*picMap_distH_Z_m1/picMap_distH_m1m2;
		// var picMap_winkel_M2ZN = osmMap_winkel_M1PM2*picMap_distH_Z_m2/picMap_distH_m1m2;

		var osmMap_Z_winkel1 = (osmMap_latlng_P.bearingTo(osmMap_latlng_1) - picMap_winkel_M1ZN + 360) % 360;
		// var osmMap_Z_winkel2 = (osmMap_latlng_P.bearingTo(osmMap_latlng_2) - picMap_winkel_M2ZN + 360) % 360;

		$('#sichtachse').val(osmMap_Z_winkel1);
		
		$('#exiftoolCmd').text('exiftool -exif:gpslatitude='+ $('#osmMap_P_lat').val() +' -exif:gpslatituderef=N -exif:gpslongitude='+ $('#osmMap_P_lng').val() +' -exif:gpslongituderef=E -exif:gpsimgdirection='+ $('#sichtachse').val() +' -exif:gpsimgdirectionref=T  ' + $('#picUploadName').val());

		// $('#sichtachse').val(Math.round(osmMap_winkel_M1PM2,2));
		//-exif:gpsaltitude=123 -exif:gpsaltituderef=0
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
	var img = {width:<?php echo intval($imgSize[0]);?>, height:<?php echo intval($imgSize[1]);?>}; 
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

	/* Foto */
	var picMap = L.map('picContainer', {
		crs: L.CRS.Simple,
		minZoom: 	-1,
		maxZoom: 	13,
		center: 	[0, 0],
		zoom: 		zoom,
		mapId: 		'picMap',
	});
	var bounds = [[0, 0], [img.height, img.width]];
	picMap.setMaxBounds(bounds);
	L.imageOverlay('<?php echo $imgRel;?>', bounds).addTo(picMap);

	var picPos = {'lat':<?php echo ((gps($exif["GPS"]["GPSLatitude"])!=0) ? gps($exif["GPS"]["GPSLatitude"]) : 0);?>, 'lng':<?php echo ((gps($exif["GPS"]["GPSLongitude"])!=0) ? gps($exif["GPS"]["GPSLongitude"]) : 0);?>};
	
	/* Karte */
	var osmMap = L.map('mapContainer', {mapId:'osmMap'});
	osmMap.on('click', onMapClick);
	picMap.on('click', onMapClick);

	if(picPos.lat!=0 && picPos.lng!=0) {
		$('#osmMap_P_lat').val(picPos.lat);
		$('#osmMap_P_lng').val(picPos.lng);
		// var latlng_P_event = L.latLng(picPos.lat, picPos.lng);
		// osmMap.fireEvent('click', latlng_P_event);
		osmMap.setView([picPos.lat, picPos.lng], 13);
		var latlngPoint = new L.latLng(picPos.lat, picPos.lng);
		osmMap.fireEvent('click', {
			latlng: latlngPoint,
			layerPoint: osmMap.latLngToLayerPoint(latlngPoint),
			containerPoint: osmMap.latLngToContainerPoint(latlngPoint)
		});
	} else {
		osmMap.setView([50.864, 12.185], 13);
	}

	L.tileLayer('http://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
		maxZoom: 18,
		attribution: '',
		id: 'mapbox.streets'
	}).addTo(osmMap);

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
<form action="" method="POST" style="width: 50%; float: left;" enctype="multipart/form-data">
	<fieldset>
		<legend>Bilder-Upload</legend>
		<p>
			<label for="picUpload">Bilddatei hochladen</label>
			<input name="upload" type="file" size="50" id="picUpload"> 
		</p>
		<button type="submit">hochladen</button>
		<p><?php echo ini_get('upload_max_filesize');?></p>
		<input type="text" id="picUploadName" value="<?php echo ((isset($_FILES['upload']['name'])) ? $_FILES['upload']['name'] : '');?>"/>
	</fieldset>
	<fieldset style="display:none;">
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
						<p><?php echo gps($exif["GPS"]["GPSLatitude"], $exif["GPS"]['GPSLatitudeRef']);?></p>
						<p><?php echo gps($exif["GPS"]["GPSLongitude"], $exif["GPS"]['GPSLongitudeRef']);?></p>
						<p><?php echo gps($exif["GPS"]["GPSAltitude"], $exif["GPS"]['GPSAltitudeRef']);?></p>
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
	</fieldset>
</form>
<div style="float:left; width: 50%;">
	<fieldset>
		<legend>exiftool</legend>
		<p>Wenn nach dem Setzen der Marker im Bild und auf der Karte der Befehl nicht aktualisiert wird, dann kann das mit einem kurzen Schubser der Marker erzwungen werden.</p>
		<p id="exiftoolCmd">exiftool -exif:gpslatitude=[LATITUDE] -exif:gpslatituderef=N -exif:gpslongitude=[LONGITUDE] -exif:gpslongituderef=E -exif:gpsimgdirection=[DIRECTION] -exif:gpsimgdirectionref=T -exif:gpsaltitude=[ALTITUDE] -exif:gpsaltituderef=0 [FILE]</p>
	</fieldset>
</div>

<div style="clear:both;float: left; width: 50%;">
	<div id="picContainer" style="width:100%; height: 400px"></div>
</div>

<div style="float: right; width: 50%;">
	<div id="mapContainer" style="width:100%; height: 400px"></div>
</div>
<div style="clear:both;"></div>
<?php
function gps($coordinate, $hemisphere=NULL) {
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
</body>
</html>

