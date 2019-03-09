<?php
/**
 * Created by PhpStorm.
 * User: Chauncey
 * Date: 2019/3/9
 * Time: 3:04
 */

require '../../vendor/autoload.php';

use GuzzleHttp\Cookie\CookieJar;
require '../../init.php';

function getDataParams($region = 'cn-hongkong'){
    $params = [
        'commodityCode' => 'swas',
        'instanceType' => '',
        'filterStamp' => '',
        'specCode' => '',
        'refundSpecCode' => '',
        'sign' => '',
        'data' => [
            'duration' =>  '12',
            'pricingCycle' => 'Month',
            'quantity'   => '1',
            'orderType'  => 'BUY',
            'chargeType' => 'PREPAY',
        ],
        'components' => [
            'region' => $region,
            'image_type' => 'systemimage',
            'system_image'  => 'fe9c66133a9d4688872869726b5284f4',
            'version_type' => 'version_1',
            'data_disk'  => 0
        ]
    ];
    //"aliyun-default-collina "
    //"aliyun-default-umid"
    return base64_encode(json_encode([$params]));
}

function check_stock($region = 'cn-shenzhen'){
    $client = new \GuzzleHttp\Client();
    parse_str(config::get('aliyun_cookie'), $cookieArray);
    $jar = CookieJar::fromArray($cookieArray, 'common-buy.aliyun.com');

    $formData = [
        'data' => getDataParams($region),
        'secToken' => 'kwRquWA6eGHDfmmsWR9aA2',
        'token' => "aliyun-default-umid",
        'collina' => 'aliyun-default-collina'
    ];
    $headers = ['content-type' => 'application/x-www-form-urlencoded;charset=UTF-8'];

    $response = $client->request('POST', 'https://common-buy.aliyun.com/buy/Operator/getPrice.json', [
        'form_params' => $formData,
        'headers' => $headers,
        'cookies' => $jar
    ]);

    $body = (string)$response->getBody();
    $result = json_decode($body, true);

    if(isset($result['code']) && $result['code'] == '200'){
        return [true, $result['message']];
    }
    return [false, $result['message']];
}

$check_regions = ['cn-hongkong','cn-shenzhen','ap-southeast-1'];
foreach($check_regions as $region){
    $status = '无货';
    list($result, $message) = check_stock($region);
    if($result){
        $status = '<font color="red">有货</font>';
    }
    echo '地区:'.$region.' '.$status.'; message => '.$message.'<br>';
}
