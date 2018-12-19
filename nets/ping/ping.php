<?php
/**
 * tcping and icmp ping
 * 
 * tcping for windows:
 * download: https://elifulkerson.com/projects/tcping.php
 * 
 * tcping for linux:
 * download page: http://www.linuxco.de/tcping/tcping.html
 * make install:
 * 		wget https://sources.voidlinux.eu/tcping-1.3.5/tcping-1.3.5.tar.gz
 * 		tar zxvf tcping-1.3.5.tar.gz
 * 		cd tcping-1.3.5/
 * 		yum install gcc
 * 		gcc -o tcping tcping.c
 * 		cp tcping /usr/bin
 * 
 */
 


function current_os(){
    if (strcasecmp(PHP_OS, 'WINNT') === 0) {  
        //Windows NT
        return 'windows';
    }elseif (strcasecmp(PHP_OS, 'Linux') === 0) {  
        //Linux
        return 'linux';
    }
	return PHP_OS;
}

function tcping_check($ip, $port = '80'){
	if(current_os() == 'windows'){
		$lastout = exec('tcping.exe -n 1 -i 2 -p '.$port.' '.$ip, $output);
		
		if(preg_match('/Was unable to connect/', $lastout)){
			return false;
		}
		if(preg_match('/Could not/', $lastout)){
			//DNS: Could not find host - 22, aborting
			return false;
		}
		if(preg_match('/Average/', $lastout)){
			return true;
		}
	}elseif(current_os() == 'linux'){
		$lastout = exec('tcping -t 2 '. $ip .' '.$port, $output);
		if(preg_match('/timeout/', $lastout)){
			return false;
		}
		if(preg_match('/closed/', $lastout)){
			return false;
		}
		if(preg_match('/open/', $lastout)){
			return true;
		}
	}
	return false;
}


function icmp_ping($ip){
	if(current_os() == 'windows'){
		$lastout = exec('ping -n 1 '.$ip, $output);
		if(preg_match('/Average\s+=[^\d]+\d+ms/is', $lastout)){
			return true;
		}
	}elseif(current_os() == 'linux'){
		$lastout = exec("ping -c 1 {$ip}", $outcome, $status);
		if(preg_match('/min\/avg\/max\/mdev\s+=\s+[\d\.]+\/([\d\.]+)\/[\d\.]+\/[\d\.]+ ms/is', $lastout, $result)){
			return true;
		}
	}
	return false;
}

function output_json($msg, $status = 0, $datas = []){
	$json = [
		'status' => $status,
		'msg'    => $msg,
		'datas'  => $datas
	];
	echo json_encode($json);exit;
}

if(isset($_POST['ip'])){
	$ip = trim($_POST['ip']);
	if(!preg_match('/^\d+\.\d+\.\d+\.\d+$/is', $ip)){
		output_json('ip address valid!', 1, ['ip' => $ip]);
	}
	
	$result['service'] = '電信';
	$result['tcp'] = 200;
	$result['http'] = 200;
	$result['icmp'] = 200;
	
	if(!tcping_check($ip, 22)){
		$result['tcp'] = 500;
	}
	if(!tcping_check($ip, 80)){
		$result['http'] = 500;
	}
	if(!icmp_ping($ip)){
		$result['icmp'] = 500;
	}
	
	$result['ip'] = $ip;
	output_json('ok', 0, $result);
}
output_json('valid request!', 2);