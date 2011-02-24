<?php
/*
*   GkMappable
*   ==========
*   Used to calculate distances between two points, specifically, between two pairs of longitude and latitude. GkMappable
*   is used to help generate the complex MySQL queries that are needed when searching for records by distance.
*
*/
class GkMappable {
    
    
    // Holds class settings
    var $settings = array();
    
    // Holds the built up query
    var $query = '';
    
    // Measurements
    public $pi_div_rad = 0.0174;
	public $kms_per_mile = 1.609;
	public $earth_radius_in_miles = 3963.19;	
	public $earth_radius_in_kms = 6376.77271;
	public $miles_per_latitude_degree = 69.1;
	public $kms_per_latitude_degree = 111.1819;
	public $latitude_degrees = 57.35441;
	
	
	/*
	*   setup($settings)
	*   ================
	*   Called during the initial setup of the object. Contains sensible defaults, but
	*   allows overrides by passing in an array of settings. Possible settings include:
	*
	*        $settings = array(
    *   		'lat_column_name' => 'lat',
    *   		'lng_column_name' => 'lng',
    *   		'units' => 'kms',
    *   		'calculate' => 'flat',
    *   		'table_name' => 'name_of_table',
    *           'single_name' => 'name_of_AS_item'
    *   	);
	*
	*/
	function setup( $settings=array() ) {
    	// Sensible defaults
    	$default = array(
			'lat_column_name' => 'lat',
			'lng_column_name' => 'lng',
			'units' => 'kms',
			'calculate' => 'flat',
			'table_name' => 'map_table',
			'single_name' => 'record',
			'limit' => 50,
			'order' => '`distance` ASC',
			'select' => array('*')
		);
		// Allow settings to be overridden
		foreach($settings as $key=>$value) {
		    $default[$key] = $value;
		}
		// Set the settings
		$this->settings = $default;
	}
	
	
	/******************************* DATABASE RELATED METHODS ************************************/
	
	// Attaches to a model reference
	function findWithin($lat=null, $lng=null, $distance=10, $options=array()) {
		return $this->findWithinQuery($lat,$lng,$distance,$options);
	}
	
	function findWithinQuery($lat=null, $lng=null, $distance=10, $options=array()) {
		
		if($lat==null || $lng==null) {
			return array();
		}
		
		if( $this->settings['calculate'] =='flat' ) {
			$distance_sql = $this->flat_distance_sql($lat,$lng);
		}else{
			$distance_sql = $this->sphere_distance_sql($lat,$lng);
		}					
		$conditions = $this->prepare_for_find($distance,$lat,$lng);
		
		// Loop through the fields that we are selecting
		$final_query = "SELECT ";
		foreach($this->settings['select'] as $field) {
		    $final_query .= " $field, ";
		}
		
		// Add our heavy distance SQL code
		$final_query .= "$distance_sql AS `distance`";
		
		// Add the FROM and AS sections
		$final_query .= " FROM `{$this->settings['table_name']}` \n";
		$final_query .= " AS `{$this->settings['single_name']}` \n WHERE";
		
		// Add our boundry conditions
		$final_query .= $conditions;
		
		// Add the order conditions
		$final_query .= " ORDER BY {$this->settings['order']} ";
		
		// Add a limit if required
		if($this->settings['limit']) {
		    $final_query .= "LIMIT {$this->settings['limit']}";
		}
		
		return $final_query;
	}
	
	// Returns the distance SQL using the spherical world formula (Haversine).
	function sphere_distance_sql($lat,$lng) {
		$lat = $this->deg2rad($lat);
		$lng = $this->deg2rad($lng);
		$multiplier = $this->units_sphere_multiplier($Model);
		$qualified_lat_column_name = "`{$this->settings['single_name']}`.`{$this->settings['lat_column_name']}`";
		$qualified_lng_column_name = "`{$this->settings['single_name']}`.`{$this->settings['lng_column_name']}`";
		return "(ACOS(least(1,COS($lat)*COS($lng)*COS(RADIANS($qualified_lat_column_name))*COS(RADIANS($qualified_lng_column_name))+
		COS($lat)*SIN($lng)*COS(RADIANS($qualified_lat_column_name))*SIN(RADIANS($qualified_lng_column_name))+
		SIN($lat)*SIN(RADIANS($qualified_lat_column_name))))*$multiplier)";
	}
	
	// Returns the distance SQL using the flat world forumla.
	function flat_distance_sql($origin_lat,$origin_lng) {
		$qualified_lat_column_name = "`{$this->settings['single_name']}`.`{$this->settings['lat_column_name']}`";
		$qualified_lng_column_name = "`{$this->settings['single_name']}`.`{$this->settings['lng_column_name']}`";
		$lat_degree_units = $this->units_per_latitude_degree();
		$lng_degree_units = $this->units_per_longitude_degree($origin_lat);
		return "SQRT(POW($lat_degree_units*($origin_lat-$qualified_lat_column_name),2)+
		          POW($lng_degree_units*($origin_lng-$qualified_lng_column_name),2))";
	}
	
	// Undocumented
	function prepare_for_find($distance,$lat,$lng) {
		$qualified_lat_column_name = "`{$this->settings['single_name']}`.`{$this->settings['lat_column_name']}`";
		$qualified_lng_column_name = "`{$this->settings['single_name']}`.`{$this->settings['lng_column_name']}`";
		$bounds = $this->formulate_bounds_from_distance($distance,$lat,$lng);
		$bounds_sql = "			
			$qualified_lat_column_name >= {$bounds['sw']['lat']} AND
			$qualified_lat_column_name <= {$bounds['ne']['lat']} AND
			$qualified_lng_column_name >= {$bounds['sw']['lng']} AND
			$qualified_lng_column_name <= {$bounds['ne']['lng']}";
		return $bounds_sql;
	}
	
    
    /******************************* NON-DATABASE RELATED METHODS ********************************/
    
    // Returns a heading in degrees (0=north,90=east,180=south,etc...)
	// From the first point to the second point
	function headingBetween($from_lat,$from_lng,$to_lat,$to_lng) {
		$d_lng = $this->deg2rad($to_lng-$from_lng);
		$from_lat_rad = $this->deg2rad($from_lat);
		$to_lat_rad = $this->deg2rad($to_lat);		
		$y = sin($d_lng) * cos($to_lat_rad);
		$x = cos($from_lat_rad) * sin($to_lat_rad) - sin($from_lat_rad) * cos($to_lat_rad) * cos($d_lng);
		return ($this->to_heading( atan2($y,$x) ));
	}
	
	// Returns an array of a lat/lng endpoint, given a heading (in degrees) and distance
	function endpoint($start_lat,$start_lng,$heading,$distance) {
		if($this->settings['units']=='kms') {
			$radius = $this->earth_radius_in_kms;
		}else{
			$radius = $this->earth_radius_in_miles;
		}
		$lat = $this->deg2rad($start_lat);
		$lng = $this->deg2rad($start_lng);
		$heading = deg2rad($heading);
		$end_lat = asin( sin($lat) * cos($distance/$radius) ) + cos($lat) * sin($distance/$radius) * cos($heading);
		$end_lng = $lng + atan2( sin($heading) * sin($distance/$radius) * cos($lat), cos($distance/$radius) - sin($lat) * sin($end_lat) );
		return array( 'lat' => $this->rad2deg($end_lat), 'lng' => $this->rad2deg($end_lng) );
	}
	
	// Allows us to calculate miles or kilometers
	function units_sphere_multiplier() {
		if($this->settings['units']=='kms') {
			return $this->earth_radius_in_kms;
		}else{
			return $this->earth_radius_in_miles;
		}
	}
	
	// Returns the number of units per latitude degree.
	function units_per_latitude_degree() {
		if($this->settings['units']=='kms') {
			return $this->kms_per_latitude_degree;
		}else{
			return $this->miles_per_latitude_degree;
		}
	}
	
	// Returns the number units per longitude degree.
	function units_per_longitude_degree($lat) {
		$miles_per_longitude_degree = $this->latitude_degrees * abs(cos($lat * $this->pi_div_rad));
		if($this->settings['units']=='kms') {
			return $miles_per_longitude_degree * $this->kms_per_mile;
		}else{
			return $miles_per_longitude_degree;
		}
	}
	
	// Undocumented
	function from_point_and_radius($radius,$lat,$lng) {
		$p0 = $this->endpoint($lat,$lng,0,$radius);
		$p90 = $this->endpoint($lat,$lng,90,$radius);
		$p180 = $this->endpoint($lat,$lng,180,$radius);
		$p270 = $this->endpoint($lat,$lng,270,$radius);
		return array('sw' => array('lat' => $p180['lat'], 'lng' => $p270['lng']), 'ne' => array('lat' => $p0['lat'], 'lng' => $p90['lng']));
	}

	// Undocumented
	function formulate_bounds_from_distance($distance,$lat,$lng) {
		return $this->from_point_and_radius($distance,$lat,$lng);
	}

	// Undocumented
	function deg2rad($deg) {
		return ($deg / 180.0 * pi());
	}
	
	// Undocumented
	function rad2deg($rad) {
		return ($rad * 180.0 / pi());
	}

	// Undocumented
	function to_heading($rad) {
		return ($this->rad2deg($rad)+360)%360;
	}
    
}
?>