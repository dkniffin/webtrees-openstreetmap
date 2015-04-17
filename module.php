<?php

error_reporting(E_ALL);
ini_set('display_errors', 'on');

if (!defined('WT_WEBTREES')) {
	header('HTTP/1.0 403 Forbidden');
	exit;
}

class openstreetmap_WT_Module extends WT_Module implements WT_Module_Tab {

	// Extend WT_Module. This title should be normalized when this module will be added officially
	public function getTitle() {
		return /* I18N: Name of a module */ WT_I18N::translate('OpenStreetMap');
	}

	// Extend WT_Module
	public function getDescription() {
		return /* I18N: Description of the “OSM” module */ WT_I18N::translate('Show the location of places and events using OpenStreetMap (OSM)');
	}

	// Implement WT_Module_Tab
	public function defaultTabOrder() {
		return 81;
	}


	// Implement WT_Module_Tab
	public function getTabContent() {
		global $controller;
		$this->individual_map();

	}

	// Implement WT_Module_Tab
	public function hasTabContent() {
		global $SEARCH_SPIDER;

		return !$SEARCH_SPIDER;
	}
	// Implement WT_Module_Tab
	public function isGrayedOut() {
		return false;
	}
	// Implement WT_Module_Tab
	public function canLoadAjax() {
		return true;
	}

	// Implement WT_Module_Tab
	public function getPreLoadContent() {
	}



	// Extend WT_Module
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
		list($events, $geodata) = $this->getEvents();

		// If no places, display message and quit
		if (!$geodata) {
			echo "No map data for this person." . "\n";
			return;
		}

		$this->drawMap($events);

	}

	private function getEvents() {
		global $controller;

		$events = array(); # Array of indivuals/events
		$geodata = false; # Boolean indicating if we have any geo-tagged data

		$thisPerson = $controller->record;

		### Get all people that we want events for ###
		$people = array();
		array_push($people, $thisPerson); # Self
		foreach($thisPerson->getChildFamilies() as $family) {
			# Parents
			foreach($family->getSpouses() as $parent) {
				array_push($people, $parent);
			}

			# Siblings
			foreach($family->getChildren() as $child) {
				if ( ! $child === $thisPerson) {
					array_push($people, $child);
				}
			}

		}
		foreach($thisPerson->getSpouseFamilies() as $family) {
			# Spouse
			foreach($family->getSpouses() as $spouse) {
				if ( ! $spouse === $thisPerson) {
					array_push($people, $spouse);
				}
			}

			# Children
			foreach($family->getChildren() as $child) {
				array_push($people, $child);
			}

		}

		# Map each person to their facts
		foreach($people as $person) {
			$xref = $person->getXref();
			$events[$xref] = array();
			foreach($person->getFacts() as $fact) {
				$placefact = new FactPlace($fact);
				array_push($events[$xref], $placefact);
				if ($placefact->knownLatLon()) $geodata = true;
			}

			// sort facts by date
			usort($events[$xref], array('FactPlace','CompareDate'));
		}


		return array($events,$geodata);
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
		echo '<link rel="stylesheet" href="', WT_STATIC_URL, WT_MODULES_DIR, 'openstreetmap/css/font-awesome.min.css">';
		echo '<link rel="stylesheet" href="', WT_STATIC_URL, WT_MODULES_DIR, 'openstreetmap/css/leaflet.awesome-markers.css">';
		echo '<script src="', WT_STATIC_URL, WT_MODULES_DIR, 'openstreetmap/js/leaflet/leaflet.awesome-markers.min.js"></script>';

		require_once WT_MODULES_DIR.$this->getName().'/classes/FactPlace.php';
	}

	private function drawMap($eventsMap) {
		$attributionString = 'Map data &copy; <a href=\"http://openstreetmap.org\">OpenStreetMap</a> contributors, <a href=\"http://creativecommons.org/license      s/by-sa/2.0/\">CC-BY-SA</a>, Imagery © <a href=\"http://mapbox.com\">Mapbox</a>';
		echo '<div id=map>';
		echo '</div>';
		echo "<script>
		var map = L.map('map').fitWorld().setZoom(2);
		L.tileLayer('http://{s}.tiles.mapbox.com/v3/oddityoverseer13.ino7n4nl/{z}/{x}/{y}.png', {
			attribution: '$attributionString',
			maxZoom: 18
		}).addTo(map);
		";

		// Set up polyline
		echo "var polyline = L.polyline([]);" . "\n";

		// Set up markercluster
		echo "var markers = new L.MarkerClusterGroup();" . "\n";

		$colors = array('blue', 'red', 'purple', 'green', 'orange', 'cadetblue', 'darkred', 'darkpuple', 'darkgreen');

		$color_i = 0;
		// Populate the leaflet map with markers
		foreach($eventsMap as $xref => $personEvents) {
			foreach($personEvents as $event) {
				if ($event->knownLatLon()) {
					echo "var icon = L.AwesomeMarkers.icon({
						icon: 'coffee',
						markerColor: '" . $colors[$color_i] . "'
					});";
					echo "var marker = L.marker(".$event->getLatLonJSArray().", {icon: icon});" . "\n";
					echo "marker.bindPopup('".$event->shortSummary()."');" . "\n";

					// Add to markercluster
					echo "markers.addLayer(marker);" . "\n";

					if ($event->fact->getDate()->isOk()) {
						// Append it to the polyline
						echo "polyline.addLatLng(".$event->getLatLonJSArray().");" . "\n";
					}
				}
			}
			$color_i++;
		}

		// Add markercluster to map
		echo "var l = map.addLayer(markers);" . "\n";

		// Add polyline to map
		echo "polyline.addTo(map);" . "\n";

		// Zoom to bounds of polyline
		echo "map.fitBounds(markers.getBounds());" . "\n";

		echo "map.invalidateSize();" . "\n";

		echo '</script>';
	}
}
