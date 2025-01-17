<?php
include_once('../../config/symbini.php');
include_once('classes/GamesWhereManager.php');
header("Content-Type: text/html; charset=".$CHARSET);

$generalVariable = $_REQUEST['var1'];
$formVariable = $_POST['formvar'];
$optionalVariable = array_key_exists('optvar',$_REQUEST)?$_REQUEST['optvar']:'';
$collid = $_REQUEST['collid'];
$formSubmit = array_key_exists('formsubmit',$_REQUEST)?$_REQUEST['formsubmit']:'';

//Sanitation
if(!is_numeric($collid)) $collid = 0;

$whereManager = new GamesWhereManager();


?>
<!DOCTYPE html>
<html>
	<head>
		<title>Where in the World</title>
		<?php
		$activateJQuery = true;
		include_once($SERVER_ROOT.'/includes/head.php');
		?>
		<link href="css/ol.css" type="text/css" rel="stylesheet" />
		<style>
			body { background-color: rgb(255, 250, 240); }
			.map { height: 600px; width: 100%; border: 2px solid #000; }
			.thumb span { position:absolute; visibility:hidden; }
			.thumb:hover span { visibility:visible; top:100px; left:1350px; }
		</style>

		<script src="js/ol.js" type="text/javascript"></script>
		<script src="<?php echo $CLIENT_ROOT; ?>/js/jquery.js" type="text/javascript"></script>
		<script src="<?php echo $CLIENT_ROOT; ?>/js/jquery-ui.js" type="text/javascript"></script>
		<script type="text/javascript">
			var parameters = location.search.substring(1);
			if(parameters.substring(0,5) == "Debug") var Debug = parameters.substring(6,7);
			function ShowImage(URL,Name) {
				var w = window.open(URL,"","top=100");
				w.addEventListener('load', function(){ w.document.title = Name; });
			}

			function openHelpPopup(){
				window.open("Help.html","Ratting","width=750,height=760,left=150,top=200,toolbar=0,status=0,");
			}
		</script>
		<script type="text/javascript">
			var MinLon = -125.64;
			var MaxLon = -66.480;
			var MinLat = 20.763;
			var MaxLat = 50.393;
			var Zoom = 6;
			var Loops = 1;
			var States = "";
			var Radius = 0;
			var Hint = "";

			var map = new ol.Map({
				target: 'map',
				loadTilesWhileAnimating: true,
				view: new ol.View({
					center: ol.proj.fromLonLat([-96,17]),
					zoom: Zoom,
					zoomFactor: 1.4
				})
			});
			Polo = new ol.layer.Tile({
				title: 'Political',
				//type: 'base',
				source: new ol.source.OSM()
			})
			map.addLayer(Polo);

			Topo = new ol.layer.Tile({
				title: 'Topo',
				//type: 'base',
				source: new ol.source.XYZ({
					url: 'https://{a-c}.tile.opentopomap.org/{z}/{x}/{y}.png'
					})
				})
			map.addLayer(Topo);

			Satellite = new ol.layer.Tile({
				title: 'Satellite',
				//type: 'base',
				source: new ol.source.XYZ({
					url: 'https://clarity.maptiles.arcgis.com/arcgis/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}'
					})
				})
			map.addLayer(Satellite);

			Landscape = new ol.layer.Tile({
				title: 'Landscape',
				//type: 'base',
				source: new ol.source.XYZ({
					url: 'https://{a-c}.tile.thunderforest.com/landscape/{z}/{x}/{y}.png?apikey=7bd5ed2197cf4da29fa26de0ba6530cc'
					})
				})
			map.addLayer(Landscape);

			Polo.setZIndex(1001); //Bring political map to the top.

			var scaleline = new ol.control.ScaleLine();
			map.addControl(scaleline);

			var {fromLonLat} = ol.proj;	//Turns out to be necessary to use fromLonLat.

			map.getView().on('change:resolution', function()
				{
				SetMidPoint();
				});

			map.on('moveend', function()
				{
				SetMidPoint();
				});

			function SetMidPoint()
				{
				var extent = map.getView().calculateExtent(map.getSize());
				var MinPoint = new ol.geom.Point([0,0]);
				var MaxPoint = new ol.geom.Point([0,0]);
				MinPoint = ol.proj.transform([extent[0], extent[1]], 'EPSG:3857', 'EPSG:4326');
				MaxPoint = ol.proj.transform([extent[2], extent[3]], 'EPSG:3857', 'EPSG:4326');
				MinLat=MinPoint[1];
				MaxLat=MaxPoint[1];
				MinLon=MinPoint[0];
				MaxLon=MaxPoint[0];
				}


			map.on('singleclick', function(evt)
				{ //Display guesses
				if(document.getElementById('List').innerHTML == "")
					{
					alert("Select region first");
					return;
					}
				var pixel = evt.coordinate;
				var location = ol.proj.toLonLat(pixel)
				ClickLon = location[0];
				ClickLat = location[1];

				var Kilo = Math.floor(DegreesToKilo(ClickLat,ClickLon,PrimLat,PrimLon));
				AddGuess(pixel,Kilo);

				if(document.CheckForm.RingCheck.checked)
					DrawRing(ClickLon,ClickLat,PrimLon,PrimLat,Radius); //Radius is a global variable here.
				document.getElementById('ajaxDiv').innerHTML = "Guess "+Loops;
				Loops++;
				});


			function DrawRing(ClickLon,ClickLat,PrimLon,PrimLat,Kilo)
				{
				if(typeof CircleLayer !== 'undefined')
					map.removeLayer(CircleLayer);
				var circle = new ol.geom.Circle(ol.proj.transform([ClickLon, ClickLat], 'EPSG:4326', 'EPSG:3857'), 1000*Kilo)
				var CircleFeature = new ol.Feature(circle);
				var CircleLayerSource = new ol.source.Vector({
					projection: 'EPSG:3857',
					});
				CircleLayerSource.addFeatures([CircleFeature]);
				CircleLayer = new ol.layer.Vector({source: CircleLayerSource});
				map.addLayer(CircleLayer);
				CircleLayer.setZIndex(1001);//Bring the circle to the top.
				}


			function AddGuess(GuessPoint,Kilo)
				{
				GuessDot = new ol.Feature({
					geometry: new ol.geom.Point(GuessPoint),
					});

				GuessDot.setStyle(new ol.style.Style({
					image: new ol.style.Circle({
						radius: 4,
						fill: new ol.style.Fill({color: 'white'}),
						stroke: new ol.style.Stroke({color: [0,0,0], width: 3 }),
						title: "Test",
						}),
					text: new ol.style.Text({
						anchor: [10,10],
						font: '15px Calibri,sans-serif',
						text: Kilo.toString(),
						fill: new ol.style.Fill({color: 'black'}),
						offsetX: 0,
						offsetY: -12
					})
				}))
				GuessLayerSource.addFeature(GuessDot);
				GuessLayer.setZIndex(1001);
				}

			function Reveal()
				{
				if(document.getElementById('List').innerHTML == "")
					{
					alert("Select region first");
					return;
					}
				RevealDot = new ol.Feature({
					geometry: new ol.geom.Point(ol.proj.fromLonLat([Number(PrimLon), Number(PrimLat)])),
					});

				RevealDot.setStyle(new ol.style.Style({
					image: new ol.style.Circle({
						radius: 5,
						fill: new ol.style.Fill({color: 'red'}),
						stroke: new ol.style.Stroke({color: [0,0,0], width: 3 }),
						title: "Test",
						}),
					text: new ol.style.Text({
						anchor: [10,10],
						 font: '15px Calibri,sans-serif',
						 text: 'Target',
						 fill: new ol.style.Fill({color: 'black'}),
						 offsetX: 0,
						 offsetY: -12
					   })
					}))
				GuessLayerSource.addFeature(RevealDot);
				}

			function WaitMessage()
				{
				WaitDot = new ol.Feature({
					geometry: new ol.geom.Point(ol.proj.fromLonLat([(MinLon+MaxLon)/2, (MinLat+MaxLat)/2])),
					});	//Called Dot, but really just used to display the "Searching..." message in the center of the map.

				WaitDot.setStyle(new ol.style.Style({
					text: new ol.style.Text({
						anchor: [10,10],
						 font: '50px Calibri,sans-serif',
						 text: 'Searching...',
						 fill: new ol.style.Fill({color: 'black'}),
						 offsetX: 0,
						 offsetY: -12
					   })
					}))
				GuessLayerSource.addFeature(WaitDot);
				GuessLayer.setZIndex(1001);
				}

			function ResetGuessLayer()
				{
				if(typeof GuessLayer !== 'undefined')
					map.removeLayer(GuessLayer);
				if(typeof CircleLayer !== 'undefined')
					map.removeLayer(CircleLayer);
				GuessLayerSource = new ol.source.Vector;
				GuessLayer = new ol.layer.Vector({source: GuessLayerSource });
				map.addLayer(GuessLayer);
				document.getElementById('ajaxDiv').innerHTML = "";
				}


			function GetSpecimens()
				{
				ResetGuessLayer();
				document.getElementById('List').innerHTML = "";
				document.getElementById('HintText').innerHTML = "";
				Loops = 1;
				WaitMessage();
				var ajaxRequest;
				try
					{
					ajaxRequest = new XMLHttpRequest();
					}
				catch(e)
					{
					alert("Problem: "+e);
					return false;
					}
				ajaxRequest.onreadystatechange = function()
					{
					if(ajaxRequest.readyState == 4)
						{
						ResetGuessLayer();
						var Message = ajaxRequest.responseText;
						if(Message.substring(0,5) == "Error")
							{ //Used to display error messages from the ajax file.
							alert(Message.substring(7));
							return;
							}
						if(Message.substring(0,5) == "Debug")
							{
							alert(Message);
							return;
							}
						if(Message.length < 40)
							{
							//alert("Timed out; Try again");
							alert(Message);
							}
						else
							{
							FormatList(ajaxRequest.responseText);
							}
						}
					}
				var Query = "?MinLat="+MinLat+"&MaxLat="+MaxLat+"&MinLon="+MinLon+"&MaxLon="+MaxLon+"&States="+States;
					if(Debug == 1 || Debug == 2)
						Query += "&Debug="+Debug;
				ajaxRequest.open("GET", "rpc/WhereInTheWorld.php"+Query, true);
				ajaxRequest.send(null);
				}

			function FormatList(Response)
				{ //Take the response from the ajax file and format it
				var AllLines = Response.split("\r\n");
				var PrimPoint = AllLines[0].split(",");
				PrimLon = PrimPoint[0];
				PrimLat = PrimPoint[1];
				var Names = AllLines[1].split(",");
				var Count = AllLines[2].split(",");
				var ImageURL = AllLines[3].split(",");
				Hint = AllLines[4];
				if(Hint.length > 0)
					document.getElementById('HintButton').disabled=false;
				else
					document.getElementById('HintButton').disabled=true;
				var Output = "<table>";
				for(i=0;i<Names.length;i++)
					{
					if(ImageURL[i] != "" && ImageURL[i] != "empty")
						{
						Output=Output+"<tr><td>"+Names[i]+" ("+Count[i]+")</td><td><a class='thumb' href='#'><img src='"+ImageURL[i]+"' height=35 onclick='ShowImage('"+ImageURL[i]+"','"+Names[i]+"')'><span><img src='"+ImageURL[i]+"' width=600></span></td></tr>\r\n";
						}
					else
						Output=Output+"<tr style='height:25px;'><td>"+Names[i]+" ("+Count[i]+")</td></tr>\r\n";
					}
				Output = Output+"</table>";
				document.getElementById('List').innerHTML = Output;
				document.getElementById('RevealButton').disabled=false;
				document.getElementById("HintText").innerHTML = "";
				}

			function DegreesToKilo(Lat1,Lon1,Lat2,Lon2)
				{
				PI = 3.14159
				var DtoR = PI/180;
				rlat1 = (Lat1*DtoR);
				rlat2 = (Lat2*DtoR);
				rlon = (Lon2 - Lon1)*DtoR;
				Posdist = 2 * 60 * (180 / PI) * Math.acos(Math.sin(rlat1) * Math.sin(rlat2) +  Math.cos(rlat1) * Math.cos(rlat2) *Math.cos(rlon));
				//This should be accurate for actual distance.
				//However, though this will draw a fairly accurate circle near 20 - 30 degrees latitude, for Mercator projection
				//it needs to be adjusted for latitude.

				//For the southern hemisphere, same metrics apply but need to change the latitude sign.
				if(Lat1 < 0)
					Lat1 = -Lat1;
				if(Lat2 < 0)
					Lat2 = -Lat2;

				if(Lat1 > 80)
					Lat1 = 79.9;
				if(Lat2 > 80)
					Lat2 = 79.9;

				//Interpolate the correction factor between these measured values.
				//Each array and each variable in each array goes from 0 to 80 degrees, by 10 degree intervals.
				//Unlikely to need larger than 80 degrees for plant collecting.  If so, then too bad, the circle will be incorrect.
				var Fudge = [
				[0.928, 0.938, 0.9495,0.9715,1.015, 1.0745,1.169, 1.337, 1.6],
				[0.938, 0.944, 0.9625,0.9945,1.0405,1.1099,1.216, 1.389, 1.7],
				[0.9495,0.9625,0.987, 1.0225,1.0845,1.159,1.276, 1.4585, 1.8],
				[0.9715,0.9945,1.0225,1.071, 1.1355,1.2305,1.361, 1.5715, 1.95],
				[1.015, 1.037, 1.0845,1.1355,1.22,  1.316, 1.474, 1.7305, 2.12],
				[1.0745,1.1099,1.159,1.2305,1.316, 1.4494, 1.6273,1.924, 2.39],
				[1.169, 1.216, 1.276, 1.361, 1.474, 1.6273,1.89,  2.2325, 2.8],
				[1.337, 1.389, 1.4585, 1.5715, 1.7305, 1.924, 2.2325, 2.747, 3.75],
				[1.6, 1.7, 1.8, 1.95, 2.12, 2.39, 2.8, 3.75, 6]];

				Lat1 /= 10;
				Lat2 /= 10;

				//The Low and High values bracket the true value with integers.
				Low1 = (Math.floor(Lat1));
				High1 = (Math.ceil(Lat1));
				Low2 = (Math.floor(Lat2));
				High2 = (Math.ceil(Lat2));
				//Ratios
				RL1 = (Lat1-Low1); //Weights the higher value.  The higher this is, the closer to the high value.
				RH1 = (High1 - Lat1); //etc.
				RL2 = (Lat2-Low2);
				RH2 = (High2-Lat2);
				Y1 = RH1*(Fudge[Low1][Low2]*RH2+Fudge[Low1][High2]*RL2);
				Y2 = RL1*(Fudge[High1][Low2]*RH2+Fudge[High1][High2]*RL2);
				OneY = (Y1+Y2);
				Radius = Posdist*OneY;//Radius is a global variable, so no return necessary
				return Posdist;
				}

			function ShowSelectedState(selectObj)
				{
				States = "";
				var StateList = document.getElementById("StateSelect");
				for(var i=0;i<StateList.length;i++)
					if(StateList.options[i].selected)
						{
						if(States == "")
							States = StateList.options[i].text;
						else
							States = States+", "+StateList.options[i].text;
						}
				document.getElementById("ShowState").innerHTML = States;
				}

			function ShowHint()
				{
				document.getElementById("HintText").innerHTML = "Hint:<br>"+Hint;
				}

		</script>
		<script type="text/javascript">
			StateList=["Alabama","Alaska","Arizona","Arkansas","California","Colorado","Connecticut","Delaware","Florida","Georgia","Hawaii","Idaho","Illinois","Indiana","Iowa","Kansas","Kentucky","Louisiana","Maine","Maryland","Massachusetts","Michigan","Minnesota","Mississippi","Missouri","Montana","Nebraska","Nevada","New Hampshire","New Jersey","New Mexico","New York","North Carolina","North Dakota","Ohio","Oklahoma","Oregon","Pennsylvania","Rhode Island","South Carolina","South Dakota","Tennessee","Texas","Utah","Vermont","Virginia","Washington","West Virginia","Wisconsin","Wyoming"];
			for(var i=0;i<StateList.length;i++)
				{
				document.write("<option>"+StateList[i]+"</option>");
				}

			function ResetAll()
				{
				State="Full Region";
				document.getElementById("ShowState").innerHTML = "Full Region";
				document.getElementById("StateSelect").selectedIndex=0;
				document.getElementById("RevealButton").disabled= true;
				}

			function ChangeMapLayer(MapType)
				{
				Topo.setZIndex(0);
				Polo.setZIndex(0);
				Satellite.setZIndex(0);
				Landscape.setZIndex(0);
				if(MapType=='Topo')
					{
					Topo.setZIndex(1001);
					}
				else if(MapType=='Polo')
					{
					Polo.setZIndex(1001);
					}
				else if(MapType=='Landscape')
					Landscape.setZIndex(1001);
				else
					Satellite.setZIndex(1001);

				if(typeof GuessLayerSource !== 'undefined')
					GuessLayer.setZIndex(1001); //Same Z Index, but will push the map down and go on top.
				if(typeof CircleLayerSource !== 'undefined')
					CircleLayer.setZIndex(1001);

				}

			window.onload = ResetAll;
		</script>
	</head>
	<body>
		<?php
		$displayLeftMenu = true;
		include($SERVER_ROOT.'/includes/header.php');
		?>
		<div class="navpath">
			<a href="<?php echo $CLIENT_ROOT; ?>/index.php">Home</a> &gt;&gt;
			<b>Where in the World Game</b>
		</div>
		<!-- This is inner text! -->
		<div id="innertext">
			<h2>Where in the World do these plants grow?</h2>
			<table>
				<tr>
					<td width=1000 height=600>
					<div id="map" class="map"></div>
					</td>
					<td width=10></td>
					<td rowspan=2>
						<div id="List"></div>
					</td>
				</tr>
				<tr>
					<td valign=top>
						<table>
							<tr>
								<td>
									<form name="Use this region">
										<input type="button" onclick="GetSpecimens()" value="Use this region" title="Reset and find new random location in the current region" />
										<br><br>
										<select id="StateSelect" name="State" size="4" multiple onchange="ShowSelectedState(this)" title="Only visible portions of the selected states will be included">
											<option selected="selected">Full Region</option>
										</select>
									</form>
									<br>
									<form name="RevealTarget">
										<input id="HintButton" type="button" onclick="ShowHint()" value="Show Hint" disabled />
										<br><br>
										<input id="RevealButton" type="button" onclick="Reveal()" value="Reveal Target" disabled />
									</form>
									<br>

									<div id = 'ajaxDiv'></div>

									<a href="#" name="Error Handling" title="Where in the World Help" onClick="openHelpPopup();return false;">Instructions</a>
								</td>
								<td width=30></td>
								<td valign=top>
									<!--/td><td width = 50></td><td valign=top-->
									<form name="CheckForm">
										<input type="Checkbox" id="RingCheck" name="RingCheck" checked=checked>Show Distance Ring (Approximate)</input><br><br>
										<input type="Radio" id="UsePolo" name="MapToUse" onclick="ChangeMapLayer('Polo')" checked>Political Map</input><br>
										<input type="Radio" id="UseLandscape" name="MapToUse" onclick="ChangeMapLayer('Landscape')">Landscape Map</input><br>
										<input type="Radio" id="UseTopo" name="MapToUse" onclick="ChangeMapLayer('Topo')">Relief Map</input><br>
										<input type="Radio" id="UseSatellite" name="MapToUse" onclick="ChangeMapLayer('Satellite')">Satellite Map</input><br>
									</form>
									<br>
									<div id='ShowState'></div>
								</td>
								<td valign="center" width="500px">
									<div id='HintText'></div>
								</td>
							</tr>
						</table>
					</td>
				</tr>
			</table>
		</div>
		<?php
		include($SERVER_ROOT.'/includes/footer.php');
		?>
	</body>
</html>