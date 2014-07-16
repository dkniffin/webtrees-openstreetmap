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
		$facts = $controller->record->getFacts();
		$places = array();
		foreach($facts as $fact) {
			array_push($places, new FactPlace($fact));
		}

		$this->drawMap($places);

	}

	private function includes($controller) {
		// Leaflet JS
		echo '<script src="', WT_STATIC_URL, WT_MODULES_DIR, 'openstreetmap/js/leaflet/leaflet.js"></script>';
		// Leaflet CSS
		echo '<link type="text/css" href="', WT_STATIC_URL, WT_MODULES_DIR, 'openstreetmap/css/leaflet.css" rel="stylesheet">';
		echo '<link type="text/css" href="', WT_STATIC_URL, WT_MODULES_DIR, 'openstreetmap/css/osm-module.css" rel="stylesheet">';

		require_once WT_MODULES_DIR.$this->getName().'/classes/FactPlace.php';
	}

	private function drawMap($places) {
		$attributionString = 'Map data &copy; <a href=\"http://openstreetmap.org\">OpenStreetMap</a> contributors, <a href=\"http://creativecommons.org/license      s/by-sa/2.0/\">CC-BY-SA</a>, Imagery © <a href=\"http://mapbox.com\">Mapbox</a>';
		echo '<div id=map>';
		echo '</div>';
		echo "<script>
		var map = L.map('map').setView([25,0], 3);
		L.tileLayer('http://{s}.tiles.mapbox.com/v3/oddityoverseer13.ino7n4nl/{z}/{x}/{y}.png', {
			attribution: '$attributionString',
			maxZoom: 18
		}).addTo(map);
		";

		// Populate the leaflet map with markers
		foreach($places as $place) {
			if ($place->knownLatLon()) {
				echo "var marker = L.marker(".$place->getLatLonJSArray().").addTo(map);" . "\n";
				echo "marker.bindPopup('".$place->shortSummary()."');" . "\n";
			}
		}

		echo '</script>';
	}
}
