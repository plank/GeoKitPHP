GeoKITPHP
=========

A handy PHP class that helps generate the SQL queries required when doing distance based selects. It is made up of two classes, mappable and locateable. Mappable is used to generate scary MySQL queries, while locatable is a nice wrapper that makes API calls to Google, Yahoo, and other location based services.

Mappable
--------

First some examples:

    include_once(GeoKitPHP/gk_mappable.php');
    
    $mappable = new GkMappable();
    $gk_settings = array(
		'lng_column_name' => 'lon',
		'table_name' => 'GEOCODES'
    );
    $mappable->setup($gk_settings);
    
    // Mont-Royal Area (Montreal)
    $lon = -73.5802;
    $lat = 45.5267;
    
    // findWithin($lat, $lon, $distance)
    $crazy_sql = $mappable->findWithin($lat, $lon, 200);