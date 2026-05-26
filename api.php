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

// 学校数据维护后台鉴权
function is_admin_authed() {
    $pwd = $_GET['pwd'] ?? ($_POST['pwd'] ?? '');
    if ($pwd === '') {
        $raw = file_get_contents('php://input');
        if ($raw) {
            $json = json_decode($raw, true);
            if (is_array($json) && isset($json['pwd'])) $pwd = $json['pwd'];
        }
    }
    return $pwd === 'xzxsc2026';
}


// 增加超时控制，防止网络请求无限挂起导致 PHP 假死
function curl_get($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5); // 限制微信接口最大响应时间为 5 秒
    $res = curl_exec($ch);
    curl_close($ch);
    return $res;
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

// 极速无回溯定序序列包含检查算法（完全替代正则，彻底杜绝回溯挂起导致的超时）
function sequence_contains($string, $query) {
    $chars = preg_split('//u', $query, -1, PREG_SPLIT_NO_EMPTY);
    $last_pos = -1;
    // 使用滑动窗口逐字定位，性能开销趋近于 0 毫秒
    foreach ($chars as $char) {
        $pos = mb_strpos($string, $char, $last_pos + 1, 'utf-8');
        if ($pos === false) {
            return false;
        }
        $last_pos = $pos;
    }
    return true;
}

// 1. 模拟微信测试接口
if ($action === 'simulate_register') {
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

    $time = time() * 1000;
    $date_str = date("m-d H:i", time() + 28800);

    if ($user) {
        $update_stmt = $conn->prepare("UPDATE leads SET wx_nickname = ?, wx_avatar = ? WHERE wx_openid = ?");
        $update_stmt->bind_param("sss", $nickname, $headimgurl, $openid);
        $update_stmt->execute();
        $update_stmt->close();

        $hasProfile = ($user['child_name'] !== '未填' && !empty($user['child_name']));
        $hasIntent = ($user['assigned_public_school'] !== '未填' && !empty($user['assigned_public_school']));

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
                "schoolType" => $user['school_type'],
                "targetDistrict" => $user['target_district'],
                "communityName" => $user['community_name'],
                "assignedPublicSchool" => $user['assigned_public_school'],
                "v1SchoolName" => $user['v1_school_name'],
                "v1District" => $user['v1_district']
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

    if ($user) {
        $hasProfile = ($user['child_name'] !== '未填' && !empty($user['child_name']));
        $hasIntent = ($user['assigned_public_school'] !== '未填' && !empty($user['assigned_public_school']));
        echo json_encode([
            "success" => true,
            "hasProfile" => $hasProfile,
            "hasIntent" => $hasIntent,
            "profile" => [
                "childName" => $user['child_name'],
                "parentPhone" => $user['parent_phone'],
                "grade" => $user['grade'],
                "currentSchool" => $user['current_school'],
                "schoolType" => $user['school_type'],
                "targetDistrict" => $user['target_district'],
                "communityName" => $user['community_name'],
                "assignedPublicSchool" => $user['assigned_public_school'],
                "v1SchoolName" => $user['v1_school_name'],
                "v1District" => $user['v1_district']
            ]
        ]);
    } else {
        echo json_encode(["success" => true, "hasProfile" => false, "hasIntent" => false]);
    }
}

// 4. 保存档案
elseif ($action === 'save_profile' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    $openid = $data['openid'] ?? '';
    $childName = $data['childName'] ?? '未填';
    $parentPhone = $data['parentPhone'] ?? '未填';
    $grade = $data['grade'] ?? '未填';
    $currentSchool = $data['currentSchool'] ?? '未填';

    $stmt = $conn->prepare("UPDATE leads SET child_name = ?, parent_phone = ?, grade = ?, current_school = ? WHERE wx_openid = ?");
    $stmt->bind_param("sssss", $childName, $parentPhone, $grade, $currentSchool, $openid);
    
    if ($stmt->execute()) {
        echo json_encode(["success" => true]);
    } else {
        echo json_encode(["success" => false, "message" => "基础建档保存错误"]);
    }
    $stmt->close();
}

// 5. 保存意向
elseif ($action === 'save_intent' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    $openid = $data['openid'] ?? '';
    $schoolType = $data['schoolType'] ?? '公办';
    $targetDistrict = $data['targetDistrict'] ?? '未填';
    $communityName = $data['communityName'] ?? '未填';
    $assignedPublicSchool = $data['assignedPublicSchool'] ?? '未填';
    $v1_schoolName = $data['v1_schoolName'] ?? '未填';
    $v1_district = $data['v1_district'] ?? '未填';

    $stmt = $conn->prepare("UPDATE leads SET school_type = ?, target_district = ?, community_name = ?, assigned_public_school = ?, v1_school_name = ?, v1_district = ? WHERE wx_openid = ?");
    $stmt->bind_param("sssssss", $schoolType, $targetDistrict, $communityName, $assignedPublicSchool, $v1_schoolName, $v1_district, $openid);
    
    if ($stmt->execute()) {
        echo json_encode(["success" => true]);
    } else {
        echo json_encode(["success" => false, "message" => "升学决策保存错误"]);
    }
    $stmt->close();
}

// 6. 房产小区自动匹配对口初中 (支持多候选项返回与智能加权排序，已修复大分隔符提前切断缩写链的冲突)
elseif ($action === 'match_school') {
    $community = trim($_GET['community'] ?? '');
    if (mb_strlen($community, 'utf-8') < 2) { echo json_encode([]); exit; }

    // 载入全部公办学校的施教区和小区列表进行高阶内参比对
    $query = "SELECT school_name, address, teaching_area, communities FROM public_school";
    $result = $conn->query($query);
    $matched_schools = [];

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $communities_str = $row['communities'] ?? '';
            // 切分各个并列小区，不能在这里切断顿号，防止阻断 expand
            $comm_list = preg_split('/[，,；;\n]/u', $communities_str);
            
            foreach ($comm_list as $comm_item) {
                $comm_item = trim($comm_item);
                if ($comm_item === '') continue;

                $expanded_names = expand_community_name($comm_item);
                
                if (empty($expanded_names)) {
                    if (mb_strpos($comm_item, '、') !== false) {
                        $expanded_names = explode('、', $comm_item);
                    } else {
                        $expanded_names = [$comm_item];
                    }
                }

                foreach ($expanded_names as $expanded_name) {
                    $expanded_name = trim($expanded_name);
                    if ($expanded_name === '') continue;

                    $score = 0;
                    if (mb_stripos($expanded_name, $community) !== false) {
                        $score = 100;
                    } elseif (sequence_contains($expanded_name, $community)) {
                        $score = 50;
                    }

                    if ($score > 0) {
                        $existing_index = -1;
                        foreach ($matched_schools as $idx => $school) {
                            if ($school['school_name'] === $row['school_name']) {
                                $existing_index = $idx;
                                break;
                            }
                        }

                        if ($existing_index !== -1) {
                            if ($score > $matched_schools[$existing_index]['score']) {
                                $matched_schools[$existing_index]['score'] = $score;
                            }
                        } else {
                            $matched_schools[] = [
                                "school_name" => $row['school_name'],
                                "address" => $row['address'],
                                "teaching_area" => $row['teaching_area'],
                                "score" => $score
                            ];
                        }
                    }
                }
            }
        }
    }

    usort($matched_schools, function($a, $b) {
        return $b['score'] <=> $a['score'];
    });

    $matched_schools = array_slice($matched_schools, 0, 3);
    echo json_encode($matched_schools);
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

// 8. 调取学校底表详情（全字段透传前端展示）
elseif ($action === 'get_school_details') {
    $type = $_GET['type'] ?? 'public';
    $name = $_GET['name'] ?? '';

    if ($type === 'public') {
        $stmt = $conn->prepare("SELECT * FROM public_school WHERE school_name = ? LIMIT 1");
    } else {
        $stmt = $conn->prepare("SELECT * FROM private_school WHERE school_name = ? LIMIT 1");
    }
    $stmt->bind_param("s", $name);
    $stmt->execute();
    echo json_encode($stmt->get_result()->fetch_assoc());
    $stmt->close();
}

// 9. 看板列表
elseif ($action === 'list') {
    $result = $conn->query("SELECT * FROM leads ORDER BY id DESC");
    $list = [];
    while ($row = $result->fetch_assoc()) {
        $list[] = [
            "dateStr" => $row['date_str'] ?? '', 
            "wx_nickname" => $row['wx_nickname'] ?? '未授权',
            "wx_avatar" => $row['wx_avatar'] ?? '',
            "childName" => $row['child_name'] ?? '未填', 
            "parentPhone" => $row['parent_phone'] ?? '未填', 
            "schoolType" => $row['school_type'] ?? '未填', 
            "assignedPublicSchool" => $row['assigned_public_school'] ?? '未填', 
            "v1_schoolName" => $row['v1_school_name'] ?? '未填'
        ];
    }
    echo json_encode($list);
}


// 10. 学校后台列表（公办/民办）
elseif ($action === 'school_admin_list') {
    if (!is_admin_authed()) {
        echo json_encode(["success"=>false,"message"=>"未授权"]);
        exit;
    }
    $type = $_GET['type'] ?? 'public';
    $kw = trim($_GET['kw'] ?? '');
    $table = $type === 'private' ? 'private_school' : 'public_school';

    if ($kw !== '') {
        $like = "%{$kw}%";
        $stmt = $conn->prepare("SELECT id, school_name, enrollment_2025, signup_2025, admit_2025, contact_phone, remark FROM {$table} WHERE school_name LIKE ? ORDER BY school_name ASC LIMIT 300");
        $stmt->bind_param("s", $like);
    } else {
        $stmt = $conn->prepare("SELECT id, school_name, enrollment_2025, signup_2025, admit_2025, contact_phone, remark FROM {$table} ORDER BY school_name ASC LIMIT 300");
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($r = $res->fetch_assoc()) { $rows[] = $r; }
    $stmt->close();
    echo json_encode(["success"=>true, "rows"=>$rows]);
}

// 11. 学校后台保存（公办/民办）
elseif ($action === 'school_admin_update') {
    if (!is_admin_authed()) {
        echo json_encode(["success"=>false,"message"=>"未授权"]);
        exit;
    }
    $type = $_GET['type'] ?? 'public';
    $table = $type === 'private' ? 'private_school' : 'public_school';

    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) { echo json_encode(["success"=>false,"message"=>"参数错误"]); exit; }

    $id = intval($data['id'] ?? 0);
    if ($id <= 0) { echo json_encode(["success"=>false,"message"=>"ID无效"]); exit; }

    $enrollment_2025 = $data['enrollment_2025'] ?? null;
    $signup_2025 = $data['signup_2025'] ?? null;
    $admit_2025 = $data['admit_2025'] ?? null;
    $contact_phone = $data['contact_phone'] ?? '';
    $remark = $data['remark'] ?? '';

    $stmt = $conn->prepare("UPDATE {$table} SET enrollment_2025 = ?, signup_2025 = ?, admit_2025 = ?, contact_phone = ?, remark = ? WHERE id = ? LIMIT 1");
    $stmt->bind_param("iiissi", $enrollment_2025, $signup_2025, $admit_2025, $contact_phone, $remark, $id);
    $ok = $stmt->execute();
    $stmt->close();

    echo json_encode(["success"=>(bool)$ok]);
}


$conn->close();
?>