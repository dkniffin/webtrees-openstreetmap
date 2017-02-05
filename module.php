<?php

namespace vendor\WebtreesModules\OpenStreetMapModule;

use Composer\Autoload\ClassLoader;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Filter;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Module\AbstractModule;
use Fisharebest\Webtrees\Module\ModuleTabInterface;

class OpenStreetMapModule extends AbstractModule implements ModuleTabInterface {

	var $directory;

	public function __construct()
	{
		parent::__construct('OpenStreetMapModule');
		$this->directory = WT_MODULES_DIR . $this->getName();
		$this->action = Filter::get('mod_action');
		// register the namespaces
		$loader = new ClassLoader();
		$loader->addPsr4('vendor\\WebtreesModules\\OpenStreetMapModule\\', $this->directory);
		$loader->register();
	}

	// Extend AbstractModule. Unique internal name for this module. Must match the directory name
	public function getName() {
		return "openstreetmap";
	}

	// Extend AbstractModule. This title should be normalized when this module will be added officially
	public function getTitle() {
		return /* I18N: Name of a module */ I18N::translate('OpenStreetMap');
	}

	// Extend AbstractModule
	public function getDescription() {
		return /* I18N: Description of the “OSM” module */ I18N::translate('Show the location of places and events using OpenStreetMap (OSM)');
	}

	// Extend AbstractModule
	public function defaultAccessLevel() {
		# Auth::PRIV_PRIVATE actually means public.
		# Auth::PRIV_NONE - no acces to anybody.
		return Auth::PRIV_PRIVATE;
	}

	// Implement ModuleTabInterface
	public function defaultTabOrder() {
		return 81;
	}

	// Implement ModuleTabInterface
	public function getTabContent() {
		global $controller;
		$this->individual_map();
	}

	// Implement ModuleTabInterface
	public function hasTabContent() {
		global $SEARCH_SPIDER;

		return !$SEARCH_SPIDER;
	}

	// Implement ModuleTabInterface
	public function isGrayedOut() {
		return false;
	}

	// Implement ModuleTabInterface
	public function canLoadAjax() {
		return true;
	}

	// Implement ModuleTabInterface
	public function getPreLoadContent() {
	}


	// Extend AbstractModule
	// Here, we define the actions available for the module
	public function modAction($mod_action) {
		switch($mod_action){
		case 'pedigree_map':
			$this->pedigree_map();
		}

	}

	private function pedigree_map() {
		global $controller;
		$controller = new WT_Controller_Pedigree();

		$this->includes($controller);
		$this->drawMap();
	}

	private function individual_map() {
		global $controller;

		$this->includes($controller);


		## This still needs some work. We'll probably want to copy this directly
		##   from googlemaps
		list($events, $personInfo, $geodata) = $this->getEvents();

		// If no places, display message and quit
		if (!$geodata) {
			echo "No map data for this person." . "\n";
			return;
		}

		$this->drawMap($events, $personInfo);

	}
////// This function didn't work, I don't know why, can be deleted. ////////
/*	private function getFullName($myPerson) {
		return $myPerson->getAllNames()[0]['fullNN'];
	}*/	

	private function getEvents() {
		global $controller;

		$events = array(); # Array of indivuals/events
		$geodata = false; # Boolean indicating if we have any geo-tagged data

		$thisPerson = $controller->record;

		### Get all people that we want events for ###
		
		### This person self ###
		$Xref=$thisPerson->getXref();
		$people[$Xref] = array($thisPerson,'relation'=>'', 'fullName'=>$thisPerson->getAllNames()[0]['fullNN']); 
//		array_push($people, $thisPerson); # Self

		### Parents and Sibblings ###
		foreach($thisPerson->getChildFamilies() as $family) {
			# Parents
			foreach($family->getSpouses() as $parent) {
				$Xref = $parent->getXref();
				if ($parent==$family->getHusband()){
					$relation="Father";
				} else {
				  	$relation="Mother";
				}
				$people[$Xref] = array($parent,'relation'=>$relation, 'fullName'=>$parent->getAllNames()[0]['fullNN']);
//				array_push($people, $parent);
			}

			# Siblings
			foreach($family->getChildren() as $sibling) {
				if ( $sibling !== $thisPerson) {
				$Xref = $sibling->getXref();
				if ($sibling->getSex()== 'M'){
					$relation="Brother";
				} else {
				  	$relation="Sister";
				}
				$people[$Xref] = array($sibling,'relation'=>$relation, 'fullName'=>$sibling->getAllNames()[0]['fullNN']);

//					array_push($people, $child);
				}
			}

		}

		### Spouse and own Children ###
		foreach($thisPerson->getSpouseFamilies() as $family) {
			# Spouse
			foreach($family->getSpouses() as $spouse) {
				if ( $spouse !== $thisPerson) {
				$Xref = $spouse->getXref();
				if ($spouse==$family->getHusband()){
					$relation="Husband";
				} else {
				  	$relation="Wife";
				}
				$people[$Xref] = array($spouse,'relation'=>$relation, 'fullName'=>$spouse->getAllNames()[0]['fullNN']);
//					array_push($people, $spouse);
				}
			}

			# Children
			foreach($family->getChildren() as $child) {
				if ( $child !== $thisPerson) {
				$Xref = $child->getXref();
				if ($child->getSex()== 'M'){
					$relation="Son";
				} else {
				  	$relation="Daughter";
				}
				$people[$Xref] = array($child,'relation'=>$relation, 'fullName'=>$child->getAllNames()[0]['fullNN']);
//				array_push($people, $child);
				}
			}

		}

		# Map each person to their facts
		foreach($people as $x) {
			$person = $x[0];
			$xref = $person->getXref();
			$personInfo[$xref]='';
			if($xref!==$thisPerson->getXref()){		
				$personInfo[$xref]='<b>'.I18N::translate($x['relation']).': </b>';	
			}
			$personInfo[$xref].='<a href="/individual.php?pid='.$xref.'&ged='.$_GET['ged'].'">'.$x['fullName'].'</a>';
			$events[$xref] = array();
			foreach($person->getFacts() as $fact) {
				$placefact = new \FactPlace($fact);
				array_push($events[$xref], $placefact);
				if ($placefact->knownLatLon()) $geodata = true;
			}

			// sort facts by date
			usort($events[$xref], array('FactPlace','CompareDate'));
//			}
		}


		return array($events,$personInfo, $geodata);
	}

	private function includes($controller) {
		// Leaflet JS
		echo '<script src="', WT_STATIC_URL, WT_MODULES_DIR, 'openstreetmap/js/leaflet/leaflet.js"></script>';
		// Leaflet CSS
		echo '<link type="text/css" href="', WT_STATIC_URL, WT_MODULES_DIR, 'openstreetmap/css/leaflet.css" rel="stylesheet">';
		echo '<link type="text/css" href="', WT_STATIC_URL, WT_MODULES_DIR, 'openstreetmap/css/osm-module.css" rel="stylesheet">';

		// Leaflet markercluster
		echo '<link type="text/css" href="', WT_STATIC_URL, WT_MODULES_DIR, 'openstreetmap/css/MarkerCluster.Default.css" rel="stylesheet">';
		echo '<link type="text/css" href="', WT_STATIC_URL, WT_MODULES_DIR, 'openstreetmap/css/MarkerCluster.css" rel="stylesheet">';
		echo '<script src="', WT_STATIC_URL, WT_MODULES_DIR, 'openstreetmap/js/leaflet/leaflet.markercluster.js"></script>';

		// Leaflet Fontawesome markers
		echo '<link rel="stylesheet" href="', WT_STATIC_URL, WT_MODULES_DIR, 'openstreetmap/font-awesome-4.3.0/css/font-awesome.min.css">';
		echo '<link rel="stylesheet" href="', WT_STATIC_URL, WT_MODULES_DIR, 'openstreetmap/css/Leaflet.vector-markers.css">';
		echo '<script src="', WT_STATIC_URL, WT_MODULES_DIR, 'openstreetmap/js/leaflet/Leaflet.vector-markers.min.js"></script>';

		require_once $this->directory.'/classes/FactPlace.php';
	}

	private function drawMap($eventsMap, $eventPerson) {
		$attributionOsmString = 'Map data © <a href=\"http://openstreetmap.org\">OpenStreetMap</a> contributors';
		$attributionMapBoxString = 'Map data &copy; <a href=\"http://openstreetmap.org\">OpenStreetMap</a> contributors, <a href=\"http://creativecommons.org/licenses/by-sa/2.0/\">CC-BY-SA</a>, Imagery © <a href=\"http://mapbox.com\">Mapbox</a>';

		echo '<div id=map>';
		echo '</div>';
		echo "<script>
		
                var osm = L.tileLayer('//{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
			attribution: '$attributionOsmString',
			maxZoom: 18
		});
		
		var mapbox = L.tileLayer('//{s}.tiles.mapbox.com/v3/oddityoverseer13.ino7n4nl/{z}/{x}/{y}.png', {
			attribution: '$attributionMapBoxString',
			maxZoom: 18
		});

                var map = L.map('map').fitWorld().setZoom(2);

		osm.addTo(map);
                
                var baseLayers = {
                    'Mapbox': mapbox,
                    'OpenStreetMap': osm
                };

                L.control.layers(baseLayers).addTo(map);
                
                ";
                
                

		// Set up markercluster
		echo "var markers = new L.MarkerClusterGroup();" . "\n";

		$colors = array('#0C448C', '#D60500',  '#009E30', '#D6A000', '#008C65', '#AB0061', '#5B088F', '#D66500');
		$event_options_map = array(
			'BIRT' => array('icon' => 'birthday-cake'),
			'RESI' => array('icon' => 'home'),
			'CENS' => array('icon' => 'users'),
			'GRAD' => array('icon' => 'graduation-cap'),
			'OCCU' => array('icon' => 'briefcase')
			);

		$color_i = 0;
		// Populate the leaflet map with markers
		foreach($eventsMap as $xref => $personEvents) {
			// Set up polyline
			echo "var polyline = L.polyline([], {color: '" . $colors[$color_i] . "'});" . "\n";
			usort($personEvents, array('FactPlace','CompareDate'));

			foreach($personEvents as $event) {
				if ($event->knownLatLon()) {
					$tag = $event->fact->getTag();
					
					$popup = $eventPerson[$xref].$event->shortSummary();
					$options = array_key_exists($tag,$event_options_map) ? $event_options_map[$tag] : array('icon' => 'circle');
					$options['markerColor'] = $colors[$color_i];
					echo "var icon = L.VectorMarkers.icon(".json_encode($options).");";
					echo "var marker = L.marker(".$event->getLatLonJSArray().", {icon: icon});" . "\n";
					echo "marker.bindPopup('".$popup."');" . "\n";

					// Add to markercluster
					echo "markers.addLayer(marker);" . "\n";

					if ($event->fact->getDate()->isOk()) {
						// Append it to the polyline
						echo "polyline.addLatLng(".$event->getLatLonJSArray().");" . "\n";
					}
				}

				// Add polyline to map
				echo "polyline.addTo(map);" . "\n";
			}
			$color_i = ($color_i+1) % count($colors);
		}

		// Add markercluster to map
		echo "var l = map.addLayer(markers);" . "\n";

		// Zoom to bounds of polyline
		echo "map.fitBounds(markers.getBounds());" . "\n";

		echo "map.invalidateSize();" . "\n";

		echo '</script>';
	}
}

return new OpenStreetMapModule();
