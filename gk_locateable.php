<?php
// Using xml2array to make life a little easier. If anyone would like to change this please send a pull
// request my way.
include_once('xml2array.php');


/*
*   GkLocatable
*   ===========
*   This is a class that tries to wrap location based API calls into an easy to use single point of entry.
*
*/
class GkLocateable {
    
    
    // Used to store the settings
    var $settings = array();
    
    
    // Sets the settings
    function setup( $settings = array()) {
        $default = array(
            'timeout' => 1000,
            'yahoo_api_key' => '',
            'google_api_key' => '',
            'order' => array(
                'google','yahoo','geocoder_ca', // 'geonames' need to implement
            )
        );
        
        // Allow settings to be overridden
        foreach($settings as $key=>$value) {
            $default[$key] = $value;
        }
        // Set the settings
        $this->settings = $default;
    }
    
    
    // A wrapper function that simply fires off a get request
    function geocode($location) {
        if(empty($location)) {
            return false;
        }
        $response = $this->get_request($location);
        return $response;
    }
    
    
    // Sends a CURL request to one of the location based services. If the response is invalid it will
    // try one of the other services in the order defined within the settings.
    function get_request($request) {
        foreach($this->settings['order'] as $service) {
            $request = call_user_func(array($this,$service."_request"),urlencode($request));
            $ch = curl_init();
            $options = array(
                CURLOPT_URL => $request,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => $this->settings['timeout']
            );
            curl_setopt_array($ch,$options);
            $response = curl_exec($ch);
            curl_close($ch);
            if($response) {
                $data = $this->parsedResponse($response,$service);
                if($data['status']=='fail' || empty($data)) {
                    continue;
                }else{
                    return $data;
                }
            }else{
                continue;
            }
        }
        return false;
    }
    
    // Parses our requests into a standard response
    function parsedResponse($response,$service) {
        $data = array(
            'lat' 		=> null,
            'lng' 		=> null,
            'country' 	=> '',
            'city' 		=> '',
            'region' 	=> '',
            'type'		=> $service,
            'status'	=> 'fail'
        );
    if($service=='google') {
        $data = $this->parse_google_response($response);
    }
    elseif($service=='yahoo') {
        $data = $this->parse_yahoo_response($response);
    }
    elseif($service=='geonames') {
        $data = $this->parse_geonames_response($response);
    }
    elseif($service=='geocoder_us') {
        $data = $this->parse_geocoder_us_response($response);
    }
    elseif($service=='geocoder_ca') {
        $data = $this->parse_geocoder_ca_response($response);
    }
    return $data;
    }
    
    
    // Gets a rough lat and lng from an ip address
    // Needs a rewrite very badly
	function geocode_ip($ip) {
        $ignore_ips = array(
            '0.0.0.0','10.0.0.0','14.0.0.0',
            '127.0.0.0','169.254.0.0','172.16.0.0',
            '192.0.2.0','192.168.0.0','198.18.0.0',
            '224.0.0.0','240.0.0.0'
        );
        if(in_array($ip,$ignore_ips)) {
            return array('status'=>'fail');
        }
        $url = "http://api.hostip.info/get_html.php?ip=";
        $url .= $ip;
        $url .= "&position=true";
        $ch = curl_init();
        $options = array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => $this->settings['timeout']
        );
        curl_setopt_array($ch,$options);
        $response = curl_exec($ch);
        curl_close($ch);
        if($response) {
            $array_data = explode("\n",$response);
            foreach($array_data as $line) {
                if(!empty($line)) {
                    $data = explode(":",$line);
                    $data[1] = trim($data[1]);
                    if(!empty($data[0]) && !empty($data[1])) {
                        if($data[0] == 'Country') {							
                            $results['country'] = ucwords(mb_strtolower($data[1]));
                        }
                        elseif($data[0] == 'City') {
                            $region = explode(',',$data[1]);
                            if(count($region) > 0) {
                                if(!empty($region[1])) {
                                    $results['region'] = ucwords(mb_strtolower($region[1]));
                                }
                                if(!empty($region[0])) {
                                    $results['city'] = ucwords(mb_strtolower($region[0]));
                                }
                            }else{
                                $results['city'] = ucwords(mb_strtolower($data[1]));
                            }
                        }
                        elseif($data[0] == 'Latitude') {
                            if(!empty($data[1])) {
                                $results['lat'] = $data[1];
                            }
                        }
                        elseif($data[0] == 'Longitude') {
                            if(!empty($data[1])) {
                                $results['lng'] = $data[1];
                            }
                        }
                        elseif($data[0] == 'IP') {
                            if(!empty($data[1])) {
                                $results['ip'] = $data[1];
                            }
                        }
                    }
                }
                continue;
            }
            if(empty($results['lat']) && empty($results['lng'])) {
                return false;
            }else{
                $results['status'] = 'success';
                return $results;
            }
        }else{
            return false;
        }
    }
    
    
    // All the different requests
    function yahoo_request($location) {
        // TODO: Update to new API:
        //          http://where.yahooapis.com/geocode?q=1600+Pennsylvania+Avenue,+Washington,+DC&appid=
        $url = 'http://local.yahooapis.com/MapsService/V1/geocode?appid=';
        $url .= $this->settings['yahoo_api_key'];
        $url .= '&location='.$location;
        return $url;
    }
    
    function google_request($location) {
        $url = 'http://maps.google.com/maps/geo?q=';
        $url .= $location;
        $url .= "&output=xml&key=".$this->settings['google_api_key'];
        $url .= "&oe=utf-8";
        return $url;
    }
    function geonames_request($location) {
        $url = "http://ws.geonames.org/postalCodeSearch?placename=";
        $url .= $location;
        $url .= "&&maxRows=3";
        return $url;
    }
    function geocoder_us_request($location) {
        // Untested, probably requires rewrite
        $url = "http://geocoder.us/service/csv/geocode?address=";
        $url .= $location;
        return $url;
    }
    function geocoder_ca_request($location) {
        $url = "http://geocoder.ca?locate=";
        $url .= $location;
        $url .= '&geoit=xml&standard=1';
        return $url;
    }
    
    
    // Parse a Google response into something stamdarduseful
    function parse_google_response($response) {
        $result = array();
        $response_array = xml2array($response);
        if(empty($response_array['kml']['Response']['Status']) || $response_array['kml']['Response']['Status']['code'] != 200) {
            return false;
        }
        
        // Get the lat and lng
        $short_array = $response_array['kml']['Response']['Placemark'];
        if(!empty($short_array['Point']['coordinates'])) {
            $latlng = explode(',',$short_array['Point']['coordinates']);
            $result['lat'] = $latlng[0];
            $result['lng'] = $latlng[1];
        }else{
             $result['lat'] = false;
             $result['lng'] = false;
        }
        
        // Get the city (needs refactor for readability)
        if(!empty($short_array['AddressDetails']['Country']['AdministrativeArea']['Locality']['LocalityName'])
        || !empty($short_array['AddressDetails']['Country']['AdministrativeArea']['SubAdministrativeArea']['Locality']['LocalityName'])) {
            if(isset($short_array['AddressDetails']['Country']['AdministrativeArea']['SubAdministrativeArea']['Locality']['LocalityName'])) {
                $result['city'] = $short_array['AddressDetails']['Country']['AdministrativeArea']['SubAdministrativeArea']['Locality']['LocalityName'];
            }else{
                $result['city'] = $short_array['AddressDetails']['Country']['AdministrativeArea']['Locality']['LocalityName'];
            }
            if(!empty($result['city'])) {
                $result['city'] = ucwords(mb_strtolower($result['city']));
            }
        }else{
            $result['city']	= '';
        }
        
        // Get the region name
        if(!empty($short_array['AddressDetails']['Country']['AdministrativeArea']['AdministrativeAreaName'])) {
            $result['region'] = $short_array['AddressDetails']['Country']['AdministrativeArea']['AdministrativeAreaName'];
        }else{
            $result['region'] = '';
        }
        
        // Get the country name
        if(!empty($short_array['AddressDetails']['Country']['CountryNameCode'])) {
            $result['country'] = $short_array['AddressDetails']['Country']['CountryNameCode'];
        }else{
            $result['country'] = '';
        }
        
        // Set the status
        if(empty($result['lat']) || empty($result['lng'])) {
            $result['status'] = 'fail';
        }else{
            $result['status'] = 'success';
        }
        $result['service'] = 'google';
        return $result;
    }
    
    
    // Deals with a yahoo response
    function parse_yahoo_response($response) {
        $result = array();
        $array_data = xml2array($response);
        $result['type'] = 'yahoo';
        if(empty($array_data) || isset($array_data['Error'])) {
            return array('status'=>'fail');
        }else{
            if(!empty($array_data['ResultSet']['Result']['Latitude']) && !empty($array_data['ResultSet']['Result']['Longitude'])) {
                $result['lat'] = $array_data['ResultSet']['Result']['Latitude'];
                $result['lng'] = $array_data['ResultSet']['Result']['Longitude'];
            }else{
                $result['lat'] = null;
                $result['lng'] = null;
            }
            if(!empty($array_data['ResultSet']['Result']['City'])) {
                $result['city'] = $array_data['ResultSet']['Result']['City'];
            }
            if(!empty($array_data['ResultSet']['Result']['State'])) {
                $result['region'] = $array_data['ResultSet']['Result']['State'];
            }
            if(!empty($array_data['ResultSet']['Result']['Country'])) {
                $result['country'] = $array_data['ResultSet']['Result']['Country'];
            }
            if(empty($result['lat']) || empty($result['lng'])) {
                $result['status'] = 'fail';
            }else{
                $result['status'] = 'success';
            }
        }
        return $result;
    }
    
    // Deals with the geonames response - not working properly at the moment
    function parse_geonames_response($response) {
		$array_data = xml2array($response);
		$result['type'] = 'geonames';
		if(empty($array_data['Geonames']['Code'][0])) {
			return array('status'=>false);
		}else{
			if(!empty($array_data['Geonames']['Code'][0]['lat']) && !empty($array_data['Geonames']['Code'][0]['lng'])) {
				$result['lat'] = $array_data['Geonames']['Code'][0]['lat'];
				$result['lng'] = $array_data['Geonames']['Code'][0]['lng'];
			}
			if(!empty($array_data['Geonames']['Code'][0]['name'])) {
				$result['city'] = $array_data['Geonames']['Code'][0]['name'];
			}
			if(!empty($array_data['Geonames']['Code'][0]['adminName1'])) {
				$result['region'] = $array_data['Geonames']['Code'][0]['adminName1'];
			}
			if(!empty($array_data['Geonames']['Code'][0]['countryCode'])) {
				$result['country'] = $array_data['Geonames']['Code'][0]['countryCode'];
			}
			$result['status'] = 'success';
		}
		return $result;
	}
	
	// Handles the geocoder_ca response
	function parse_geocoder_ca_response($response) {
		$array_data = xml2array($response);
		$result['type'] = 'geocoder_ca';
		if(empty($array_data) || isset($array_data['Geodata']['Error'])) {
			return array('status'=>'fail');
		}else{
			if(!empty($array_data['Geodata']['latt']) && !empty($array_data['Geodata']['longt'])) {
				$result['lat'] = $array_data['geodata']['latt'];
				$result['lng'] = $array_data['geodata']['longt'];
			}else{
				$result['lat'] = null;
				$result['lng'] = null;
			}
			if(!empty($array_data['geodata']['standard']['city'])) {
				// $result['city'] = ucwords(strtolower($array_data['Geodata']['Standard']['city']));
				$result['city'] = ucwords(mb_strtolower($array_data['geodata']['standard']['city']));
			}
			if(!empty($array_data['Geodata']['standard']['prov'])) {
				$result['region'] = ucfirst(mb_strtolower($array_data['geodata']['standard']['prov']));
			}
			$result['country'] = 'CA'; // It's geocoder_ca and will always be Canada
			$result['status'] = 'success';
		}
		return $result;
	}
	
}
?>