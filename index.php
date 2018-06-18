<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<title>Haxball Room List</title>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
<style type="text/css" media="screen">
/*====================================================
    - HTML Table Filter stylesheet
=====================================================*/
@import "filtergrid.css";

/*====================================================
	- General html elements
=====================================================*/
body{ 
	margin:15px; padding:15px; border:1px solid #666;
	font-family:Arial, Helvetica, sans-serif; font-size:88%; 
}
h2{ margin-top: 50px; }
caption{ margin:10px 0 0 5px; padding:10px; text-align:left; }
pre{ font-size:13px; margin:5px; padding:5px; background-color:#f4f4f4; border:1px solid #ccc;  }
.mytable{
	width:100%; font-size:12px;
	border:1px solid #ccc;
}
div.tools{ margin:5px; }
div.tools input{ background-color:#f4f4f4; border:2px outset #f4f4f4; margin:2px; }
th{ background-color:#003366; color:#FFF; padding:2px; border:1px solid #ccc; }
td{ padding:2px; border-bottom:1px solid #ccc; border-right:1px solid #ccc; }

</style>
<script language="javascript" type="text/javascript" src="actb.js"></script><!-- External script -->
<script language="javascript" type="text/javascript" src="tablefilter.js"></script>
</head>
<body>

<?php

function strhex($string) 
{
  $hexstr = unpack('H*', $string);
  return array_shift($hexstr);
}

function distance($lat1, $lon1, $lat2, $lon2) 
{
  $theta = $lon1 - $lon2;
  $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) +  cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
  $dist = acos($dist);
  $dist = rad2deg($dist);
  $miles = $dist * 60 * 1.1515;

  return ($miles * 1.609344);
}

function hexTo32Float($strHex) 
{
    $v = hexdec($strHex);
    $x = ($v & ((1 << 23) - 1)) + (1 << 23) * ($v >> 31 | 1);
    $exp = ($v >> 23 & 0xFF) - 127;
    return $x * pow(2, $exp - 23);
}

class Room
{
    var $n1, $n2, $url, $name, $actual_players, $max_players, $password, $country, $lat, $lon;
    
    function __construct($n1, $n2, $url, $name, $actual_players, $max_players, $password, $country, $lat, $lon)
    {
        $this->n1 = $n1;
        $this->n2 = $n2;
        $this->url = $url;
        $this->name = $name;
        $this->actual_players = $actual_players;
        $this->max_players = $max_players;
        $this->password = $password;
        $this->country = $country;
        $this->lat = $lat;
        $this->lon = $lon;
    }
    
    public function show()
    {
        echo "n1 $this->n1, n2 $this->n2, url $this->url, name $this->name, actual_players $this->actual_players, max_players $this->max_players, password $this->password, country $this->country, lat $this->lat, lon $this->lon";
    }
}

$rooms = array();

$HAXBALL_ROOM_ADDR = 'http://www.haxball.com/?roomid=';
$HAXBALL_ROOM_ADDR_HTML5 = 'http://html5.haxball.com/?c=';

$file_rooms_list = 'rooms.json';

$mutex_name = 'Random_mutex_name21ej345766dfjlkgjd8kdn84';

function read_bytes(&$data, $n, &$index)
{
	$n = intval($n);
    $result = array(substr($data, $index, $n));
    $index = $index + $n;
    return $result;
}

function bytes_as_short(&$data)
{
	$r = unpack('n', implode($data));
    return $r[1];
}

function bytes_as_uchar(&$data)
{
    $r = unpack('C', implode($data));
    return $r[1];
}

function bytes_as_float(&$data)
{
    return hexTo32Float(strhex(implode($data)));
}

function get_haxball_short(&$data, &$index)
{
    return bytes_as_short(read_bytes($data, 2, $index));
}

function get_haxball_uchar(&$data, &$index)
{
    return bytes_as_uchar(read_bytes($data, 1, $index));
}

function get_haxball_float(&$data, &$index)
{
    return bytes_as_float(read_bytes($data, 4, $index));
}

function get_haxball_string(&$data, &$index)
{
    $l = get_haxball_short($data, $index);
    return implode(read_bytes($data, $l, $index));
}

function get_haxball_string_uchar(&$data, &$index)
{
    $l = get_haxball_uchar($data, $index);
    return implode(read_bytes($data, $l, $index));
}

function get_haxball_room(&$data, &$index)
{
    global $HAXBALL_ROOM_ADDR;
    $n1 = get_haxball_short($data, $index);
    $n2 = get_haxball_short($data, $index);
    $url = $HAXBALL_ROOM_ADDR.get_haxball_string($data, $index);
    $name = get_haxball_string($data, $index);
    $actual_players = get_haxball_uchar($data, $index);
    $max_players = get_haxball_uchar($data, $index);
    $password = get_haxball_uchar($data, $index);
    $country = get_haxball_string($data, $index);
    $lat = get_haxball_float($data, $index);
    $lon = get_haxball_float($data, $index);
    return new Room($n1, $n2, $url, $name, $actual_players, $max_players, $password, $country, $lat, $lon);
}

function get_haxball_room_html5(&$data, &$index)
{
    global $HAXBALL_ROOM_ADDR_HTML5;
    $url = $HAXBALL_ROOM_ADDR_HTML5.get_haxball_string($data, $index);
    $n1 = get_haxball_short($data, $index);
    $n2 = get_haxball_uchar($data, $index);
    $name = get_haxball_string($data, $index);
    $country = get_haxball_string_uchar($data, $index);
    $lat = get_haxball_float($data, $index);
    $lon = get_haxball_float($data, $index);
    $password = get_haxball_uchar($data, $index);
    $max_players = get_haxball_uchar($data, $index);
    $actual_players = get_haxball_uchar($data, $index);
    return new Room($n1, $n2, $url, $name, $actual_players, $max_players, $password, $country, $lat, $lon);
}

function compare_rooms(&$x, &$y)
{
	$country_cmp_result = strcasecmp($x->country, $y->country);
	if($country_cmp_result != 0)
	{
		return $country_cmp_result;
	}
	return strcasecmp($x->name, $y->name);
}

function sort_rooms(&$rooms)
{
    usort($rooms, "compare_rooms");
}

function decode_list(&$encoded, $html5)
{
    $encoded_len = strlen($encoded);
    $index = 0;
    $header_size = 5;
    
    if($html5 == 1)
    {
        $header_size = 1;
    }
    read_bytes($encoded, $header_size, $index);
    $ROOMS = array();

    while($index < $encoded_len)
    {
        if($html5 == 1)
        {
            $ROOMS[] = get_haxball_room_html5($encoded, $index);
        }
        else
        {
            $ROOMS[] = get_haxball_room($encoded, $index);
        }
    }
    return $ROOMS;
}

function dump_rooms_to_json(&$rooms, $fn)
{
    $f = fopen($fn, 'w');
    fwrite($f, serialize($rooms));
    fclose($f);
}

function get_rooms(&$rooms)
{
    global $file_rooms_list;
    global $rooms;
    $xml = file_get_contents("http://www.haxball.com/list3");
    $xml_html5 = file_get_contents("http://html5.haxball.com/rs/api/list");

    $encoded_data = gzuncompress($xml);

    $rooms1 = decode_list($encoded_data, 0);
    $rooms2 = decode_list($xml_html5, 1);

    $rooms = array_merge($rooms1, $rooms2);
    sort_rooms($rooms);

    dump_rooms_to_json($rooms, $file_rooms_list);
}

function read_json_file($fn)
{
    $f = fopen($fn, 'r');
    $data = fread($f, filesize($fn));
    fclose($f);
    $json = unserialize($data);

    $rooms = array();
    foreach($json as $key):
        $rooms[] = new Room($key->n1, $key->n2, $key->url, $key->name, $key->actual_players, 
                    $key->max_players, $key->password, $key->country, $key->lat, $key->lon);
    endforeach;
    return $rooms;
}

#$location = json_decode(file_get_contents('http://freegeoip.net/json/'.@$_SERVER['HTTP_REFERER']));

#--------------------------------------------------------------

$ip_addr = $_SERVER['REMOTE_ADDR'];
$geoplugin = unserialize( file_get_contents('http://www.geoplugin.net/php.gp?ip='.$ip_addr) );

if ( is_numeric($geoplugin['geoplugin_latitude']) && is_numeric($geoplugin['geoplugin_longitude']) ) {

$lat = $geoplugin['geoplugin_latitude'];
$lon = $geoplugin['geoplugin_longitude'];
}

#--------------------------------------------------------------

$current_time = time();

#$mutex = new SyncMutex($mutex_name);
#$mutex->lock();

$file_modification_time = filemtime($file_rooms_list);
$interval = $current_time - $file_modification_time;
if($interval > 5)
{
	get_rooms($rooms);
}
else
{
    $rooms = read_json_file($file_rooms_list);
}

#$mutex->unlock();


?>
<dev>
<table border="1" cellpadding="10" id="table1" class="mytable">
    <thead><tr><th id='country'>Country</th><th id='name'>Name of room</th><th id='players'>Players</th><th id='html5'>HTML5</th><th id='password'>Has password</th><th name='distance'>Distance</th></tr></thead>
    <tbody>
    <?php foreach($rooms as $key => $value): ?>
        <tr>
            <td name='country'><?php echo $value->country; ?></td>
            <td name='name'><a href='<?php echo $value->url; ?>'><?php if(0 == strlen($value->name)) echo "[[EMPTY NAME]]"; else echo $value->name; ?></a></td>
            <td name='players'><?php echo $value->actual_players.'/'.$value->max_players; $url = '"https://www.google.pl/maps/@'.$value->lat.','.$value->lon.',14z"'; ?></td>
            <td name='HTML5'><?php if($value->n2 != 31) echo 'YES'; else echo 'NO'; ?></td>
            <td name='password'><?php if($value->password != 0) echo 'YES'; else echo 'NO'; ?></td>
            <td name='distance'><a href=<?php echo $url; ?>><?php echo distance($value->lat, $value->lon, $lat, $lon); ?></a></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</dev>
<dev>
<script language="javascript" type="text/javascript">
    var table3Filters = {
		col_0: "select",
        col_2: "select",
		col_3: "select",
		col_4: "select",
        loader: true,
        sort_select: true,
	}
	setFilterGrid("table1",1,table3Filters);
</script>
</dev>

</body>
</html> 
