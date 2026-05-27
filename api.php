<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { exit(0); }

// =================【数据库与微信公众号配置区】=================
$db_host = '127.0.0.1';
$db_name = '26xsc_niubiness_';
$db_user = '26xsc_niubiness_';
$db_pass = '5T6naCsejJXpnpSD';

$wx_appid = 'wx811ef2c7102e88b8';
$wx_secret = '8e068d35ebb4fd240c3a68c4e6666468';
// ========================================================

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    echo json_encode(["success" => false, "message" => "数据库连接失败"]);
    exit;
}
$conn->set_charset("utf8mb4");

$action = $_GET['action'] ?? '';

// 增加超时控制，防止网络请求无限挂起导致 PHP 假死
function curl_get($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5); 
    $res = curl_exec($ch);
    curl_close($ch);
    return $res;
}

// 微信JS-SDK签名函数
function get_wx_share_signature($appId, $appSecret, $url) {
    $token_file = __DIR__ . "/wx_access_token_cache.json";
    $ticket_file = __DIR__ . "/wx_jsapi_ticket_cache.json";
    
    $access_token = "";
    if (file_exists($token_file) && (time() - filemtime($token_file) < 7000)) {
        $token_data = json_decode(file_get_contents($token_file), true);
        $access_token = $token_data['access_token'] ?? '';
    }
    if (empty($access_token)) {
        $token_url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid={$appId}&secret={$appSecret}";
        $res = json_decode(curl_get($token_url), true);
        if (isset($res['access_token'])) {
            $access_token = $res['access_token'];
            file_put_contents($token_file, json_encode($res));
        }
    }

    $jsapi_ticket = "";
    if (!empty($access_token)) {
        if (file_exists($ticket_file) && (time() - filemtime($ticket_file) < 7000)) {
            $ticket_data = json_decode(file_get_contents($ticket_file), true);
            $jsapi_ticket = $ticket_data['ticket'] ?? '';
        }
        if (empty($jsapi_ticket)) {
            $ticket_url = "https://api.weixin.qq.com/cgi-bin/ticket/getticket?access_token={$access_token}&type=jsapi";
            $res = json_decode(curl_get($ticket_url), true);
            if (isset($res['ticket'])) {
                $jsapi_ticket = $res['ticket'];
                file_put_contents($ticket_file, json_encode($res));
            }
        }
    }

    $nonceStr = substr(md5(time() . mt_rand(1000, 9999)), 0, 16);
    $timestamp = time();
    $string = "jsapi_ticket={$jsapi_ticket}&noncestr={$nonceStr}&timestamp={$timestamp}&url={$url}";
    $signature = sha1($string);

    return [
        "appId" => $appId,
        "nonceStr" => $nonceStr,
        "timestamp" => $timestamp,
        "signature" => $signature
    ];
}

// 智能拆分并展开施教区缩写
function expand_community_name($name) {
    $pattern = '/^(.+?)([一二三四五六七八九十十一十二a-zA-Z0-9]+(?:[、，,和及][一二三四五六七八九十十一十二a-zA-Z0-9]+)+)(街区|期|区|栋|幢|号楼|号|组团)$/u';
    if (preg_match($pattern, $name, $matches)) {
        $base = $matches[1];
        $list_str = $matches[2];
        $suffix = $matches[3];
        $items = preg_split('/[、，,]|和|及/u', $list_str);
        $expanded = [];
        foreach ($items as $item) {
            $item = trim($item);
            if ($item !== '') {
                $expanded[] = $base . $item . $suffix;
            }
        }
        return $expanded;
    }
    return [];
}

// 极速无回溯定序序列包含检查算法
function sequence_contains($string, $query) {
    $chars = preg_split('//u', $query, -1, PREG_SPLIT_NO_EMPTY);
    $last_pos = -1;
    foreach ($chars as $char) {
        $pos = mb_strpos($string, $char, $last_pos + 1, 'utf-8');
        if ($pos === false) {
            return false;
        }
        $last_pos = $pos;
    }
    return true;
}

// 动态拉取行政区
if ($action === 'get_districts') {
    $result = $conn->query("SELECT DISTINCT district FROM public_school WHERE district != '' ORDER BY id ASC");
    $districts = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) { $districts[] = $row['district']; }
    }
    echo json_encode($districts);
    exit;
}

// 签发卡片分享配置
elseif ($action === 'get_wx_config') {
    $url = $_GET['url'] ?? '';
    if (empty($url)) { echo json_encode(["success" => false]); exit; }
    $url = urldecode($url);
    $config_package = get_wx_share_signature($wx_appid, $wx_secret, $url);
    echo json_encode($config_package);
    exit;
}

// 1. 模拟微信测试接口
elseif ($action === 'simulate_register') {
    $openid = $_GET['openid'] ?? '';
    $nickname = $_GET['nickname'] ?? '模拟家长';
    $avatar = $_GET['avatar'] ?? '';

    if (empty($openid)) { echo json_encode(["success" => false]); exit; }

    $time = time() * 1000;
    $date_str = date("m-d H:i", time() + 28800);

    $stmt = $conn->prepare("INSERT INTO leads (wx_openid, wx_nickname, wx_avatar, create_time, date_str) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE wx_nickname = ?, wx_avatar = ?");
    $stmt->bind_param("sssisss", $openid, $nickname, $avatar, $time, $date_str, $nickname, $avatar);
    $success = $stmt->execute();
    $stmt->close();

    echo json_encode(["success" => $success]);
    exit;
}

// 2. 微信登录授权
elseif ($action === 'wx_login') {
    $code = $_GET['code'] ?? '';
    if (empty($code)) { echo json_encode(["success" => false]); exit; }

    $token_url = "https://api.weixin.qq.com/sns/oauth2/access_token?appid={$wx_appid}&secret={$wx_secret}&code={$code}&grant_type=authorization_code";
    $resData = json_decode(curl_get($token_url), true);
    
    $openid = $resData['openid'] ?? '';
    $access_token = $resData['access_token'] ?? '';

    if (empty($openid) || empty($access_token)) { echo json_encode(["success" => false]); exit; }

    $info_url = "https://api.weixin.qq.com/sns/userinfo?access_token={$access_token}&openid={$openid}&lang=zh_CN";
    $userInfo = json_decode(curl_get($info_url), true);

    $nickname = $userInfo['nickname'] ?? '微信用户';
    $headimgurl = $userInfo['headimgurl'] ?? '';

    $stmt = $conn->prepare("SELECT * FROM leads WHERE wx_openid = ? LIMIT 1");
    $stmt->bind_param("s", $openid);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // 🔒 安全熔断：如果已经被拉黑，则拦截登录，直接向前端下发封杀阻断状态
    if ($user && $user['is_black'] == 1) {
        echo json_encode(["success" => true, "isBlackout" => true]);
        exit;
    }

    $time = time() * 1000;
    $date_str = date("m-d H:i", time() + 28800);

    if ($user) {
        $update_stmt = $conn->prepare("UPDATE leads SET wx_nickname = ?, wx_avatar = ? WHERE wx_openid = ?");
        $update_stmt->bind_param("sss", $nickname, $headimgurl, $openid);
        $update_stmt->execute();
        $update_stmt->close();

        $hasProfile = ($user['child_name'] !== '未填' && !empty($user['child_name']) && $user['child_name'] !== NULL);
        
        $hist_stmt = $conn->prepare("SELECT * FROM query_history WHERE wx_openid = ? ORDER BY id DESC LIMIT 1");
        $hist_stmt->bind_param("s", $openid);
        $hist_stmt->execute();
        $history = $hist_stmt->get_result()->fetch_assoc();
        $hist_stmt->close();
        
        $hasIntent = !empty($history);

        echo json_encode([
            "success" => true,
            "hasProfile" => $hasProfile,
            "hasIntent" => $hasIntent,
            "openid" => $openid,
            "nickname" => $nickname,
            "headimgurl" => $headimgurl,
            "profile" => [
                "childName" => $user['child_name'],
                "parentPhone" => $user['parent_phone'],
                "grade" => $user['grade'],
                "currentSchool" => $user['current_school'],
                "schoolType" => $history ? $history['school_type'] : '未填',
                "targetDistrict" => $history ? $history['target_district'] : '未填',
                "communityName" => $history ? $history['community_name'] : '未填',
                "assignedPublicSchool" => $history ? $history['assigned_public_school'] : '未填',
                "v1SchoolName" => $history ? $history['v1_school_name'] : '未填',
                "v1District" => $history ? $history['v1_district'] : '未填'
            ]
        ]);
    } else {
        $insert_stmt = $conn->prepare("INSERT INTO leads (wx_openid, wx_nickname, wx_avatar, create_time, date_str) VALUES (?, ?, ?, ?, ?)");
        $insert_stmt->bind_param("sssis", $openid, $nickname, $headimgurl, $time, $date_str);
        $insert_stmt->execute();
        $insert_stmt->close();

        echo json_encode([
            "success" => true,
            "hasProfile" => false,
            "hasIntent" => false,
            "openid" => $openid,
            "nickname" => $nickname,
            "headimgurl" => $headimgurl
        ]);
    }
}

// 3. 检查填报状态
elseif ($action === 'check_status') {
    $openid = $_GET['openid'] ?? '';
    if (empty($openid)) { echo json_encode(["success" => false, "hasProfile" => false, "hasIntent" => false]); exit; }

    $stmt = $conn->prepare("SELECT * FROM leads WHERE wx_openid = ? LIMIT 1");
    $stmt->bind_param("s", $openid);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // 🔒 安全熔断：异步检测到黑名单状态直接熔断拒绝数据下发
    if ($user && $user['is_black'] == 1) {
        echo json_encode(["success" => true, "isBlackout" => true]);
        exit;
    }

    if ($user) {
        $hasProfile = ($user['child_name'] !== '未填' && !empty($user['child_name']) && $user['child_name'] !== NULL);
        
        $stmt2 = $conn->prepare("SELECT * FROM query_history WHERE wx_openid = ? ORDER BY id DESC LIMIT 1");
        $stmt2->bind_param("s", $openid);
        $stmt2->execute();
        $history = $stmt2->get_result()->fetch_assoc();
        $stmt2->close();
        
        $hasIntent = !empty($history);
        
        echo json_encode([
            "success" => true,
            "hasProfile" => $hasProfile,
            "hasIntent" => $hasIntent,
            "profile" => [
                "childName" => $user['child_name'],
                "parentPhone" => $user['parent_phone'],
                "grade" => $user['grade'],
                "currentSchool" => $user['current_school'],
                "schoolType" => $history ? $history['school_type'] : '未填',
                "targetDistrict" => $history ? $history['target_district'] : '未填',
                "communityName" => $history ? $history['community_name'] : '未填',
                "assignedPublicSchool" => $history ? $history['assigned_public_school'] : '未填',
                "v1SchoolName" => $history ? $history['v1_school_name'] : '未填',
                "v1District" => $history ? $history['v1_district'] : '未填'
            ]
        ]);
    } else {
        echo json_encode(["success" => true, "hasProfile" => false, "hasIntent" => false]);
    }
}

// 4. 保存档案（🌟 升级为高防御 INSERT ... ON DUPLICATE KEY UPDATE 模式）
elseif ($action === 'save_profile' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    $openid = $data['openid'] ?? '';

    if (empty($openid)) {
        echo json_encode(["success" => false, "message" => "缺少唯一识别码"]);
        exit;
    }

    // 拦截写入
    $ck_stmt = $conn->prepare("SELECT is_black FROM leads WHERE wx_openid = ? LIMIT 1");
    $ck_stmt->bind_param("s", $openid);
    $ck_stmt->execute();
    $ck = $ck_stmt->get_result()->fetch_assoc();
    $ck_stmt->close();
    if($ck && $ck['is_black'] == 1) { echo json_encode(["success" => false, "message" => "系统检测到非合规网络访问环境"]); exit; }

    $childName = $data['childName'] ?? '未填';
    $parentPhone = $data['parentPhone'] ?? '未填';
    $grade = $data['grade'] ?? '未填';
    $currentSchool = $data['currentSchool'] ?? '未填';

    $time = time() * 1000;
    $date_str = date("m-d H:i", time() + 28800);

    // 🌟 强力保障：即使家长授权异常导致 leads 里没有 openid 行，在这里也能全自动强行 INSERT 写入入库，彻底消灭 leads 漏盘！
    $stmt = $conn->prepare("INSERT INTO leads (wx_openid, child_name, parent_phone, grade, current_school, create_time, date_str) VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE child_name = ?, parent_phone = ?, grade = ?, current_school = ?");
    $stmt->bind_param("sssssisssss", $openid, $childName, $parentPhone, $grade, $currentSchool, $time, $date_str, $childName, $parentPhone, $grade, $currentSchool);
    
    if($stmt->execute()) {
        echo json_encode(["success" => true]);
    } else {
        echo json_encode(["success" => false, "message" => "基础档案写入失败"]);
    }
    $stmt->close();
}

// 5. 保存意向
elseif ($action === 'save_intent' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    $openid = $data['openid'] ?? '';

    if (empty($openid)) {
        echo json_encode(["success" => false, "message" => "缺少唯一识别凭证"]);
        exit;
    }

    // 拦截写入
    $ck_stmt = $conn->prepare("SELECT is_black FROM leads WHERE wx_openid = ? LIMIT 1");
    $ck_stmt->bind_param("s", $openid);
    $ck_stmt->execute();
    $ck = $ck_stmt->get_result()->fetch_assoc();
    $ck_stmt->close();
    if($ck && $ck['is_black'] == 1) { echo json_encode(["success" => false, "message" => "系统检测到非合规网络访问环境"]); exit; }

    $schoolType = $data['schoolType'] ?? '公办';
    $targetDistrict = $data['targetDistrict'] ?? '未填';
    $communityName = $data['communityName'] ?? '未填';
    $assignedPublicSchool = $data['assignedPublicSchool'] ?? '未填';
    $v1_schoolName = $data['v1_schoolName'] ?? '未填';
    $v1_district = $data['v1_district'] ?? '未填';

    $time = time() * 1000;
    $date_str = date("m-d H:i", time() + 28800);

    $stmt = $conn->prepare("INSERT INTO query_history (wx_openid, school_type, target_district, community_name, assigned_public_school, v1_school_name, v1_district, create_time, date_str) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssssis", $openid, $schoolType, $targetDistrict, $communityName, $assignedPublicSchool, $v1_schoolName, $v1_district, $time, $date_str);
    
    if($stmt->execute()) {
        echo json_encode(["success" => true]);
    } else {
        echo json_encode(["success" => false, "message" => "决策历史存档阻塞"]);
    }
    $stmt->close();
}

// 6. 房产小区自动匹配
elseif ($action === 'match_school') {
    $community = trim($_GET['community'] ?? '');
    if (mb_strlen($community, 'utf-8') < 2) { echo json_encode([]); exit; }

    $query = "SELECT school_name, address, teaching_area, communities FROM public_school";
    $result = $conn->query($query);
    $matched_schools = [];

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $communities_str = $row['communities'] ?? '';
            $comm_list = preg_split('/[，,；;\n]/u', $communities_str);
            foreach ($comm_list as $comm_item) {
                $comm_item = trim($comm_item);
                if ($comm_item === '') continue;
                $expanded_names = expand_community_name($comm_item);
                if (empty($expanded_names)) {
                    $expanded_names = (mb_strpos($comm_item, '、') !== false) ? explode('、', $comm_item) : [$comm_item];
                }
                foreach ($expanded_names as $expanded_name) {
                    $expanded_name = trim($expanded_name);
                    if ($expanded_name === '') continue;
                    $score = 0;
                    if (mb_stripos($expanded_name, $community) !== false) { $score = 100; } 
                    elseif (sequence_contains($expanded_name, $community)) { $score = 50; }
                    if ($score > 0) {
                        $existing_index = -1;
                        foreach ($matched_schools as $idx => $school) {
                            if ($school['school_name'] === $row['school_name']) { $existing_index = $idx; break; }
                        }
                        if ($existing_index !== -1) {
                            if ($score > $matched_schools[$existing_index]['score']) { $matched_schools[$existing_index]['score'] = $score; }
                        } else {
                            $matched_schools[] = ["school_name" => $row['school_name'], "address" => $row['address'], "teaching_area" => $row['teaching_area'], "score" => $score];
                        }
                    }
                }
            }
        }
    }
    usort($matched_schools, function($a, $b) { return $b['score'] <=> $a['score']; });
    echo json_encode(array_slice($matched_schools, 0, 3));
    exit;
}

// 7. 联动学校加载
elseif ($action === 'get_schools') {
    $type = $_GET['type'] ?? 'public';
    $district = $_GET['district'] ?? '';
    if ($type === 'public') {
        $stmt = $conn->prepare("SELECT id, school_name FROM public_school WHERE district = ? ORDER BY school_name ASC");
        $stmt->bind_param("s", $district);
        $stmt->execute();
        $res = $stmt->get_result();
        $schools = [];
        while ($row = $res->fetch_assoc()) { $schools[] = $row; }
        $stmt->close();
        echo json_encode($schools);
    } else {
        $stmt = $conn->prepare("SELECT id, school_name FROM private_school ORDER BY school_name ASC");
        $stmt->execute();
        $res = $stmt->get_result();
        $schools = [];
        while ($row = $res->fetch_assoc()) { $schools[] = $row; }
        $stmt->close();
        echo json_encode($schools);
    }
}

// 8. 调取学校详情
elseif ($action === 'get_school_details') {
    $type = $_GET['type'] ?? 'public';
    $name = $_GET['name'] ?? '';
    $stmt = ($type === 'public') ? $conn->prepare("SELECT * FROM public_school WHERE school_name = ? LIMIT 1") : $conn->prepare("SELECT * FROM private_school WHERE school_name = ? LIMIT 1");
    $stmt->bind_param("s", $name);
    $stmt->execute();
    echo json_encode($stmt->get_result()->fetch_assoc());
    $stmt->close();
}

$conn->close();
?>