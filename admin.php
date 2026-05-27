<?php
session_start();
header('Content-Type: text/html; charset=utf-8');

// =================【配置区】=================
$db_host = '127.0.0.1';
$db_name = '26xsc_niubiness_';
$db_user = '26xsc_niubiness_';
$db_pass = '5T6naCsejJXpnpSD';
$admin_pass = 'xzxsc2026'; // 后台登录口令
// ============================================

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) { die("数据库连接失败"); }
$conn->set_charset("utf8mb4");

// 登录校验逻辑
if (isset($_GET['action']) && $_GET['action'] === 'login') {
    if (($_POST['password'] ?? '') === $admin_pass) {
        $_SESSION['admin_logged'] = true;
        header("Location: admin.php");
        exit;
    } else {
        echo "<script>alert('授权失败');history.back();</script>";
        exit;
    }
}
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    unset($_SESSION['admin_logged']);
    header("Location: admin.php");
    exit;
}

if (!($_SESSION['admin_logged'] ?? false)) {
?>
<!DOCTYPE html>
<html lang="zh-CN"><head><meta charset="UTF-8"><title>系统授权</title><script src="https://cdn.tailwindcss.com"></script></head>
<body class="bg-slate-950 flex items-center justify-center min-h-screen">
    <form action="admin.php?action=login" method="POST" class="bg-white p-8 rounded-3xl shadow-2xl w-full max-w-sm space-y-5">
        <h3 class="text-base font-black text-center text-slate-800">彭城小升初·数据中央枢纽</h3>
        <div>
            <label class="block text-xs font-bold text-slate-500 mb-2">安全管理口令</label>
            <input type="password" name="password" class="w-full border rounded-xl p-3 focus:outline-none focus:ring-2 focus:ring-indigo-500" required>
        </div>
        <button type="submit" class="w-full bg-indigo-600 text-white font-bold py-3.5 rounded-xl text-sm shadow-md">安全登录后台</button>
    </form>
</body></html>
<?php
    exit;
}

$sub_page = $_GET['page'] ?? 'leads';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action'])) {
    
    // 【全新功能：黑名单一键拉黑/解除封禁动作网关】
    if ($_GET['action'] === 'toggle_black') {
        $openid = $_POST['openid'] ?? '';
        $status = intval($_POST['status'] ?? 0);
        if (!empty($openid)) {
            $stmt = $conn->prepare("UPDATE leads SET is_black = ? WHERE wx_openid = ?");
            $stmt->bind_param("is", $status, $openid);
            $stmt->execute();
            $stmt->close();
        }
        header("Location: admin.php?page=leads");
        exit;
    }

    // 公办学校全新添加
    if ($_GET['action'] === 'add_public') {
        $district = $_POST['district'];
        $school_name = $_POST['school_name'];
        $address = $_POST['address'];
        $teaching_area = $_POST['teaching_area'];
        $communities = $_POST['communities'];
        $enrollment_2025 = intval($_POST['enrollment_2025']);
        $enrollment_2024 = intval($_POST['enrollment_2024']);
        $enrollment_2023 = intval($_POST['enrollment_2023']);
        $contact_phone = $_POST['contact_phone'];
        $special_class_note = $_POST['special_class_note'];
        $exam_2026_info = $_POST['exam_2026_info'];
        $exam_2025_info = $_POST['exam_2025_info'];
        $tuition_per_semester = $_POST['tuition_per_semester'];
        $remark = $_POST['remark'];
        
        $stmt = $conn->prepare("INSERT INTO public_school (district, school_name, address, teaching_area, communities, enrollment_2025, enrollment_2024, enrollment_2023, contact_phone, special_class_note, exam_2026_info, exam_2025_info, tuition_per_semester, remark) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssiiissssss", $district, $school_name, $address, $teaching_area, $communities, $enrollment_2025, $enrollment_2024, $enrollment_2023, $contact_phone, $special_class_note, $exam_2026_info, $exam_2025_info, $tuition_per_semester, $remark);
        $stmt->execute();
        $stmt->close();
        header("Location: admin.php?page=public");
        exit;
    }

    // 公办学校属性订正
    if ($_GET['action'] === 'update_public') {
        $id = intval($_POST['id']);
        $district = $_POST['district'];
        $school_name = $_POST['school_name'];
        $address = $_POST['address'];
        $teaching_area = $_POST['teaching_area'];
        $communities = $_POST['communities'];
        $enrollment_2025 = intval($_POST['enrollment_2025']);
        $enrollment_2024 = intval($_POST['enrollment_2024']);
        $enrollment_2023 = intval($_POST['enrollment_2023']);
        $contact_phone = $_POST['contact_phone'];
        $special_class_note = $_POST['special_class_note'];
        $exam_2026_info = $_POST['exam_2026_info'];
        $exam_2025_info = $_POST['exam_2025_info'];
        $tuition_per_semester = $_POST['tuition_per_semester'];
        $remark = $_POST['remark'];
        
        $stmt = $conn->prepare("UPDATE public_school SET district = ?, school_name = ?, address = ?, teaching_area = ?, communities = ?, enrollment_2025 = ?, enrollment_2024 = ?, enrollment_2023 = ?, contact_phone = ?, special_class_note = ?, exam_2026_info = ?, exam_2025_info = ?, tuition_per_semester = ?, remark = ? WHERE id = ?");
        $stmt->bind_param("sssssiiissssssi", $district, $school_name, $address, $teaching_area, $communities, $enrollment_2025, $enrollment_2024, $enrollment_2023, $contact_phone, $special_class_note, $exam_2026_info, $exam_2025_info, $tuition_per_semester, $remark, $id);
        $stmt->execute();
        $stmt->close();
        header("Location: admin.php?page=public");
        exit;
    }
    
    // 民办学校一键增补
    if ($_GET['action'] === 'add_private') {
        $district = $_POST['district'];
        $school_name = $_POST['school_name'];
        $lottery_method = $_POST['lottery_method'];
        $signup_2025 = intval($_POST['signup_2025']);
        $admit_2025 = intval($_POST['admit_2025']);
        $lottery_rate_2025 = $signup_2025 > 0 ? ($admit_2025 / $signup_2025) : 0.0000;
        $signup_2024 = intval($_POST['signup_2024']);
        $admit_2024 = intval($_POST['admit_2024']);
        $lottery_rate_2024 = $signup_2024 > 0 ? ($admit_2024 / $signup_2024) : 0.0000;
        $plan_2025 = intval($_POST['plan_2025']);
        $plan_2024 = intval($_POST['plan_2024']);
        $enrollment_2023 = intval($_POST['enrollment_2023']);
        $contact_phone = $_POST['contact_phone'];
        $direct_admission_note = $_POST['direct_admission_note'];
        $special_class_note = $_POST['special_class_note'];
        $exam_2026_info = $_POST['exam_2026_info'];
        $exam_2025_info = $_POST['exam_2025_info'];
        $tuition_per_semester = $_POST['tuition_per_semester'];
        $remark = $_POST['remark'];
        
        $stmt = $conn->prepare("INSERT INTO private_school (district, school_name, lottery_method, signup_2025, admit_2025, lottery_rate_2025, signup_2024, admit_2024, lottery_rate_2024, plan_2025, plan_2024, enrollment_2023, contact_phone, direct_admission_note, special_class_note, exam_2026_info, exam_2025_info, tuition_per_semester, remark) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssiididiiiisssssss", $district, $school_name, $lottery_method, $signup_2025, $admit_2025, $lottery_rate_2025, $signup_2024, $admit_2024, $lottery_rate_2024, $plan_2025, $plan_2024, $enrollment_2023, $contact_phone, $direct_admission_note, $special_class_note, $exam_2026_info, $exam_2025_info, $tuition_per_semester, $remark);
        $stmt->execute();
        $stmt->close();
        header("Location: admin.php?page=private");
        exit;
    }

    // 民办学校属性订正
    if ($_GET['action'] === 'update_private') {
        $id = intval($_POST['id']);
        $district = $_POST['district'];
        $school_name = $_POST['school_name'];
        $lottery_method = $_POST['lottery_method'];
        $signup_2025 = intval($_POST['signup_2025']);
        $admit_2025 = intval($_POST['admit_2025']);
        $lottery_rate_2025 = $signup_2025 > 0 ? ($admit_2025 / $signup_2025) : 0.0000;
        $signup_2024 = intval($_POST['signup_2024']);
        $admit_2024 = intval($_POST['admit_2024']);
        $lottery_rate_2024 = $signup_2024 > 0 ? ($admit_2024 / $signup_2024) : 0.0000;
        $plan_2025 = intval($_POST['plan_2025']);
        $plan_2024 = intval($_POST['plan_2024']);
        $enrollment_2023 = intval($_POST['enrollment_2023']);
        $contact_phone = $_POST['contact_phone'];
        $direct_admission_note = $_POST['direct_admission_note'];
        $special_class_note = $_POST['special_class_note'];
        $exam_2026_info = $_POST['exam_2026_info'];
        $exam_2025_info = $_POST['exam_2025_info'];
        $tuition_per_semester = $_POST['tuition_per_semester'];
        $remark = $_POST['remark'];
        
        $stmt = $conn->prepare("UPDATE private_school SET district = ?, school_name = ?, lottery_method = ?, signup_2025 = ?, admit_2025 = ?, lottery_rate_2025 = ?, signup_2024 = ?, admit_2024 = ?, lottery_rate_2024 = ?, plan_2025 = ?, plan_2024 = ?, enrollment_2023 = ?, contact_phone = ?, direct_admission_note = ?, special_class_note = ?, exam_2026_info = ?, exam_2025_info = ?, tuition_per_semester = ?, remark = ? WHERE id = ?");
        $stmt->bind_param("sssiididiiiisssssssi", $district, $school_name, $lottery_method, $signup_2025, $admit_2025, $lottery_rate_2025, $signup_2024, $admit_2024, $lottery_rate_2024, $plan_2025, $plan_2024, $enrollment_2023, $contact_phone, $direct_admission_note, $special_class_note, $exam_2026_info, $exam_2025_info, $tuition_per_semester, $remark, $id);
        $stmt->execute();
        $stmt->close();
        header("Location: admin.php?page=private");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>彭城小升初控制面板</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 min-h-screen flex text-slate-800">

    <div class="w-64 bg-slate-900 text-white flex flex-col justify-between shrink-0 p-6">
        <div class="space-y-8">
            <div class="border-b border-slate-800 pb-4 text-center">
                <h1 class="text-sm font-black tracking-wider text-indigo-400">彭城小升初·数据大脑</h1>
                <p class="text-[9px] text-slate-500 mt-1">学业规划公益矩阵服务端</p>
            </div>
            <nav class="space-y-2">
                <a href="admin.php?page=leads" class="block py-3 px-4 rounded-xl text-xs font-bold transition-colors <?php echo $sub_page==='leads'?'bg-indigo-600 text-white':'text-slate-400 hover:bg-slate-800';?>">👥 用户审计与拉黑中心</a>
                <a href="admin.php?page=public" class="block py-3 px-4 rounded-xl text-xs font-bold transition-colors <?php echo $sub_page==='public'?'bg-indigo-600 text-white':'text-slate-400 hover:bg-slate-800';?>">🏫 公办学区数据底盘矩阵</a>
                <a href="admin.php?page=private" class="block py-3 px-4 rounded-xl text-xs font-bold transition-colors <?php echo $sub_page==='private'?'bg-indigo-600 text-white':'text-slate-400 hover:bg-slate-800';?>">📊 民办派位大盘基数校准</a>
            </nav>
        </div>
        <a href="admin.php?action=logout" class="text-center text-xs text-slate-500 hover:text-red-400 border border-slate-800 py-2 rounded-xl transition-colors">退出管理控制台</a>
    </div>

    <div class="flex-1 p-8 overflow-y-auto">
        
        <?php if ($sub_page === 'leads'): ?>
        <div class="bg-white rounded-3xl p-6 shadow-sm border">
            <div class="mb-6 border-b pb-4 flex justify-between items-center">
                <div>
                    <h2 class="text-base font-bold text-slate-900">市区小升初学业诊断咨询流向与黑名单防御看板</h2>
                    <p class="text-xs text-slate-400 mt-0.5">多频分析防火墙：点击“一键拉黑同行”，该微信用户端将遭到全面数据熔断隔离保护。</p>
                </div>
                <div class="flex space-x-4 text-xs font-bold">
                    <span class="bg-slate-100 text-slate-600 px-3 py-1.5 rounded-xl border">累计总用户：<?php echo $conn->query("SELECT id FROM leads")->num_rows;?>人</span>
                    <span class="bg-rose-50 text-rose-700 border border-rose-100 px-3 py-1.5 rounded-xl">已封杀同行数：<?php echo $conn->query("SELECT id FROM leads WHERE is_black = 1")->num_rows;?>人</span>
                </div>
            </div>
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="bg-slate-50 text-slate-500 font-bold border-b">
                        <th class="p-3">最新诊断时间</th>
                        <th class="p-3">微信家长头像/昵称</th>
                        <th class="p-3">绑定留资手机</th>
                        <th class="p-3">测算流向</th>
                        <th class="p-3">检索的小区住宅房</th>
                        <th class="p-3 text-indigo-600">最终测试志愿</th>
                        <th class="p-3 text-center text-rose-600">防火墙安全控制</th>
                    </tr>
                </thead>
                <tbody class="divide-y text-slate-700">
                    <?php
                    // 联表获取最新数据，并加入 leads.is_black 判断状态
                    $res = $conn->query("SELECT h.*, l.wx_nickname, l.wx_avatar, l.parent_phone, l.child_name, l.current_school, l.is_black FROM query_history h LEFT JOIN leads l ON h.wx_openid = l.wx_openid ORDER BY h.id DESC");
                    while($row = $res->fetch_assoc()):
                    ?>
                    <tr class="hover:bg-slate-50 transition-colors <?php echo $row['is_black'] == 1 ? 'bg-rose-50/40 opacity-70' : '';?>">
                        <td class="p-3 text-slate-400"><?php echo $row['date_str'];?></td>
                        <td class="p-3 flex items-center space-x-2">
                            <img src="<?php echo $row['wx_avatar'];?>" class="w-6 h-6 rounded-full border">
                            <span class="font-bold <?php echo $row['is_black'] == 1 ? 'line-through text-slate-400' : '';?>"><?php echo $row['wx_nickname'];?></span>
                        </td>
                        <td class="p-3">
                            <div class="font-bold"><?php echo $row['parent_phone'];?></div>
                            <div class="text-[10px] text-slate-400"><?php echo $row['child_name'];?> | <?php echo $row['current_school'];?></div>
                        </td>
                        <td class="p-3">
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold <?php echo $row['school_type']==='民办'?'bg-rose-50 text-rose-700 border border-rose-100':'bg-emerald-50 text-emerald-700 border border-emerald-100';?>">
                                <?php echo $row['school_type'];?>
                            </span>
                        </td>
                        <td class="p-3 text-slate-500 text-[11px] font-mono"><?php echo $row['community_name'];?></td>
                        <td class="p-3 font-bold text-indigo-600"><?php echo $row['v1_school_name'];?></td>
                        
                        <td class="p-3 text-center">
                            <form action="admin.php?page=leads&action=toggle_black" method="POST" onsubmit="return confirm('确认要对该微信用户执行此项安全操作吗？');">
                                <input type="hidden" name="openid" value="<?php echo $row['wx_openid'];?>">
                                <?php if($row['is_black'] == 1): ?>
                                    <input type="hidden" name="status" value="0">
                                    <button type="submit" class="bg-emerald-600 text-white font-bold px-3 py-1 rounded-lg text-[10px] hover:bg-emerald-700 transition-colors shadow-sm">解封恢复访问</button>
                                <?php else: ?>
                                    <input type="hidden" name="status" value="1">
                                    <button type="submit" class="bg-rose-600 text-white font-bold px-3 py-1 rounded-lg text-[10px] hover:bg-rose-700 transition-colors shadow-sm">🛑 一键拉黑拦截</button>
                                <?php endif; ?>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile;?>
                </tbody>
            </table>
        </div>

        <?php elseif ($sub_page === 'public'): ?>
        <div class="space-y-6">
            <div class="bg-indigo-900 text-white rounded-3xl p-6 shadow-md space-y-4">
                <div>
                    <h2 class="text-base font-bold">➕ 新增公办初中学校入库通道</h2>
                    <p class="text-xs text-indigo-200 mt-0.5">在此处填写完全新公办初中的完整划片信息后，系统将自动下发至前端供给家长搜索匹配。</p>
                </div>
                <form action="admin.php?page=public&action=add_public" method="POST" class="space-y-4 text-xs text-slate-800">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div><label class="block font-bold text-indigo-200 mb-1">所属区 (例：泉山区)</label><input type="text" name="district" class="w-full border rounded p-2 bg-white font-bold" required></div>
                        <div><label class="block font-bold text-indigo-200 mb-1">学校正式全称</label><input type="text" name="school_name" class="w-full border rounded p-2 bg-white font-bold" required></div>
                        <div class="md:col-span-2"><label class="block font-bold text-indigo-200 mb-1">学校物理地址</label><input type="text" name="address" class="w-full border rounded p-2 bg-white"></div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div><label class="block font-bold text-indigo-200 mb-1">官方正式施教区范围文字表述</label><textarea name="teaching_area" rows="2" class="w-full border rounded p-2 bg-white resize-none leading-relaxed"></textarea></div>
                        <div><label class="block font-bold text-indigo-200 mb-1">系统快捷智能关联匹配的小区名称（请务必用顿号“、”或逗号隔开）</label><textarea name="communities" rows="2" class="w-full border rounded p-2 bg-white resize-none font-mono" placeholder="例：兴隆花园、万寨港宿舍、重型厂宿舍"></textarea></div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                        <div><label class="block font-bold text-indigo-200 mb-1">2025招生人数</label><input type="number" name="enrollment_2025" class="w-full border rounded p-2 bg-white font-bold text-indigo-600"></div>
                        <div><label class="block font-bold text-indigo-200 mb-1">2024招生人数</label><input type="number" name="enrollment_2024" class="w-full border rounded p-2 bg-white"></div>
                        <div><label class="block font-bold text-indigo-200 mb-1">2023招生人数</label><input type="number" name="enrollment_2023" class="w-full border rounded p-2 bg-white"></div>
                        <div><label class="block font-bold text-indigo-200 mb-1">招生咨询热线</label><input type="text" name="contact_phone" class="w-full border rounded p-2 bg-white"></div>
                        <div><label class="block font-bold text-indigo-200 mb-1">学费标准</label><input type="text" name="tuition_per_semester" value="义务教育免学费" class="w-full border rounded p-2 bg-white"></div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div><label class="block font-bold text-indigo-200 mb-1">特色班型设置说明</label><input type="text" name="special_class_note" class="w-full border rounded p-2 bg-white"></div>
                        <div><label class="block font-bold text-indigo-200 mb-1">2026中考动向趋势</label><input type="text" name="exam_2026_info" class="w-full border rounded p-2 bg-white"></div>
                        <div><label class="block font-bold text-indigo-200 mb-1">2025中考重点战绩</label><input type="text" name="exam_2025_info" class="w-full border rounded p-2 bg-white"></div>
                        <div><label class="block font-bold text-amber-300 mb-1">内部数据业务备忘录（含“热点”字样激活高危调剂算法）</label><input type="text" name="remark" class="w-full border rounded p-2 bg-white font-bold" placeholder="填写：热点学校"></div>
                    </div>
                    <div class="flex justify-end"><button type="submit" class="bg-emerald-500 text-white font-bold py-2.5 px-8 rounded-xl shadow hover:bg-emerald-600 transition-colors">🚀 确认新学校上架入库</button></div>
                </form>
            </div>

            <div class="bg-white rounded-3xl p-6 shadow-sm border space-y-6">
                <div>
                    <h2 class="text-base font-bold text-slate-900">已录入公办初中属性矩阵订正中心</h2>
                </div>
                <div class="space-y-6">
                    <?php
                    $res = $conn->query("SELECT * FROM public_school ORDER BY district DESC, id ASC");
                    while($school = $res->fetch_assoc()):
                    ?>
                    <form action="admin.php?page=public&action=update_public" method="POST" class="p-6 bg-slate-50 rounded-2xl border border-slate-200 space-y-4 text-xs">
                        <input type="hidden" name="id" value="<?php echo $school['id'];?>">
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div><label class="block font-bold text-slate-500 mb-1">所属行政区</label><input type="text" name="district" value="<?php echo $school['district'];?>" class="w-full border rounded p-2 bg-white font-bold"></div>
                            <div><label class="block font-bold text-slate-500 mb-1">学校正式全名</label><input type="text" name="school_name" value="<?php echo $school['school_name'];?>" class="w-full border rounded p-2 bg-white font-bold text-slate-800"></div>
                            <div class="md:col-span-2"><label class="block font-bold text-slate-500 mb-1">学校物理地址</label><input type="text" name="address" value="<?php echo $school['address'];?>" class="w-full border rounded p-2 bg-white"></div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div><label class="block font-bold text-slate-500 mb-1">官方正规划片施教区段范围描述 (teaching_area)</label><textarea name="teaching_area" rows="3" class="w-full border rounded p-2 bg-white resize-none leading-relaxed"><?php echo $school['teaching_area'];?></textarea></div>
                            <div><label class="block font-bold text-slate-500 mb-1">系统用模糊智能关联匹配的小区清单 (communities，用逗号隔开)</label><textarea name="communities" rows="3" class="w-full border rounded p-2 bg-white resize-none leading-relaxed font-mono"><?php echo $school['communities'];?></textarea></div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                            <div><label class="block font-bold text-slate-500 mb-1">2025招生规模</label><input type="number" name="enrollment_2025" value="<?php echo $school['enrollment_2025'];?>" class="w-full border rounded p-2 bg-white font-bold text-indigo-600"></div>
                            <div><label class="block font-bold text-slate-500 mb-1">2024招生规模</label><input type="number" name="enrollment_2024" value="<?php echo $school['enrollment_2024'];?>" class="w-full border rounded p-2 bg-white"></div>
                            <div><label class="block font-bold text-slate-500 mb-1">2023招生规模</label><input type="number" name="enrollment_2023" value="<?php echo $school['enrollment_2023'];?>" class="w-full border rounded p-2 bg-white"></div>
                            <div><label class="block font-bold text-slate-500 mb-1">招生办电话</label><input type="text" name="contact_phone" value="<?php echo $school['contact_phone'];?>" class="w-full border rounded p-2 bg-white"></div>
                            <div><label class="block font-bold text-slate-500 mb-1">每学期基础学费标准</label><input type="text" name="tuition_per_semester" value="<?php echo $school['tuition_per_semester'];?>" class="w-full border rounded p-2 bg-white"></div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div><label class="block font-bold text-slate-500 mb-1">特色班型备注</label><input type="text" name="special_class_note" value="<?php echo $school['special_class_note'];?>" class="w-full border rounded p-2 bg-white"></div>
                            <div><label class="block font-bold text-slate-500 mb-1">2026中考风向诊断</label><input type="text" name="exam_2026_info" value="<?php echo $school['exam_2026_info'];?>" class="w-full border rounded p-2 bg-white"></div>
                            <div><label class="block font-bold text-slate-500 mb-1">2025中考战绩披露</label><input type="text" name="exam_2025_info" value="<?php echo $school['exam_2025_info'];?>" class="w-full border rounded p-2 bg-white"></div>
                            <div><label class="block font-bold text-amber-700 mb-1">内部业务备忘录（含“热点”激活退回溢出机制）</label><input type="text" name="remark" value="<?php echo $school['remark'];?>" class="w-full border border-amber-200 rounded p-2 bg-amber-50/50 font-bold text-slate-700"></div>
                        </div>
                        <div class="flex justify-end"><button type="submit" class="bg-slate-900 text-white font-bold py-2 px-5 rounded-xl hover:bg-indigo-600 transition-colors">更正并应用该校数据</button></div>
                    </form>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>

        <?php elseif ($sub_page === 'private'): ?>
        <div class="space-y-6">
            <div class="bg-rose-900 text-white rounded-3xl p-6 shadow-md space-y-4">
                <div>
                    <h2 class="text-base font-bold">➕ 新增民办初中学校入库通道</h2>
                    <p class="text-xs text-rose-200 mt-0.5">在此处新增完民办初中后，后台中签摇号率会通过填入的报名/派位人数自动实时测算。</p>
                </div>
                <form action="admin.php?page=private&action=add_private" method="POST" class="space-y-4 text-xs text-slate-800">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div><label class="block font-bold text-rose-200 mb-1">多级联动归属区标签（可为空）</label><input type="text" name="district" class="w-full border rounded p-2 bg-white"></div>
                        <div><label class="block font-bold text-rose-200 mb-1">民办学校正式名称</label><input type="text" name="school_name" class="w-full border rounded p-2 bg-white font-bold" required></div>
                        <div><label class="block font-bold text-rose-200 mb-1">电脑派位派选形式标签</label><input type="text" name="lottery_method" class="w-full border rounded p-2 bg-white" placeholder="例：第一阶段报名"></div>
                    </div>
                    <div class="bg-white p-4 rounded-xl border space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div><label class="block font-bold text-rose-950 mb-1">【2025】核定招生计划数</label><input type="number" name="plan_2025" class="w-full border rounded p-1.5 bg-slate-50"></div>
                            <div><label class="block font-bold text-rose-950 mb-1">【2025】审核报名总人数</label><input type="number" name="signup_2025" class="w-full border rounded p-1.5 bg-slate-50 font-bold text-indigo-600"></div>
                            <div><label class="block font-bold text-rose-950 mb-1">【2025】派位中签实际录取数</label><input type="number" name="admit_2025" class="w-full border rounded p-1.5 bg-slate-50 font-bold text-emerald-600"></div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 border-t pt-3">
                            <div><label class="block font-bold text-slate-500 mb-1">【2024】核定招生计划</label><input type="number" name="plan_2024" class="w-full border rounded p-1.5 bg-slate-50"></div>
                            <div><label class="block font-bold text-slate-500 mb-1">【2024】审核报名人数</label><input type="number" name="signup_2024" class="w-full border rounded p-1.5 bg-slate-50"></div>
                            <div><label class="block font-bold text-slate-500 mb-1">【2024】派位录取总数</label><input type="number" name="admit_2024" class="w-full border rounded p-1.5 bg-slate-50"></div>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                        <div><label class="block font-bold text-rose-200 mb-1">2023年招生数参考</label><input type="number" name="enrollment_2023" class="w-full border rounded p-2 bg-white"></div>
                        <div><label class="block font-bold text-rose-200 mb-1">对外官方咨询热线</label><input type="text" name="contact_phone" class="w-full border rounded p-2 bg-white"></div>
                        <div class="md:col-span-2"><label class="block font-bold text-rose-200 mb-1">内部直升/特殊招生方案机制说明</label><input type="text" name="direct_admission_note" class="w-full border rounded p-2 bg-white"></div>
                        <div><label class="block font-bold text-rose-200 mb-1">每学期学费标准费用 (元)</label><input type="text" name="tuition_per_semester" class="w-full border border-rose-300 rounded p-2 bg-rose-50/50 font-bold text-rose-700" placeholder="例：12000元"></div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div><label class="block font-bold text-rose-200 mb-1">特色办型班级描述</label><input type="text" name="special_class_note" class="w-full border rounded p-2 bg-white"></div>
                        <div><label class="block font-bold text-rose-200 mb-1">2026中考动向趋势</label><input type="text" name="exam_2026_info" class="w-full border rounded p-2 bg-white"></div>
                        <div><label class="block font-bold text-rose-200 mb-1">2025中考重点率榜单</label><input type="text" name="exam_2025_info" class="w-full border rounded p-2 bg-white"></div>
                        <div><label class="block font-bold text-rose-200 mb-1">附加独立备注说明 (remark)</label><input type="text" name="remark" class="w-full border rounded p-2 bg-white"></div>
                    </div>
                    <div class="flex justify-end"><button type="submit" class="bg-emerald-500 text-white font-bold py-2.5 px-8 rounded-xl shadow hover:bg-emerald-600 transition-colors">🚀 确认新民办校上架入库</button></div>
                </form>
            </div>

            <div class="bg-white rounded-3xl p-6 shadow-sm border space-y-6">
                <div>
                    <h2 class="text-base font-bold text-slate-900">已录入民办初中阳光招生数据矩阵维护</h2>
                </div>
                <div class="space-y-6">
                    <?php
                    $res = $conn->query("SELECT * FROM private_school ORDER BY id ASC");
                    while($school = $res->fetch_assoc()):
                        $rate25 = $school['signup_2025'] > 0 ? round(($school['admit_2025'] / $school['signup_2025']) * 100, 1) : 0;
                        $rate24 = $school['signup_2024'] > 0 ? round(($school['admit_2024'] / $school['signup_2024']) * 100, 1) : 0;
                    ?>
                    <form action="admin.php?page=private&action=update_private" method="POST" class="p-6 bg-slate-50 rounded-2xl border border-slate-200 space-y-4 text-xs">
                        <input type="hidden" name="id" value="<?php echo $school['id'];?>">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div><label class="block font-bold text-slate-500 mb-1">区域划分联动标签</label><input type="text" name="district" value="<?php echo $school['district'];?>" class="w-full border rounded p-2 bg-white"></div>
                            <div><label class="block font-bold text-slate-500 mb-1">民办学校全称</label><input type="text" name="school_name" value="<?php echo $school['school_name'];?>" class="w-full border rounded p-2 bg-white font-bold text-slate-800"></div>
                            <div><label class="block font-bold text-slate-500 mb-1">派位摇号录取分类形式</label><input type="text" name="lottery_method" value="<?php echo $school['lottery_method'];?>" class="w-full border rounded p-2 bg-white"></div>
                        </div>
                        <div class="bg-white p-4 rounded-xl border space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                                <div><label class="block font-bold text-indigo-950 mb-1">【2025】核定招生计划数</label><input type="number" name="plan_2025" value="<?php echo $school['plan_2025'];?>" class="w-full border rounded p-1.5 bg-slate-50"></div>
                                <div><label class="block font-bold text-indigo-950 mb-1">【2025】审核报名总人数</label><input type="number" name="signup_2025" value="<?php echo $school['signup_2025'];?>" class="w-full border rounded p-1.5 bg-slate-50 font-bold text-indigo-600"></div>
                                <div><label class="block font-bold text-indigo-950 mb-1">【2025】派位中签实际录取数</label><input type="number" name="admit_2025" value="<?php echo $school['admit_2025'];?>" class="w-full border rounded p-1.5 bg-slate-50 font-bold text-emerald-600"></div>
                                <div class="flex flex-col justify-center"><div class="text-slate-400 font-bold">25折合公式录取率</div><div class="text-xs font-black text-slate-800 mt-1"><?php echo $rate25 > 100 ? 100 : $rate25;?> %</div></div>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 border-t pt-3">
                                <div><label class="block font-bold text-slate-500 mb-1">【2024】核定招生计划</label><input type="number" name="plan_2024" value="<?php echo $school['plan_2024'];?>" class="w-full border rounded p-1.5 bg-slate-50"></div>
                                <div><label class="block font-bold text-slate-500 mb-1">【2024】审核报名人数</label><input type="number" name="signup_2024" value="<?php echo $school['signup_2024'];?>" class="w-full border rounded p-1.5 bg-slate-50"></div>
                                <div><label class="block font-bold text-slate-500 mb-1">【2024】派位录取总数</label><input type="number" name="admit_2024" value="<?php echo $school['admit_2024'];?>" class="w-full border rounded p-1.5 bg-slate-50"></div>
                                <div class="flex flex-col justify-center"><div class="text-slate-400 font-bold">24历史参考概率</div><div class="text-xs font-bold text-slate-500 mt-1"><?php echo $rate24 > 100 ? 100 : $rate24;?> %</div></div>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                            <div><label class="block font-bold text-slate-500 mb-1">2023年总招生人数</label><input type="number" name="enrollment_2023" value="<?php echo $school['enrollment_2023'];?>" class="w-full border rounded p-2 bg-white"></div>
                            <div><label class="block font-bold text-slate-500 mb-1">对外公开联络热线</label><input type="text" name="contact_phone" value="<?php echo $school['contact_phone'];?>" class="w-full border rounded p-2 bg-white"></div>
                            <div class="md:col-span-2"><label class="block font-bold text-slate-500 mb-1">内部直升或特色贯通方案备注</label><input type="text" name="direct_admission_note" value="<?php echo $school['direct_admission_note'];?>" class="w-full border rounded p-2 bg-white"></div>
                            <div><label class="block font-bold text-rose-700 mb-1">每期学费成本标准 (元)</label><input type="text" name="tuition_per_semester" value="<?php echo $school['tuition_per_semester'];?>" class="w-full border border-rose-200 rounded p-2 bg-rose-50/50 font-bold text-rose-700"></div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div><label class="block font-bold text-slate-500 mb-1">特色办学班型设置</label><input type="text" name="special_class_note" value="<?php echo $school['special_class_note'];?>" class="w-full border rounded p-2 bg-white"></div>
                            <div><label class="block font-bold text-slate-500 mb-1">2026中考趋势推演</label><input type="text" name="exam_2026_info" value="<?php echo $school['exam_2026_info'];?>" class="w-full border rounded p-2 bg-white"></div>
                            <div><label class="block font-bold text-slate-500 mb-1">2025中考重点率榜单摘要</label><input type="text" name="exam_2025_info" value="<?php echo $school['exam_2025_info'];?>" class="w-full border rounded p-2 bg-white"></div>
                            <div><label class="block font-bold text-slate-500 mb-1">附加备忘 (remark)</label><input type="text" name="remark" value="<?php echo $school['remark'];?>" class="w-full border rounded p-2 bg-white"></div>
                        </div>
                        <div class="flex justify-end"><button type="submit" class="bg-slate-900 text-white font-bold py-2 px-5 rounded-xl hover:bg-rose-600 transition-colors">更正并应用该校数据</button></div>
                    </form>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div>
</body>
</html>