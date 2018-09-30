<?php

require __DIR__ . '/vendor/autoload.php';

$api_base_url = 'https://api.pyramido.ca/v1/events';
$events = json_decode(file_get_contents('./file_lavitrinesag.json'), true);
$client = new \GuzzleHttp\Client(['headers' => [
    'Accept' => 'application/json',
    'Authorization' => 'Bearer FewtJrsUnkCHfp9exx2QulBPaUbnqqmLqT69YawYqJdE1AMEFCufgtPNbDkL',
]]);

$filtered_events = array_filter($events, function ($event) {
    return $event['start_date'] && $event['image_url'];
});

$nb_events_to_import = count($filtered_events);

echo "###################################################\r\n";
echo "#\r\n";
echo "# Importing {$nb_events_to_import} events\r\n";
echo "#\r\n";
echo "###################################################\r\n";

foreach ($filtered_events as $i => $event) {
    $image = $event['image_url'];
    $image_name = slugify($event['title']);

    try {
        $title = ucwords(mb_strtolower($event['title']));
        $description = $event['description'];

        $data = [
            'title' => iconv(mb_detect_encoding($title, mb_detect_order(), true), "UTF-8", $title),
            'description' => iconv(mb_detect_encoding($description, mb_detect_order(), true), "UTF-8", $description),
            'date' => date('Y-m-d', strtotime($event['start_date']))
        ];

        $res = $client->request('POST', $api_base_url, [ 'form_params' => $data ]);
        $response = json_decode((string) $res->getBody(), true)['data'];

        $res = $client->request('POST', $api_base_url . "/{$response['id']}/medias", [
            'multipart' => [
                [
                    'name'     => 'file',
                    'contents' => file_get_contents($image),
                    'filename' => "{$image_name}.jpg",
                ]
            ]
        ]);

        echo '[' . date('H:i:s') . "] Event {$response['id']} added. Sleeping 1.2 seconds.\r\n";
        sleep(1.2);
    } catch (\Exception $e) {
        echo '[' . date('H:i:s') . "] Event {$i} NOT added, got an exception.\r\n";
    }
}

function slugify($text)
{
    // replace non letter or digits by -
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);

    // transliterate
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);

    // remove unwanted characters
    $text = preg_replace('~[^-\w]+~', '', $text);

    // trim
    $text = trim($text, '-');

    // remove duplicate -
    $text = preg_replace('~-+~', '-', $text);

    // lowercase
    $text = strtolower($text);

    if (empty($text)) {
        return 'n-a';
    }

    return $text;
}
