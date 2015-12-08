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

use Fisharebest\Webtrees\Database;
use Fisharebest\Webtrees\Fact;

class FactPlace {
	public $fact;
	private $data;
	private $lat, $lon;
	private $fqpn; // Fully Qualified Place Name

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

	// Populate this objects lat/lon values, if possible
	private function getLatLon() {
		$fact = $this->fact;
		if (!$fact->getPlace()->isEmpty()) {
			// First look to see if the lat/lon is hardcoded in the gedcom
			$gedcom_lat = preg_match("/\d LATI (.*)/", $fact->getGedcom(), $match1);
			$gedcom_lon = preg_match("/\d LONG (.*)/", $fact->getGedcom(), $match1);
			if ($gedcom_lat && $gedcom_lon) {
				// If it's hardcoded, we're done.
				$this->lat = $gedcom_lat;
				$this->lon = $gedcom_lon;
				return;
			}

			// Next, get the lat/lon from the database
			$data = Database::prepare("
				SELECT
			      CONCAT_WS(', ', t1.pl_place, t2.pl_place, t3.pl_place, t4.pl_place, t5.pl_place, t6.pl_place) as fqpn,
					COALESCE(NULLIF(t1.pl_long,'E0'),
						NULLIF(t2.pl_long,'E0'),
						NULLIF(t3.pl_long,'E0'),
						NULLIF(t4.pl_long,'E0'),
						NULLIF(t5.pl_long,'E0'),
						NULLIF(t6.pl_long,'E0')) as pl_long,
					COALESCE(NULLIF(t1.pl_lati,'N0'),
						NULLIF(t2.pl_lati,'N0'),
						NULLIF(t3.pl_lati,'N0'),
						NULLIF(t4.pl_lati,'N0'),
						NULLIF(t5.pl_lati,'N0'),
						NULLIF(t6.pl_lati,'N0')) as pl_lati
				FROM `##placelocation` as t1
				LEFT JOIN `##placelocation` as t2 on t1.pl_parent_id = t2.pl_id
				LEFT JOIN `##placelocation` as t3 on t2.pl_parent_id = t3.pl_id
				LEFT JOIN `##placelocation` as t4 on t3.pl_parent_id = t4.pl_id
				LEFT JOIN `##placelocation` as t5 on t4.pl_parent_id = t5.pl_id
				LEFT JOIN `##placelocation` as t6 on t5.pl_parent_id = t6.pl_id
				HAVING fqpn=?;
			   ")
					->execute(array($fact->getPlace()->getGedcomName()))
					->fetchOneRow();
			if ($data) {
				if ($data->pl_long && $data->pl_lati) {
					$this->lat = $data->pl_lati;
					$this->lon = $data->pl_long;
				}
				if ($data->fqpn) {
					$this->fqpn = $data->fqpn;
				}
			}

			// Next, query nominatim
			// NOTE: This is too slow. We don't want to do this at page-load.
			#$res = $this->queryNominatim($this->fact->getPlace()->getGedcomName());
			#if ($res) {
				#$this->lat = $res[0]->lat;
				#$this->lon = $res[0]->lon;
			#}

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

	public static function CompareDate(FactPlace $a, FactPlace $b) {
		return Fact::CompareDate($a->fact, $b->fact);
	}

}
