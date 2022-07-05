<?php

define('ES_HOSTS', getenv('ES_HOSTS'));
!ES_HOSTS &&  die('es hosts not specified ');

include "es.php";
$es = getEs();


$file_index = [
    'person_info_output.json'                    => 'sh_ga_person_info_sample',
    'address_merge_with_mobile_data_output.json' => 'sh_ga_address_merge_with_mobile_data_sample',
    'case_data_index_output.json'                => 'sh_ga_case_data_index_sample',
];

// check files
foreach ($file_index as $filepath => $index) {
    if (!is_file($filepath)) {
        die(sprintf('file %s not exist', $filepath));
    }
}


foreach ($file_index as $filepath => $index) {
    $fh = fopen($filepath, "r");

    $params = [
        'body' => []
    ];
    while (!feof($fh)) {
        $row = fgets($fh);
        $json = json_decode($row, true);
        if ($json !== null) {
            $head = [
                'index' => [
                    '_index' => $index,
                    '_id'    => $json['_id'] ?? null
                ]
            ];
            $params['body'][] = $head;
            $params['body'][] = $json['_source'];
        }

        if (count($params['body']) >= 1000) {
            $res = $es->bulk($params);
            unset($params);
            $params = [
                'body' => []
            ];
        }
        unset($row);
    }

    if (count($params['body']) > 0) {
        $res = $es->bulk($params);
        unset($params);
    }
    fclose($fh);
}
echo "done!";
