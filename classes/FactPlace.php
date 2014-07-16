<?php
// Gedcom Place functionality.
//
// webtrees: Web based Family History software
// Copyright (C) 2014 webtrees development team.
//
// This program is free software; you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation; either version 2 of the License, or
// (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with this program; if not, write to the Free Software
// Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA

if (!defined('WT_WEBTREES')) {
	header('HTTP/1.0 403 Forbidden');
	exit;
}

class FactPlace {
	private $fact;
	private $data;
	private $lat, $lon;

	public function __construct($fact) {
		$this->fact = $fact;

		//$this->buildData($fact->getPlace()->getGedcomName());
		$this->getLatLon();
	}
	public function knownLatLon() {
		//return ($this->data && $this->data->pl_lati && $this->data->pl_long);
		return ($this->lat && $this->lon);
	}
	public function getLat($format) {
		switch($format) {
		case 'signed':
			return str_replace('N','',str_replace('S','-',$this->lat));
		default:
			return $this->lat;
		}
	}

	public function getLon($format) {
		switch($format) {
		case 'signed':
			return str_replace('W','-',str_replace('E','',$this->lon));
		default:
			return $this->lon;
		}
	}


	// Return a one line summary from the fact
	public function shortSummary() {
		return $this->fact->summary();
	}

	public function getPlaceName() {
		return $this->fact->getPlace()->getPlaceName();
	}

	public function getLatLonJSArray() {
		return '['.$this->getLat('signed').','.$this->getLon('signed').']';
	}

	private function rem_prefix_postfix_from_placename($prefix_list, $postfix_list, $place, $placelist) {
      if ($prefix_list && $postfix_list) {
         foreach (explode (";", $prefix_list) as $prefix) {
            foreach (explode (";", $postfix_list) as $postfix) {
               if ($prefix && $postfix && substr($place, 0, strlen($prefix)+1)==$prefix.' ' && substr($place, -strlen($postfix)-1)==' '.$postfix) {
                  $placelist[] = substr($place, strlen($prefix)+1, strlen($place)-strlen($prefix)-strlen($postfix)-2);
               }
            }
         }
      }
      return $placelist;
   }

	private function create_possible_place_names($placename, $level) {
      $retlist = array();
/*
      if ($level<=9) {
         $retlist = $this->rem_prefix_postfix_from_placename($this->getSetting('GM_PREFIX_' . $level), $this->getSetting('GM_POSTFIX_' . $level), $placename, $retlist); // Remove both
         $retlist = $this->rem_prefix_from_placename($this->getSetting('GM_PREFIX_' . $level), $placename, $retlist); // Remove prefix
         $retlist = $this->rem_postfix_from_placename($this->getSetting('GM_POSTFIX_' . $level), $placename, $retlist); // Remove suffix
      }
*/
      $retlist[]=$placename; // Exact

      return $retlist;
   }


	private function buildData($place) {
		$parent = explode (',', $place);
      $parent = array_reverse($parent);
      $place_id = 0;
      for ($i=0; $i<count($parent); $i++) {
         $parent[$i] = trim($parent[$i]);
         if (empty($parent[$i])) $parent[$i]='unknown';// GoogleMap module uses "unknown" while GEDCOM uses , ,
         $placelist = $this->create_possible_place_names($parent[$i], $i+1);
         foreach ($placelist as $placename) {
            $pl_id=
               WT_DB::prepare("SELECT pl_id FROM `##placelocation` WHERE pl_level=? AND pl_parent_id=? AND pl_place LIKE ? ORDER BY pl_place")
               ->execute(array($i, $place_id, $placename))
               ->fetchOne();
            if (!empty($pl_id)) break;
         }
         if (empty($pl_id)) break;
         $place_id = $pl_id;
      }

      $this->data = WT_DB::prepare("SELECT pl_lati, pl_long FROM `##placelocation` WHERE pl_id=? ORDER BY pl_place")
         ->execute(array($place_id))
         ->fetchOneRow();
				
	}

	// Populate this objects lat/lon values, if possible
	private function getLatLon() {
		$fact = $this->fact;
		if (!$fact->getPlace()->isEmpty()) {
			// Options in order of priority
			//  1 - Lat/Lon are explicitly set in gedcom
			//  2 - Placename in gedcom has lat/lon in placelocation table
			//  3 - Nominatim has a lat/lon for place
			//  4 - Placename's parents have lat/lon in placelocation table
			  
			// First look to see if the lat/lon is hardcoded in the gedcom
			$gedcom_lat = preg_match("/\d LATI (.*)/", $fact->getGedcom(), $match1);
			$gedcom_lon = preg_match("/\d LONG (.*)/", $fact->getGedcom(), $match1);
			if ($gedcom_lat && $gedcom_lon) { 
				// If it's hardcoded, we're done.
				$this->lat = $gedcom_lat; 
				$this->lon = $gedcom_lon; 
				return;
			} 
			// Next, check if the place has lat/lon in the database
			
			// Next, query nominatim
			$res = $this->queryNominatim($this->fact->getPlace()->getGedcomName());
			if ($res) {
				$this->lat = $res[0]->lat;
				$this->lon = $res[0]->lon;
			}

		}
	}

	private function queryNominatim($query) {
		$queryString = urlencode($query);

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_URL, "http://nominatim.openstreetmap.org/search/?format=json&limit=1&q=$queryString");
		$response = curl_exec($ch);
		curl_close($ch);

		if ($response === false) {
			throw new Exception('Nominatim query failed');
		}
		return json_decode($response);
	}
}
