<?php
function read_json($raw)
{
    $json = json_decode($raw, true);
    if ($json == null) {
        $raw = str_replace('"{', "{", $raw);
        $raw = str_replace('}"', '}', $raw);
        $json = json_decode($raw, true);

        if ($json == null) {
            $last_left_quotes_pos = 0;
            $c = 0;
            while (($quotes_left_pos = mb_strpos($raw, ':"', $last_left_quotes_pos)) !== false) {
                $c++;
                $quotes_right_pos = mb_strpos($raw, '","', $quotes_left_pos);
                $quotes_right_pos2 = mb_strpos($raw, '"}', $quotes_left_pos);
                if ($quotes_right_pos === false && $quotes_right_pos2 === false) {
                    break;
                }
                if ($quotes_right_pos === false) {
                    $quotes_right_pos = $quotes_right_pos2;
                } else if ($quotes_right_pos2 === false) {

                } else {
                    $quotes_right_pos = min($quotes_right_pos, $quotes_right_pos2);
                }

                $sub_content = mb_substr($raw, $quotes_left_pos + 2, $quotes_right_pos - $quotes_left_pos - 3);

                $add_len = 0;
                if (mb_strpos($sub_content, '"') !== false) {
                    $last_content = $sub_content;
                    $sub_content = str_replace('"', '\"', $sub_content);
                    $left_text = mb_substr($raw, 0, $quotes_left_pos);
                    $right_text = mb_substr($raw, $quotes_right_pos);
                    $last_raw = $raw;
                    $raw = $left_text . ':"' . $sub_content . '' . $right_text;
                    $add_len = mb_strlen($sub_content) - mb_strlen($last_content);
                }
                $last_left_quotes_pos = $quotes_right_pos + mb_strlen('",') + $add_len + 1;
            }
            $json = json_decode($raw, true);

        }

    }
    return $json;
}


// $filepath = 'address_merge_with_mobile_data.json';
// $filepath = 'case_data_index.json';
$filepath = 'person_info.json';
if (!is_file($filepath)) {
    die('file not exists!');
}

$filename = pathinfo($filepath, PATHINFO_FILENAME);
$file_ext = pathinfo($filepath, PATHINFO_EXTENSION);
$fh = fopen($filepath, 'r');
$fhOutput = fopen($filename . "_output." . $file_ext, 'w+');
$fhError = fopen($filename . "_err." . $file_ext, 'w+');
$i = 0;
while (!feof($fh)) {
    $i++;
    $raw = fgets($fh);
    if (empty($raw)) {
        continue;
    }

    $json = read_json($raw);
    if ($json == null) {
        fwrite($fhError, trim($raw, "\n") . "\n");
        var_dump($i);
        var_dump($raw);
        var_dump(json_last_error_msg());
        continue;
    } else {
        fwrite($fhOutput, json_encode($json, JSON_UNESCAPED_UNICODE) . "\n");
        echo sprintf("scanf:%s          \r", $i);
    }
    unset($raw);
}
fclose($fh);
fclose($fhOutput);
fclose($fhError);