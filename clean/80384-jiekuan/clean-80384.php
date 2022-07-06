<?php
/**
 * Created by PhpStorm.
 * User: ellermister
 * Date: 2022/7/5
 * Time: 18:29
 */

$filename = getenv('CLEAN_PATH');

if(!$filename){
    die('CLEAN_PATH not specified');
}

if (!is_file($filename)) {
    die('file not exist!');
}

$filepath_info = pathinfo($filename);
$file_output_path = $filepath_info['dirname'] . '/' . $filepath_info['filename'] . '_output.' . $filepath_info['extension'];

$fh = fopen($filename, 'r');
$fhOutput = fopen($file_output_path, 'w+');

$fields = array(
    0  => 'real_name',// '姓名',
    1  => 'loan_amount',//'借款金额',
    2  => 'sex', // 性别
    3  => 'hometown', // 籍贯
    4  => 'ic_expired_at', // 身份证到期日
    5  => 'ic_sign_org',//身份证发证机关
    6  => 'id_address',//户籍地址
    7  => 'home_address',//住址
    8  => 'wechat',//微信号
    9  => 'qq',//qq
    10 => 'ic_no',//身份证号
    11 => 'phone_num',//手机号
    12 => 'education_level',//教育程度
    13 => 'marital_status',//婚姻状态
    14 => 'children_num',//供养子女数
    15 => 'type_of_residence', // 居住类型
    16 => 'spouse_name',//配偶姓名
    17 => 'spouse_phone_num',//配偶手机号
    18 => 'spouse_employer',//配偶单位
    19 => 'immediate_family_name',//直系亲属姓名
    20 => 'immediate_family_kinship',//直属关系
    21 => 'immediate_family_phone_num',//直属手机号
    22 => 'immediate_family_address',   //直属住址
    23 => 'company name', // '单位名称',
    24 => 'department', // '部门',
    25 => 'job', //职务
    26 => 'job_start_time', //现单位工作起始时间
    27 => 'job_payday',// '每月支新日',
    28 => 'total_monthly_spending',// '每月总支出',
    29 => 'gross_monthly_income', //'每月总收入',
    30 => 'working_years',//'参加工作时间',
    31 => 'loan_date',//借款日期
    32 => 'enterprise_nature',//企业性质
    33 => 'birthday',//出生日期
);

$err_num = 0;
$line = 0;
while (!feof($fh)) {
    $line++;
    $row = fgets($fh);
    if ($line == 1) continue;

    $row = trim($row);
    $arr = explode("\t", $row);

    $data = [];
    foreach ($arr as $index => $value) {
        $name = $fields[$index];
        $data[$name] = $value;
    }

    if (isset($arr[32]) && isset($arr[33])) {
        $data['enterprise_nature'] = strval(!empty($arr[32]) ? $arr[32] : $arr[33]);
    }

    if (isset($data['ic_no'])) {
        $data['birthday'] = intval(substr($data['ic_no'], 6, 8));
    } else {
        $data['birthday'] = 0;
    }

    $json_str = json_encode($data, JSON_UNESCAPED_UNICODE);
    fwrite($fhOutput, $json_str . "\n");

    unset($row);
}

fclose($fh);
fclose($fhOutput);
echo "done!\n";
