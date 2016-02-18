var map = L.map('map').setView([25,0], 3);
L.tileLayer('//{s}.tiles.mapbox.com/v3/oddityoverseer13.ino7n4nl/{z}/{x}/{y}.png', {
	attribution: 'Map data &copy; <a href=\"http://openstreetmap.org\">OpenStreetMap</a> contributors, <a href=\"http://creativecommons.org/licenses/by-sa/2.0/\">CC-BY-SA</a>, Imagery © <a href=\"http://mapbox.com\">Mapbox</a>',
   maxZoom: 18
}).addTo(map);

