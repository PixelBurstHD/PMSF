<?php
$timing['start'] = microtime(true);
include('config/config.php');
global $map, $fork, $db, $raid_bosses, $webhookUrl, $sendWebhook;
$pokemonId = !empty($_POST['pokemonId']) ? $_POST['pokemonId'] : 0;
$gymId = !empty($_POST['gymId']) ? $_POST['gymId'] : 0;
$mins = !empty($_POST['mins']) ? $_POST['mins'] : 0;
$secs = !empty($_POST['secs']) ? $_POST['secs'] : 0;

// brimful of asha on the:
$forty_five = 45 * 60;
$hour = 3600;

// set content type
header('Content-Type: application/json');

$now = new DateTime();
$now->sub(new DateInterval('PT20S'));

$d = array();
$d['status'] = "ok";

$d["timestamp"] = $now->getTimestamp();

//$db->debug();
// fetch fort_id
$gym = $db->get("forts", ['id', 'name', 'lat', 'lon'], ['external_id' => $gymId]);
$gymId = $gym['id'];

$add_seconds = ($mins * 60) + $secs;
$time_battle = time() + $add_seconds;
$time_end = $time_battle + $forty_five;
// fake the battle start and spawn times cuz rip hashing :(
$time_spawn = time() - $forty_five;
$extId = rand(0, 65535) . rand(0, 65535);
$level = 0;
if(strpos($pokemonId,'egg_') !== false){
    $level = (int)substr($pokemonId,4,1);
    $time_spawn = time() + $add_seconds;
}


$cols = [
    'external_id' => $gymId,
    'fort_id' => $gymId,
    'level' => $level,
    'time_spawn' => $time_spawn,
    'time_battle' => $time_battle,
    'time_end' => $time_end,
    'cp' => 0,
    'pokemon_id' => 0,
    'move_1' => 0, // struggle
    'move_2' => 0

];
if (array_key_exists($pokemonId, $raid_bosses)) {
    $time_end = time() + $add_seconds;
// fake the battle start and spawn times cuz rip hashing :(
    $time_battle = $time_end - $forty_five;
    $time_spawn = $time_battle - $hour;
    $cols['pokemon_id'] = $pokemonId;
    $cols['move_1'] = 133; // struggle :(
    $cols['move_2'] = 133;
    $cols['level'] = $raid_bosses[$pokemonId]['level']; // struggle :(
    $cols['cp'] = $raid_bosses[$pokemonId]['cp'];
} elseif($cols['level'] === 0) {
    // no boss or egg matched
    http_response_code(500);
}
$db->query('DELETE FROM raids WHERE fort_id IN ( SELECT id FROM forts WHERE external_id = ":external")', [':external' => $gymId]);
$db->insert("raids", $cols);

if ($sendWebhook === true) {
    // webhook stuff:
    // build webhook array:

    $webhook = [
        'message' => [
            'gym_id' => $cols['external_id'],
            'pokemon_id' => $cols['pokemon_id'],
            'cp' => $cols['cp'],
            'move_1' => $cols['move_1'],
            'move_2' => $cols['move_2'],
            'level' => $cols['level'],
            'latitude' => $gym['lat'],
            'longitude' => $gym['lon'],
            'raid_end' => $time_end,
            'team' => 0,
            'name' => $gym['name']
        ],
        'type' => 'raid'
    ];
    if(strpos($pokemonId,'egg_') !== false) {
        $webhook['message']['raid_begin'] = $time_spawn;
    }


    $c = curl_init($webhookUrl);
    curl_setopt($c, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($c, CURLOPT_POST, true);
    curl_setopt($c, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($c, CURLOPT_HTTPHEADER, ['Content-type: application/json', 'User-Agent: python-requests/2.18.4']);
    curl_setopt($c, CURLOPT_POSTFIELDS, json_encode($webhook));
    curl_exec($c);
    curl_close($c);
}

$jaysson = json_encode($d);
echo $jaysson;