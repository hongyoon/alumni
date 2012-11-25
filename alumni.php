<?php
require 'facebook/facebook.php';
$facebook = new Facebook(array(
	'appId'  => '173601072702495',
	'secret' => 'fcd57788f0fda71749bd7aac0dea3239',
));

$debug = '';

function error($error) {
	debug("Error: $error");
}

function debug($msg) {
	global $debug;
	$debug .= "$msg\n";
}

$db_filename = 'alumni.txt';
$members = array();

Class City {
	public static $cities = array();
	public $id;
	public $name;
	public $members_location;
	public $members_hometown;
	
	public function __construct($city) {
		if ($city['id'] == 0) return FALSE;
		$this->id = $city['id'];
		$this->name = $city['name'];
		$this->members_location = array();
		$this->members_hometown = array();
		self::$cities[$this->id] = $this;
	}
	
	public static function get($city) {
		if ($city['id'] == 0) return FALSE;
		if (array_key_exists($city['id'], self::$cities)) {
			return self::$cities[$city['id']];
		} else {
			return new City($city);
		}
	}
	
	public function add_member(&$member, $hometown=false) {
		if ($hometown) {
			$list = &$this->members_hometown;
		} else {
			$list = &$this->members_location;
		}
		if (! array_key_exists($member->id, $list)) {
			$list[$member->id] = $member;
		}
	}
	
	public function __toString() {
		if (is_string($this->name)) {
			return $this->name;
		} else return '';
	}
}

Class Member {
	public $id;
	public $name;
	public $link;
	public $username;
	public $location;
	public $hometown;
	public $updated_time;
	
	public function __construct($profile) {
		debug(print_r($profile,true));
		$this->id = $profile['id'];
		$this->name = $profile['name'];
		if ( ! empty($profile['updated_time']))
			$this->updated_time = $profile['updated_time'];
		$this->link = $profile['link'];
		$this->username = $profile['username'];
		if ($profile['location']) {
			$this->location = City::get($profile['location']);
			$this->location->add_member($this);
		}
		if ($profile['hometown']) {
			$this->hometown = City::get($profile['hometown']);
			$this->hometown->add_member($this, true);
		}
	}

	public function __toString() {
		return $this->name;
	}
}

function load_db($fn) {
	$db = array();
	$db_file = file('alumni.txt');
	foreach ($db_file as $line) {
		list($id, $token) = explode("\t",trim($line));
		if ($id != 0) {
			$db[$id] = $token;
		}
	}
	return $db;
}

function save_db($db, $fn) {
	$handle = fopen($fn, 'w');
	foreach ($db as $id => $token) {
		fwrite($handle, "$id\t$token\n");
	}
	fclose($handle);
}

$db = load_db($db_filename);

$user = $facebook->getUser();
if ($user) {
	try {
		$user_profile = $facebook->api('/me');
	} catch (FacebookApiException $e) {
		error($e);
		$user = null;
	}
}

if ($user) {
	if (!array_key_exists($user_profile['id'], $db)) {
		$db[$user_profile['id']] = $facebook->getAccessToken();
		save_db($db, $db_filename);
	}
//	$logoutUrl = $facebook->getLogoutUrl();
} else {
	$loginUrl = $facebook->getLoginUrl(array('scope' => 'user_hometown,user_location,offline_access'));
}


$members = array();
foreach ($db as $id => $token) {
	try {
		$m = new Member($facebook->setAccessToken($token)->api('/'.$id));
	} catch (FacebookApiException $e) {
		error($e->getResult());
		$m = null;
		if ($e->getType() == "OAuthException") {
			// Load public profile instead
			$m = new Member($facebook->api('/'.$id));
		}
	}
	if ($m) $members[] = $m;
}
?>
<!DOCTYPE html>
<html xmlns:fb="http://www.facebook.com/2008/fbml">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
	<title>HMCC Alumni Test</title>
	<style type="text/css">
		body { font-family: Lucida Grande, Lucida Sans Unicode, Verdana, sans-serif; font-size: 80%; color: #333; padding: 0 1ex; }
		table { border-collapse: collapse; }
		th, td.blank { background: #eee; }
		th, td { border: 1px solid #666; padding: 0.25em 0.5em; vertical-align: middle; }
		td.image { padding: 0; }
		td.image img { border: none; vertical-align: middle; }
		.debug { white-space: pre; }
		#map_canvas { height: 320px; width: 480px; }
	</style>
<script type="text/javascript" src="http://maps.google.com/maps/api/js?sensor=false"></script>
<script type="text/javascript">
	var geocoder;
	var map;
	function initialize() {
		geocoder = new google.maps.Geocoder();
		var latlng = new google.maps.LatLng(0, 0);
		var myOptions = {
			zoom: 1,
			center: latlng,
			mapTypeId: google.maps.MapTypeId.ROADMAP
		};
		map = new google.maps.Map(document.getElementById("map_canvas"), myOptions);
<?php
		foreach (City::$cities as $c) {
			if (count($c->members_hometown) == 0) continue;
			echo "		codeAddress('$c');\n";
		}
?>
	}
	
	function codeAddress(address) {
		geocoder.geocode( { 'address': address}, function(results, status) {
			if (status == google.maps.GeocoderStatus.OK) {
				var marker = new google.maps.Marker({
					map: map,
					position: results[0].geometry.location
				});
			} else {
				//alert("Geocode was not successful for the following reason: " + status);
			}
		});
	}
</script>
</head>
<body onload="initialize()">
	<h1>HMCC Alumni Database Test</h1>
	<?php
	if (!$user) {
		echo '<p><a href="'.$loginUrl.'">Add your Facebook to database</a></p>';
	}
	?>
	<table cellpadding="0" cellspacing="0">
		<thead>
			<tr>
				<th></th>
				<th>Name</th>
				<th>Location</th>
				<th>Hometown</th>
				<th>Last Profile Update</th>
			</tr>
		</thead>
		<tbody>
	<?php
	foreach ($members as $m) {
?>
			<tr>
				<td class="image"><img src="https://graph.facebook.com/<?php echo $m->id ?>/picture" /></td>
				<td><a href="<?php echo $m->link ?>"><?php echo $m->name ?></a></td>
				<td<?php
				if ($m->location) {
					echo '>'.$m->location;
				} else {
					echo ' class ="blank">';
				}
				?></td>
				<td<?php
				if ($m->hometown) {
					echo '>'.$m->hometown;
				} else {
					echo ' class ="blank">';
				}
				?></td>
				<td<?php echo ($m->updated_time ? '>'.date('F j, Y g:ia T', strtotime($m->updated_time)) : ' class="blank">'); ?></td>
			</tr>
<?php
	}
	?>
		</tbody>
	</table>
	<h2>Locations</h2>
	<table cellpadding="0" cellspacing="0">
		<thead>
			<tr>
				<th>Name</th>
				<th># Members</th>
				<th>Names</th>
			</tr>
		</thead>
		<tbody>
	<?php
	foreach (City::$cities as $c) {
		if (count($c->members_location) == 0) continue;
?>
			<tr>
				<td><?php echo $c->name ?></td>
				<td><?php echo count($c->members_location) ?></td>
				<td><?php echo implode(', ',$c->members_location) ?></td>
			</tr>
<?php
	}
	?>
		</tbody>
	</table>
	<h2>Hometown Map</h2>
	<div id="map_canvas"></div>
</body>
</html>
<!-- DEBUG DATA
<?php echo $debug ?>
-->