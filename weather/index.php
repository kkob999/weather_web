<?php
require './vendor/autoload.php';
use App\Models\DB;
use League\Csv\Writer;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Slim\Exception\NotFoundException;
use Selective\BasePath\BasePathMiddleware;
use Psr\Container\ContainerInterface;

// กำหนด slim instance app
$app = AppFactory::create();
$app->addBodyParsingMiddleware();
$app->addErrorMiddleware(true, true, true);
// กำหนด BasePath
$app->setBasePath('/weather_web/weather');

// กำหนด Routing
$app->get('/', function (Request $request, Response $response, $args) {
    $response->getBody()->write("Hello world!");
    return $response;
});

$app->get('/current', function (Request $request, Response $response, $args) {
    //fetch hourly
    include_once 'db.php';
    $curl = curl_init();

    curl_setopt_array(
        $curl,
        array(
            CURLOPT_URL => "https://api.open-meteo.com/v1/forecast?latitude=18.79&longitude=99.00&timezone=GMT&current_weather=true&hourly=temperature_2m,relativehumidity_2m,dewpoint_2m,pressure_msl,cloudcover,windspeed_10m,weathercode,visibility,precipitation_probability&forecast_days=1&daily=temperature_2m_max,temperature_2m_min,uv_index_max,sunrise,sunset",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
        )
    );

    $res = curl_exec($curl);

    curl_close($curl);

    $data = json_decode($res, true);
    $time_arr = $data['hourly']['time'];

    $gmt_offset = 7;


    $rows = array(array());
    $att = array(
        'latitude',
        'longitude',
        'temperature',
        'humid',
        'dewpoint',
        'cloudcover',
        'pressure',
        'wind_speed',
        'icon',
        'visibility',
        'high_temp',
        'low_temp',
        'uv',
        'sunrise',
        'sunset',
        'time'
    );

    for ($i = 0; $i < sizeof($att); $i++) {
        $rows[0][$i] = $att[$i];
    }


    $idx = 1;
    $check = $data['current_weather']['time'];

    for ($i = 0; $i < sizeof($time_arr); $i++) {
        if ($check == $data['hourly']['time'][$i]) {
            $rows[$idx][0] = $data['latitude'];
            $rows[$idx][1] = $data['longitude'];
            $rows[$idx][2] = $data['hourly']['temperature_2m'][$i];
            $rows[$idx][3] = $data['hourly']['relativehumidity_2m'][$i];
            $rows[$idx][4] = $data['hourly']['dewpoint_2m'][$i];
            $rows[$idx][5] = $data['hourly']['cloudcover'][$i];
            $rows[$idx][6] = $data['hourly']['pressure_msl'][$i];
            $rows[$idx][7] = $data['hourly']['windspeed_10m'][$i];
            $rows[$idx][8] = $data['hourly']['weathercode'][$i];
            $rows[$idx][9] = $data['hourly']['visibility'][$i];
            $rows[$idx][10] = $data['daily']['temperature_2m_max'][0];
            $rows[$idx][11] = $data['daily']['temperature_2m_min'][0];
            $rows[$idx][12] = $data['daily']['uv_index_max'][0];
            $rows[$idx][13] = $data['daily']['sunset'][0];
            $rows[$idx][14] = $data['daily']['sunrise'][0];
            $local_time = (int)substr($data['hourly']['time'][$i],11,2)+$gmt_offset;
            if ($local_time==24) {
                $local_time = "00";
            }else if ($local_time>24) {
                $tm = $local_time-24;
                $local_time = '0'.$tm;
            }
            $rows[$idx][15] = substr($data['hourly']['time'][$i],0,11).$local_time.substr($data['hourly']['time'][$i],13,3);
        }
    }


    $file = 'current.csv';
    $csv = Writer::createFromPath($file, 'w');
    $csv->insertAll($rows);

    $csvFile = fopen($file, 'r');

    fgetcsv($csvFile);

    $conn = OpenCon();
    $sql = "DELETE FROM current_weather";
    $conn->query($sql);
    while (($getData = fgetcsv($csvFile, 10000, ",")) !== FALSE) {

        $latitude = $getData[0];
        $longitude = $getData[1];
        $temperature = $getData[2];
        $humid = $getData[3];
        $dewpoint = $getData[4];
        $cloudcover = $getData[5];
        $pressure = $getData[6];
        $wind_speed = $getData[7];
        $icon = $getData[8];
        $visibility = $getData[9];
        $high_temp = $getData[10];
        $low_temp = $getData[11];
        $uv = $getData[12];
        $sunrise = $getData[13];
        $sunset = $getData[14];
        $time = $getData[15];

        $sql = "INSERT INTO current_weather(latitude, longitude, temperature, humid, dewpoint, cloudcover, pressure, wind_speed, icon, visibility,high_temp,low_temp,uv,sunrise,sunset, time)
        VALUES('$latitude', '$longitude', '$temperature', '$humid', '$dewpoint', '$cloudcover', '$pressure', '$wind_speed', '$icon', '$visibility', '$high_temp', '$low_temp', '$uv', '$sunrise', '$sunset', '$time')";
        if ($conn->query($sql) === TRUE) {
            // $conn->query($sql);
            $sql = " SELECT * FROM current_weather";
            $result = $conn->query($sql);
        } else {
            echo "Error: " . $sql . "<br>" . $conn->error;
        }
    }

    fclose($csvFile);
    CloseCon($conn);


    $response->getBody()->write($res);
    return $response;
});



$app->get('/current/{city}', function (Request $request, Response $response, $args) {
    include_once 'db.php';
    $city = $request->getAttribute('city');

    //fetch city
    $curl = curl_init();

    curl_setopt_array(
        $curl,
        array(
            CURLOPT_URL => 'http://api.openweathermap.org/geo/1.0/direct?q=' . $city . '&appid=207b5ebcf8768062b41364ba2f183b0d',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
        )
    );

    $res = curl_exec($curl);

    curl_close($curl);

    $loc = json_decode($res, true);
    $lat = round($loc[0]['lat'], 2);
    $lon = round($loc[0]['lon'], 2);
    $timezone = $loc[0]['country'];

    settype($lat, 'string');
    settype($lon, 'string');

    //fetch gmt time

    $url_loc = 'https://timezone.abstractapi.com/v1/current_time/?api_key=6877a856b24e4abd8ec1c7fcdef72670&location='.$city.','.$timezone;

    $curl = curl_init();

    curl_setopt_array(
        $curl,
        array(
            CURLOPT_URL => $url_loc,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
        )
    );

    $getgmt = curl_exec($curl);

    curl_close($curl);
    
    $gmt = json_decode($getgmt, true);

    $gmt_offset = $gmt['gmt_offset'];


    //fetch lat lon

    $url_loc = 'https://api.open-meteo.com/v1/forecast?latitude=' . $lat . '&longitude=' . $lon . '&timezone=GMT&current_weather=true&hourly=temperature_2m,relativehumidity_2m,dewpoint_2m,pressure_msl,cloudcover,windspeed_10m,weathercode,visibility,precipitation_probability&forecast_days=1&daily=temperature_2m_max,temperature_2m_min,uv_index_max,sunrise,sunset';

    $curl = curl_init();

    curl_setopt_array(
        $curl,
        array(
            CURLOPT_URL => $url_loc,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
        )
    );

    $location = curl_exec($curl);

    curl_close($curl);

    $data = json_decode($location, true);

    $time_arr = $data['hourly']['time'];

    $rows = array(array());
    $att = array(
        'latitude',
        'longitude',
        'temperature',
        'humid',
        'dewpoint',
        'cloudcover',
        'pressure',
        'wind_speed',
        'icon',
        'visibility',
        'high_temp',
        'low_temp',
        'uv',
        'sunrise',
        'sunset',
        'time'
    );

    for ($i = 0; $i < sizeof($att); $i++) {
        # code...
        $rows[0][$i] = $att[$i];
    }

    $idx = 1;
    $check = $data['current_weather']['time'];

    for ($i = 0; $i < sizeof($time_arr); $i++) {
        if ($check == $data['hourly']['time'][$i]) {
            $rows[$idx][0] = $data['latitude'];
            $rows[$idx][1] = $data['longitude'];
            $rows[$idx][2] = $data['hourly']['temperature_2m'][$i];
            $rows[$idx][3] = $data['hourly']['relativehumidity_2m'][$i];
            $rows[$idx][4] = $data['hourly']['dewpoint_2m'][$i];
            $rows[$idx][5] = $data['hourly']['cloudcover'][$i];
            $rows[$idx][6] = $data['hourly']['pressure_msl'][$i];
            $rows[$idx][7] = $data['hourly']['windspeed_10m'][$i];
            $rows[$idx][8] = $data['hourly']['weathercode'][$i];
            $rows[$idx][9] = $data['hourly']['visibility'][$i];
            $rows[$idx][10] = $data['daily']['temperature_2m_max'][0];
            $rows[$idx][11] = $data['daily']['temperature_2m_min'][0];
            $rows[$idx][12] = $data['daily']['uv_index_max'][0];
            $rows[$idx][13] = $data['daily']['sunset'][0];
            $rows[$idx][14] = $data['daily']['sunrise'][0];
            $local_time = (int)substr($data['hourly']['time'][$i],11,2)+$gmt_offset;
            if ($local_time==24) {
                $local_time = "00";
            }else if ($local_time>24) {
                $tm = $local_time-24;
                $local_time = '0'.$tm;
            }
            $rows[$idx][15] = substr($data['hourly']['time'][$i],0,11).$local_time.substr($data['hourly']['time'][$i],13,3);
        }
    }

    $file = 'current_search.csv';
    $csv = Writer::createFromPath($file, 'w');
    $csv->insertAll($rows);

    $csvFile = fopen($file, 'r');

    fgetcsv($csvFile);

    $conn = OpenCon();
    $sql = "DELETE FROM current_weather";
    $conn->query($sql);
    while (($getData = fgetcsv($csvFile, 10000, ",")) !== FALSE) {

        // $query = "SELECT id FROM hourly_weather WHERE id = '" . $getData[0] . "'";

        $latitude = $getData[0];
        $longitude = $getData[1];
        $temperature = $getData[2];
        $humid = $getData[3];
        $dewpoint = $getData[4];
        $cloudcover = $getData[5];
        $pressure = $getData[6];
        $wind_speed = $getData[7];
        $icon = $getData[8];
        $visibility = $getData[9];
        $high_temp = $getData[10];
        $low_temp = $getData[11];
        $uv = $getData[12];
        $sunrise = $getData[13];
        $sunset = $getData[14];
        $time = $getData[15];

        $sql = "INSERT INTO current_weather(latitude, longitude, temperature, humid, dewpoint, cloudcover, pressure, wind_speed, icon, visibility,high_temp,low_temp,uv,sunrise,sunset, time)
        VALUES('$latitude', '$longitude', '$temperature', '$humid', '$dewpoint', '$cloudcover', '$pressure', '$wind_speed', '$icon', '$visibility', '$high_temp', '$low_temp', '$uv', '$sunrise', '$sunset', '$time')";
        if ($conn->query($sql) === TRUE) {
            // $conn->query($sql);
            $sql = " SELECT * FROM current_weather";
            $result = $conn->query($sql);
        } else {
            echo "Error: " . $sql . "<br>" . $conn->error;
        }
    }

    fclose($csvFile);
    CloseCon($conn);

    // file_put_contents("hourly_def.json", $res);
    $response->getBody()->write($res);
    return $response;
});



$app->get('/hourly', function (Request $request, Response $response, $args) {

    //fetch hourly
    include_once 'db.php';
    $curl = curl_init();

    curl_setopt_array(
        $curl,
        array(
            CURLOPT_URL => "https://api.open-meteo.com/v1/forecast?latitude=18.79&longitude=99.00&timezone=GMT&&hourly=temperature_2m,relativehumidity_2m,dewpoint_2m,pressure_msl,cloudcover,windspeed_10m,weathercode,visibility,precipitation_probability&forecast_days=3&daily=temperature_2m_max,temperature_2m_min,uv_index_max,sunrise,sunset",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
        )
    );

    $res = curl_exec($curl);

    curl_close($curl);

    $data = json_decode($res, true);
    $time_arr = $data['hourly']['time'];

    $rows = array(array());
    $att = array(
        'id',
        'latitude',
        'longitude',
        'temperature',
        'high_temp',
        'low_temp',
        'humid',
        'dewpoint',
        'pressure',
        'cloudcover',
        'wind_speed',
        'rain',
        'icon',
        'visibility',
        'uv',
        'sunrise',
        'sunset',
        'time',
        'city'
    );

    for ($i = 0; $i < sizeof($att); $i++) {
        # code...
        $rows[0][$i] = $att[$i];
    }

    $j = 0;
    $idx = 1;
    for ($i = 0; $i < sizeof($time_arr); $i++) {
        if ($i == 0 || $i == 24 || $i == 48) {
            $high_temp = $data['daily']['temperature_2m_max'][$j];
            $low_temp = $data['daily']['temperature_2m_min'][$j];
            $uv = $data['daily']['uv_index_max'][$j];
            $sunset = $data['daily']['sunset'][$j];
            $sunrise = $data['daily']['sunrise'][$j];
            $j++;
        }

        $rows[$idx][0] = $i;
        $rows[$idx][1] = $data['latitude'];
        $rows[$idx][2] = $data['longitude'];
        $rows[$idx][3] = $data['hourly']['temperature_2m'][$i];
        $rows[$idx][4] = $high_temp;
        $rows[$idx][5] = $low_temp;
        $rows[$idx][6] = $data['hourly']['relativehumidity_2m'][$i];
        $rows[$idx][7] = $data['hourly']['dewpoint_2m'][$i];
        $rows[$idx][9] = $data['hourly']['cloudcover'][$i];
        $rows[$idx][8] = $data['hourly']['pressure_msl'][$i];
        $rows[$idx][10] = $data['hourly']['windspeed_10m'][$i];
        $rows[$idx][11] = $data['hourly']['precipitation_probability'][$i];
        $rows[$idx][12] = $data['hourly']['weathercode'][$i];
        $rows[$idx][13] = $data['hourly']['visibility'][$i];
        $rows[$idx][14] = $uv;
        $rows[$idx][15] = $sunset;
        $rows[$idx][16] = $sunrise;
        $rows[$idx][17] = $data['hourly']['time'][$i];
        $rows[$idx][18] = "Chiang Mai";

        $idx++;
    }

    $file = 'hour.csv';
    $csv = Writer::createFromPath($file, 'w');
    $csv->insertAll($rows);

    $csvFile = fopen($file, 'r');

    fgetcsv($csvFile);

    $conn = OpenCon();
    $sql = "DELETE FROM hourly_weather";
    $conn->query($sql);
    while (($getData = fgetcsv($csvFile, 10000, ",")) !== FALSE) {

        $id = $getData[0];
        $latitude = $getData[1];
        $longitude = $getData[2];
        $temperature = $getData[3];
        $high_temp = $getData[4];
        $low_temp = $getData[5];
        $humid = $getData[6];
        $dewpoint = $getData[7];
        $cloudcover = $getData[8];
        $pressure = $getData[9];
        $wind_speed = $getData[10];
        $rain = $getData[11];
        $icon = $getData[12];
        $visibility = $getData[13];
        $uv = $getData[14];
        $sunrise = $getData[15];
        $sunset = $getData[16];
        $time = $getData[17];
        $ct = $getData[18];


        $sql = "INSERT INTO hourly_weather(id, latitude, longitude, temperature, humid, dew_point, cloudcover, pressure, wind_speed, rain, icon, visibility,high_temp,low_temp,uv,sunrise,sunset,time,city)
VALUES('$id','$latitude', '$longitude', '$temperature', '$humid', '$dewpoint', '$cloudcover', '$pressure', '$wind_speed', '$rain', '$icon', '$visibility', '$high_temp', '$low_temp', '$uv', '$sunrise', '$sunset', '$time', '$ct')";
        if ($conn->query($sql) === TRUE) {
            $conn->query($sql);
            $sql = "SELECT * FROM hourly_weather";
            $result = $conn->query($sql);

        } else {
            echo "Error: " . $sql . "<br>" . $conn->error;
        }
    }

    fclose($csvFile);
    CloseCon($conn);

    // file_put_contents("hourly_def.json", $res);
    $response->getBody()->write($res);
    return $response;
});


$app->get('/hourly/{city}', function (Request $request, Response $response, $args) {
    include_once 'db.php';
    $city = $request->getAttribute('city');

    //fetch city
    $curl = curl_init();

    curl_setopt_array(
        $curl,
        array(
            CURLOPT_URL => 'http://api.openweathermap.org/geo/1.0/direct?q=' . $city . '&appid=207b5ebcf8768062b41364ba2f183b0d',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
        )
    );

    $res = curl_exec($curl);

    curl_close($curl);

    $loc = json_decode($res, true);
    $lat = round($loc[0]['lat'], 2);
    $lon = round($loc[0]['lon'], 2);
    $city = $loc[0]['name'];

    settype($lat, 'string');
    settype($lon, 'string');
    settype($city, 'string');

    // echo $lat;

    //fetch lat lon

    $url_loc = 'https://api.open-meteo.com/v1/forecast?latitude=' . $lat . '&longitude=' . $lon . '&timezone=GMT&&hourly=temperature_2m,relativehumidity_2m,dewpoint_2m,pressure_msl,cloudcover,windspeed_10m,weathercode,visibility,precipitation_probability&forecast_days=3&daily=temperature_2m_max,temperature_2m_min,uv_index_max,sunrise,sunset';

    $curl = curl_init();

    curl_setopt_array(
        $curl,
        array(
            CURLOPT_URL => $url_loc,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
        )
    );

    $location = curl_exec($curl);

    curl_close($curl);

    $data = json_decode($location, true);

    $time_arr = $data['hourly']['time'];

    $rows = array(array());
    $att = array(
        'id',
        'latitude',
        'longitude',
        'temperature',
        'high_temp',
        'low_temp',
        'humid',
        'dewpoint',
        'pressure',
        'cloudcover',
        'wind_speed',
        'rain',
        'icon',
        'visibility',
        'uv',
        'sunrise',
        'sunset',
        'time',
        'city'
    );

    for ($i = 0; $i < sizeof($att); $i++) {
        # code...
        $rows[0][$i] = $att[$i];
    }

    $j = 0;
    $idx = 1;
    for ($i = 0; $i < sizeof($time_arr); $i++) {
        if ($i == 0 || $i == 24 || $i == 48) {
            $high_temp = $data['daily']['temperature_2m_max'][$j];
            $low_temp = $data['daily']['temperature_2m_min'][$j];
            $uv = $data['daily']['uv_index_max'][$j];
            $sunset = $data['daily']['sunset'][$j];
            $sunrise = $data['daily']['sunrise'][$j];
            $j++;
        }

        $rows[$idx][0] = $i;
        $rows[$idx][1] = $data['latitude'];
        $rows[$idx][2] = $data['longitude'];
        $rows[$idx][3] = $data['hourly']['temperature_2m'][$i];
        $rows[$idx][4] = $high_temp;
        $rows[$idx][5] = $low_temp;
        $rows[$idx][6] = $data['hourly']['relativehumidity_2m'][$i];
        $rows[$idx][7] = $data['hourly']['dewpoint_2m'][$i];
        $rows[$idx][9] = $data['hourly']['cloudcover'][$i];
        $rows[$idx][8] = $data['hourly']['pressure_msl'][$i];
        $rows[$idx][10] = $data['hourly']['windspeed_10m'][$i];
        $rows[$idx][11] = $data['hourly']['precipitation_probability'][$i];
        $rows[$idx][12] = $data['hourly']['weathercode'][$i];
        $rows[$idx][13] = $data['hourly']['visibility'][$i];
        $rows[$idx][14] = $uv;
        $rows[$idx][15] = $sunset;
        $rows[$idx][16] = $sunrise;
        $rows[$idx][17] = $data['hourly']['time'][$i];
        $rows[$idx][18] = $city;

        $idx++;
    }

    $file = 'hour_search.csv';
    $csv = Writer::createFromPath($file, 'w');
    $csv->insertAll($rows);

    $csvFile = fopen($file, 'r');

    fgetcsv($csvFile);

    $conn = OpenCon();
    $sql = "DELETE FROM hourly_weather";
    $conn->query($sql);
    while (($getData = fgetcsv($csvFile, 10000, ",")) !== FALSE) {

        // $query = "SELECT id FROM hourly_weather WHERE id = '" . $getData[0] . "'";

        $id = $getData[0];
        $latitude = $getData[1];
        $longitude = $getData[2];
        $temperature = $getData[3];
        $high_temp = $getData[4];
        $low_temp = $getData[5];
        $humid = $getData[6];
        $dewpoint = $getData[7];
        $cloudcover = $getData[8];
        $pressure = $getData[9];
        $wind_speed = $getData[10];
        $rain = $getData[11];
        $icon = $getData[12];
        $visibility = $getData[13];
        $uv = $getData[14];
        $sunrise = $getData[15];
        $sunset = $getData[16];
        $time = $getData[17];
        $ct = $getData[18];


        $sql = "INSERT INTO hourly_weather(id, latitude, longitude, temperature, humid, dew_point, cloudcover, pressure, wind_speed, rain, icon, visibility,high_temp,low_temp,uv,sunrise,sunset,time, city)
VALUES('$id','$latitude', '$longitude', '$temperature', '$humid', '$dewpoint', '$cloudcover', '$pressure', '$wind_speed', '$rain', '$icon', '$visibility', '$high_temp', '$low_temp', '$uv', '$sunrise', '$sunset', '$time', '$city')";
        if ($conn->query($sql) === TRUE) {
            $conn->query($sql);
        } else {
            echo "Error: " . $sql . "<br>" . $conn->error;
        }
    }

    fclose($csvFile);
    CloseCon($conn);

    // file_put_contents("hourly_def.json", $res);
    $response->getBody()->write($res);
    return $response;
});



$app->get('/weekly/{city}', function (Request $request, Response $response, $args) {
    include_once 'db.php';
    $city = $request->getAttribute('city');

    //fetch city
    $curl = curl_init();

    curl_setopt_array(
        $curl,
        array(
            CURLOPT_URL => 'http://api.openweathermap.org/geo/1.0/direct?q=' . $city . '&appid=207b5ebcf8768062b41364ba2f183b0d',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
        )
    );

    $res = curl_exec($curl);

    curl_close($curl);

    $loc = json_decode($res, true);
    $lat = round($loc[0]['lat'], 2);
    $lon = round($loc[0]['lon'], 2);
    $city = $loc[0]['name'];

    settype($lat, 'string');
    settype($lon, 'string');
    settype($city, 'string');

    //fetch lat lon

    $url_loc = 'https://api.open-meteo.com/v1/forecast?latitude=' . $lat . '&longitude=' . $lon . '&timezone=GMT&&daily=windspeed_10m_max,weathercode,precipitation_probability_mean,et0_fao_evapotranspiration&forecast_days=14&daily=temperature_2m_max,temperature_2m_min,uv_index_max,sunrise,sunset';

    $curl = curl_init();

    curl_setopt_array(
        $curl,
        array(
            CURLOPT_URL => $url_loc,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
        )
    );

    $location = curl_exec($curl);

    curl_close($curl);

    $data = json_decode($location, true);

    $time_arr = $data['daily']['time'];

    $rows = array(array());
    $att = array('id', 'latitude', 'longitude', 'high_temp', 'low_temp', 'humid', 'wind_speed', 'rain', 'icon', 'uv', 'sunrise', 'sunset', 'time');

    for ($i = 0; $i < sizeof($att); $i++) {
        # code...
        $rows[0][$i] = $att[$i];
    }

    $idx = 1;
    for ($i = 0; $i < sizeof($time_arr); $i++) {
        $rows[$idx][] = $i;
        $rows[$idx][] = $data['latitude'];
        $rows[$idx][] = $data['longitude'];
        $rows[$idx][] = $data['daily']['temperature_2m_max'][$i];
        $rows[$idx][] = $data['daily']['temperature_2m_min'][$i];
        $rows[$idx][] = $data['daily']['et0_fao_evapotranspiration'][$i];
        $rows[$idx][] = $data['daily']['windspeed_10m_max'][$i];
        $rows[$idx][] = $data['daily']['precipitation_probability_mean'][$i];
        $rows[$idx][] = $data['daily']['weathercode'][$i];
        $rows[$idx][] = $data['daily']['uv_index_max'][$i];
        $rows[$idx][] = $data['daily']['sunrise'][$i];
        $rows[$idx][] = $data['daily']['sunset'][$i];
        $rows[$idx][] = $data['daily']['time'][$i];
        $rows[$idx][] = $city;
        $idx++;
    }

    $file = 'week_search.csv';
    $csv = Writer::createFromPath($file, 'w');
    $csv->insertAll($rows);

    $csvFile = fopen($file, 'r');

    fgetcsv($csvFile);
    $conn = OpenCon();
    $sql = "DELETE FROM weekly_weather";
    $conn->query($sql);
    while (($getData = fgetcsv($csvFile, 10000, ",")) !== FALSE) {

        $query = "SELECT id FROM weekly_weather WHERE id = '" . $getData[0] . "'";

        $id = $getData[0];
        $latitude = $getData[1];
        $longitude = $getData[2];
        $high_temp = $getData[3];
        $low_temp = $getData[4];
        $humid = $getData[5];
        $wind_speed = $getData[6];
        $rain = $getData[7];
        $icon = $getData[8];
        $uv = $getData[9];
        $sunrise = $getData[10];
        $sunset = $getData[11];
        $time = $getData[12];
        $ct = $getData[13];


        $sql = "INSERT INTO weekly_weather(id, latitude, longitude, high_temp,low_temp, humid,  wind_speed,  rain, icon,  uv,  sunrise,sunset ,time, city)
VALUES('$id','$latitude', '$longitude', '$high_temp', '$low_temp','$humid', '$wind_speed', '$rain', '$icon', '$uv', '$sunrise', '$sunset', '$time', '$city')";
        // $conn->query($sql);
        if ($conn->query($sql) === TRUE) {
            $conn->query($sql);
            // echo "insert data";
            $sql = "SELECT * FROM weekly_weather";
            $result = $conn->query($sql);
        } else {
            echo "Error: " . $sql . "<br>" . $conn->error;
        }

    }

    // Close opened CSV file
    fclose($csvFile);
    CloseCon($conn);


    $response->getBody()->write($res);
    // $response->getBody()->write($location);
    // echo gettype($lat);
    return $response;
});



$app->get('/weekly', function (Request $request, Response $response, $args) {

    include_once 'db.php';
    $curl = curl_init();

    curl_setopt_array(
        $curl,
        array(
            CURLOPT_URL => 'https://api.open-meteo.com/v1/forecast?latitude=18.79&longitude=99.00&timezone=GMT&&daily=windspeed_10m_max,weathercode,precipitation_probability_mean,et0_fao_evapotranspiration&forecast_days=14&daily=temperature_2m_max,temperature_2m_min,uv_index_max,sunrise,sunset',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
        )
    );

    $res = curl_exec($curl);

    curl_close($curl);

    $data = json_decode($res, true);
    $time_arr = $data['daily']['time'];

    $rows = array(array());
    $att = array('id', 'latitude', 'longitude', 'high_temp', 'low_temp', 'humid', 'wind_speed', 'rain', 'icon', 'uv', 'sunrise', 'sunset', 'time', 'city');

    for ($i = 0; $i < sizeof($att); $i++) {
        # code...
        $rows[0][$i] = $att[$i];
    }

    $idx = 1;
    for ($i = 0; $i < sizeof($time_arr); $i++) {
        $rows[$idx][] = $i;
        $rows[$idx][] = $data['latitude'];
        $rows[$idx][] = $data['longitude'];
        $rows[$idx][] = $data['daily']['temperature_2m_max'][$i];
        $rows[$idx][] = $data['daily']['temperature_2m_min'][$i];
        $rows[$idx][] = $data['daily']['et0_fao_evapotranspiration'][$i];
        $rows[$idx][] = $data['daily']['windspeed_10m_max'][$i];
        $rows[$idx][] = $data['daily']['precipitation_probability_mean'][$i];
        $rows[$idx][] = $data['daily']['weathercode'][$i];
        $rows[$idx][] = $data['daily']['uv_index_max'][$i];
        $rows[$idx][] = $data['daily']['sunrise'][$i];
        $rows[$idx][] = $data['daily']['sunset'][$i];
        $rows[$idx][] = $data['daily']['time'][$i];
        $rows[$idx][] = "Chiang Mai";
        $idx++;
    }

    $file = 'week.csv';
    $csv = Writer::createFromPath($file, 'w');
    $csv->insertAll($rows);

    $csvFile = fopen($file, 'r');

    fgetcsv($csvFile);
    $conn = OpenCon();
    $sql = "DELETE FROM weekly_weather";
    $conn->query($sql);
    while (($getData = fgetcsv($csvFile, 10000, ",")) !== FALSE) {

        $query = "SELECT id FROM weekly_weather WHERE id = '" . $getData[0] . "'";

        $id = $getData[0];
        $latitude = $getData[1];
        $longitude = $getData[2];
        $high_temp = $getData[3];
        $low_temp = $getData[4];
        $humid = $getData[5];
        $wind_speed = $getData[6];
        $rain = $getData[7];
        $icon = $getData[8];
        $uv = $getData[9];
        $sunrise = $getData[10];
        $sunset = $getData[11];
        $time = $getData[12];
        $ct = $getData[13];


        $sql = "INSERT INTO weekly_weather(id, latitude, longitude, high_temp,low_temp, humid,  wind_speed,  rain, icon,  uv,  sunrise,sunset ,time, city)
VALUES('$id','$latitude', '$longitude', '$high_temp', '$low_temp','$humid', '$wind_speed', '$rain', '$icon', '$uv', '$sunrise', '$sunset', '$time', '$ct')";
        $conn->query($sql);
        // if ($conn->query($sql) === TRUE) {
        //     $conn->query($sql);
        //     // echo "insert data";
        // } else {
        //     echo "Error: " . $sql . "<br>" . $conn->error;
        // }
    }

    // Close opened CSV file
    fclose($csvFile);
    CloseCon($conn);

    $response->getBody()->write($res);

    return $response;
});


$app->get('/monthly', function (Request $request, Response $response, $args) {
    include_once 'db.php';
    $day = intval(date("d"));
    $month_start = date("m");
    $month_end = date("m", strtotime("+1 Months"));
    $day_start = 01;
    $day_end = 20;

    if ($day >= 1 and $day <= 14) {
        if (in_array(date("m"), ['01', '03', '05', '07', '08', '10', '12'])) {
            //31
            $day_start = 16;
            $day_end = 20;

        } else if (date("m") == '2') {
            //febuary
            $day_start = 19;
            $day_end = 20;

        } else {
            //30
            $day_start = 17;
            $day_end = 20;
        }

        $month_start = date("m", strtotime("-1 Months"));
        $month_end = date("m");
    } else {
        $day_start = '01';
        $test = '05';
        if (in_array($test, ['01', '03', '05', '07', '08', '10', '12'])) {
            //31+4
            $day_end = '04';
        } elseif (date("m") == '2') {
            //28+7
            $day_end = '07';
        } else {
            $day_end = '05';
        }

    }
    //fetch current
    $curl = curl_init();

    $url_end = 'https://api.open-meteo.com/v1/forecast?latitude=18.79&longitude=99.00&timezone=GMT&null=null&start_date=' . date("Y") . '-' . $month_start . '-' . $day_start . '&end_date=' . date("Y") . '-' . $month_end . '-' . $day_end . '&daily=weathercode&daily=temperature_2m_max%2Ctemperature_2m_min';

    curl_setopt_array(
        $curl,
        array(
            CURLOPT_URL => $url_end,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
        )
    );

    $result = curl_exec($curl);

    curl_close($curl);

    $data = json_decode($result, true);
    $time_arr = $data['daily']['time'];

    $rows = array(array());
    $att = array(
        'id',
        'high_temp',
        'low_temp',
        'icon',
        'time',
        'city'
    );

    for ($i = 0; $i < sizeof($att); $i++) {
        # code...
        $rows[0][$i] = $att[$i];
    }

    $idx = 1;
    for ($i = 0; $i < sizeof($time_arr); $i++) {
        $rows[$idx][] = $i;
        $rows[$idx][] = $data['daily']['temperature_2m_max'][$i];
        $rows[$idx][] = $data['daily']['temperature_2m_min'][$i];
        $rows[$idx][] = $data['daily']['weathercode'][$i];
        $rows[$idx][] = $data['daily']['time'][$i];
        $rows[$idx][] = "Chiang Mai";
        $idx++;
    }

    $file = 'month.csv';
    $csv = Writer::createFromPath($file, 'w');
    $csv->insertAll($rows);

    $csvFile = fopen($file, 'r');

    fgetcsv($csvFile);
    $conn = OpenCon();
    $sql = "DELETE FROM monthly_weather";
    $conn->query($sql);
    while (($getData = fgetcsv($csvFile, 10000, ",")) !== FALSE) {

        $query = "SELECT id FROM weekly_weather WHERE id = '" . $getData[0] . "'";

        $id = $getData[0];
        $high_temp = $getData[1];
        $low_temp = $getData[2];
        $icon = $getData[3];
        $time = $getData[4];
        $ct = $getData[5];


        $sql = "INSERT INTO monthly_weather(id, high_temp, low_temp, icon, time, city)VALUES
    ('$id','$high_temp', '$low_temp', '$icon', '$time', '$ct')";
        $conn->query($sql);

    }

    // Close opened CSV file
    fclose($csvFile);
    CloseCon($conn);

    $response->getBody()->write($result);

    return $response;
});

$app->get('/monthly/{city}', function (Request $request, Response $response, $args) {

    include_once 'db.php';
    $city = $request->getAttribute('city');

    $day = intval(date("d"));
    $month_start = date("m");
    $month_end = date("m", strtotime("+1 Months"));
    $day_start = 01;
    $day_end = 20;

    if ($day >= 1 and $day <= 14) {
        if (in_array(date("m"), ['01', '03', '05', '07', '08', '10', '12'])) {
            //31
            $day_start = 16;
            $day_end = 20;

        } else if (date("m") == '2') {
            //febuary
            $day_start = 19;
            $day_end = 20;

        } else {
            //30
            $day_start = 17;
            $day_end = 20;
        }

        $month_start = date("m", strtotime("-1 Months"));
        $month_end = date("m");
    } else {
        $day_start = '01';
        $test = '05';
        if (in_array($test, ['01', '03', '05', '07', '08', '10', '12'])) {
            //31+4
            $day_end = '04';
        } elseif (date("m") == '2') {
            //28+7
            $day_end = '07';
        } else {
            $day_end = '05';
        }

    }

    //fetch city
    $curl = curl_init();

    curl_setopt_array(
        $curl,
        array(
            CURLOPT_URL => 'http://api.openweathermap.org/geo/1.0/direct?q=' . $city . '&appid=207b5ebcf8768062b41364ba2f183b0d',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
        )
    );

    $res = curl_exec($curl);

    curl_close($curl);

    $loc = json_decode($res, true);
    $lat = round($loc[0]['lat'], 2);
    $lon = round($loc[0]['lon'], 2);
    $city = $loc[0]['name'];

    settype($lat, 'string');
    settype($lon, 'string');
    settype($city, 'string');

    //fetch lat lon

    $url_loc = 'https://api.open-meteo.com/v1/forecast?latitude=' . $lat . '&longitude=' . $lon . '&timezone=GMT&null=null&start_date=' . date("Y") . '-' . $month_start . '-' . $day_start . '&end_date=' . date("Y") . '-' . $month_end . '-' . $day_end . '&daily=weathercode&daily=temperature_2m_max%2Ctemperature_2m_min';

    $curl = curl_init();

    curl_setopt_array(
        $curl,
        array(
            CURLOPT_URL => $url_loc,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
        )
    );

    $location = curl_exec($curl);

    curl_close($curl);

    $data = json_decode($location, true);

    $time_arr = $data['daily']['time'];

    $rows = array(array());
    $att = array(
        'id',
        'high_temp',
        'low_temp',
        'icon',
        'time',
        'city'
    );

    for ($i = 0; $i < sizeof($att); $i++) {
        # code...
        $rows[0][$i] = $att[$i];
    }

    $idx = 1;
    for ($i = 0; $i < sizeof($time_arr); $i++) {
        $rows[$idx][] = $i;
        $rows[$idx][] = $data['daily']['temperature_2m_max'][$i];
        $rows[$idx][] = $data['daily']['temperature_2m_min'][$i];
        $rows[$idx][] = $data['daily']['weathercode'][$i];
        $rows[$idx][] = $data['daily']['time'][$i];
        $rows[$idx][] = $city;
        $idx++;
    }

    $file = 'month_search.csv';
    $csv = Writer::createFromPath($file, 'w');
    $csv->insertAll($rows);

    $csvFile = fopen($file, 'r');

    fgetcsv($csvFile);
    $conn = OpenCon();
    $sql = "DELETE FROM monthly_weather";
    $conn->query($sql);
    while (($getData = fgetcsv($csvFile, 10000, ",")) !== FALSE) {

        $query = "SELECT id FROM weekly_weather WHERE id = '" . $getData[0] . "'";

        $id = $getData[0];
        $high_temp = $getData[1];
        $low_temp = $getData[2];
        $icon = $getData[3];
        $time = $getData[4];
        $ct = $getData[5];


        $sql = "INSERT INTO monthly_weather(id, high_temp, low_temp, icon, time, city)VALUES
    ('$id','$high_temp', '$low_temp', '$icon', '$time', '$ct')";
        $conn->query($sql);

    }

    // Close opened CSV file
    fclose($csvFile);
    CloseCon($conn);

    $response->getBody()->write($res);

    return $response;

});

$app->run();