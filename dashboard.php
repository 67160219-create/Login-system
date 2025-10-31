<?php
// dashboard.php
// 1. ดึงไฟล์ config และเชื่อมต่อฐานข้อมูล (config_mysqli.php จัดการเรื่อง mysqli_report และการเชื่อมต่อแล้ว)
require_once 'config_mysqli.php'; 
// Note: $mysqli object is now available, or the script exited on error.

// ข้อมูลเริ่มต้น
$data = [
    'monthly' => [],
    'category' => [],
    'region' => [],
    'topProducts' => [],
    'payment' => [],
    'hourly' => [],
    'newReturning' => [],
    'kpi' => ['sales_30d'=>0,'qty_30d'=>0,'buyers_30d'=>0],
    'error' => null
];

try {
    function q($db, $sql) {
        $res = $db->query($sql);
        // เพิ่มการตรวจสอบผลลัพธ์เพื่อป้องกัน fetch_all() จากค่า null
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }

    // 2. ดึงข้อมูลสำหรับแผนภูมิต่างๆ (ใช้ View ที่สร้างขึ้นใหม่และที่มีอยู่แล้ว)
    $data['monthly'] = q($mysqli, "SELECT ym, net_sales FROM v_monthly_sales");
    $data['category'] = q($mysqli, "SELECT category, net_sales FROM v_sales_by_category");
    $data['region'] = q($mysqli, "SELECT region, net_sales FROM v_sales_by_region");
    $data['topProducts'] = q($mysqli, "SELECT product_name, qty_sold FROM v_top_products");

    // ===== START: ลบโค้ดทดสอบ =====
    // ลบ Dummy Data (ข้อมูลทดสอบ) ออก
    // เพราะเรายืนยันแล้วว่า v_top_products มีข้อมูลจริง
    // ===== END: ลบโค้ดทดสอบ =====

    $data['payment'] = q($mysqli, "SELECT payment_method, net_sales FROM v_payment_share");
    $data['hourly'] = q($mysqli, "SELECT hour_of_day, net_sales FROM v_hourly_sales");
    $data['newReturning'] = q($mysqli, "SELECT date_key, new_customer_sales, returning_sales FROM v_new_vs_returning ORDER BY date_key");

    // 3. ดึงข้อมูล KPI 30 วัน
    $kpi = q($mysqli, "
        SELECT SUM(net_amount) sales_30d, SUM(quantity) qty_30d, COUNT(DISTINCT customer_id) buyers_30d
        FROM fact_sales
        WHERE date_key >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
    ");
    if ($kpi && !empty($kpi)) $data['kpi'] = $kpi[0];

    // ===== START: เพิ่ม KPI Targets และคำนวณ % =====
    $data['kpi_targets'] = [
        'sales_30d' => 1000000, // สมมติเป้าหมายยอดขาย 1 ล้านบาท
        'qty_30d' => 10000,     // สมมติเป้าหมายจำนวนชิ้น 1 หมื่นชิ้น
        'buyers_30d' => 500      // สมมติเป้าหมายผู้ซื้อ 500 คน
    ];
    // คำนวณ % สำหรับ Doughnut Chart
    $data['kpi_pct'] = [
        'sales' => min(100, ($data['kpi']['sales_30d'] / $data['kpi_targets']['sales_30d']) * 100),
        'qty' => min(100, ($data['kpi']['qty_30d'] / $data['kpi_targets']['qty_30d']) * 100),
        'buyers' => min(100, ($data['kpi']['buyers_30d'] / $data['kpi_targets']['buyers_30d']) * 100),
    ];
    // ===== END: เพิ่ม KPI Targets และคำนวณ % =====

} catch (Exception $e) {
    // จัดการข้อผิดพลาดในการคิวรี่ฐานข้อมูล
    $data['error'] = 'Database Query Error: ' . $e->getMessage();
}

// 4. Function สำหรับจัดรูปแบบตัวเลข (Number Format)
function nf($n){ return number_format((float)$n,2); }
?>
<!doctype html>
<html lang="th" data-bs-theme="light"> <head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Retail DW — Cute Pastel Mint Dashboard</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=K2D:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<style>
/* CSS ปรับปรุง: ธีม "พาสเทลมิ้นต์" (Pastel Mint Theme) */
body {
    /* ===== START: แก้ไขสีพื้นหลัง (Pastel Mint) ===== */
    /* พื้นหลังสีมิ้นต์อ่อนๆ ไล่สี */
    background: linear-gradient(to bottom right, #f0fff4, #f5fffa); /* Honeydew to MintCream */
    /* ===== END: แก้ไขสีพื้นหลัง ===== */
    color: #004d40; /* เปลี่ยนสีตัวอักษรเป็นสีเขียวเข้ม (Dark Teal) */

    /* ===== START: เปลี่ยน Font ===== */
    font-family: 'K2D', sans-serif;
    font-weight: 400; /* น้ำหนักปกติ */
    /* ===== END: เปลี่ยน Font ===== */
}
h2 { 
    /* ===== START: แก้ไขสีหัวข้อ (Pastel Mint) ===== */
    color: #00796b; /* สีเขียว Teal */
    /* ===== END: แก้ไขสีหัวข้อ ===== */
    font-weight: 700; /* Bold */
} 
h5 { 
    font-size: 1.25rem; 
    font-weight: 600; /* Semi-bold */
    color: #00897b; /* สีเขียว Teal (สว่างขึ้นเล็กน้อย) */
    border-bottom: 1px solid rgba(0,0,0,0.1); /* เส้นแบ่งใต้หัวข้อ */
    padding-bottom: 0.5rem; 
    margin-bottom: 1rem; 
} 
.card {
    backdrop-filter: none;
    /* ===== START: แก้ไขสีการ์ด (Pastel Mint) ===== */
    background: #ffffff; /* การ์ดสีขาว */
    border: 1px solid #b2dfdb; /* ขอบสีเขียวมิ้นต์อ่อน */
    /* ===== END: แก้ไขสีการ์ด ===== */
    border-radius: 1rem;
    box-shadow: 0 4px 25px rgba(0,0,0,0.05); /* เงาจางๆ */
    /* ===== START: เพิ่ม Transition สำหรับ hover effect ===== */
    transition: transform 0.2s ease-out, box-shadow 0.2s ease-out;
    /* ===== END: เพิ่ม Transition ===== */
}
.card:hover {
    transform: translateY(-5px); /* เลื่อนขึ้นเล็กน้อยเมื่อเมาส์ชี้ */
    box-shadow: 0 8px 30px rgba(0,0,0,0.1); /* เงาเข้มขึ้น */
}
.kpi-card {
    display: flex; /* จัดให้เนื้อหาอยู่บน Canvas */
    align-items: center;
    justify-content: space-between;
    padding: 1rem; /* ลด padding */
    min-height: 120px; /* เพิ่มความสูงขั้นต่ำให้เท่ากัน */
    position: relative; /* สำหรับตำแหน่ง % */
}
.kpi-content {
    flex-grow: 1; /* ให้เนื้อหาขยายเต็มพื้นที่ */
    text-align: left; /* จัดข้อความชิดซ้าย */
    padding-right: 10px; /* เว้นระยะจาก Canvas */
}
.kpi-chart-wrapper {
    width: 80px; /* กำหนดขนาดของวงกลม KPI */
    height: 80px;
    position: relative; /* สำหรับ text overlay */
}
.kpi-percentage {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    font-size: 1.1rem;
    font-weight: 700;
    color: #00796b; /* สีเขียว Teal */
    pointer-events: none; /* ไม่ให้รบกวนการคลิก Canvas */
}
.kpi-title {
    font-size: 0.9rem; /* ลดขนาด font */
    font-weight: 500; /* Medium */
    color: #00897b; /* สีเขียว Teal */
    margin-bottom: 0.2rem; /* ลดระยะห่าง */
}
.kpi-value {
    font-size: 1.6rem; /* ลดขนาด font */
    font-weight: 700; /* Bold */
    color: #00796b; /* สีเขียว Teal (เข้ม) */
    line-height: 1.2;
}
canvas { max-height: 400px; } 
footer { text-align: center; font-size: 0.8rem; color: #00796b; margin-top: 2rem; padding-top: 1rem; border-top: 1px solid rgba(0,0,0,0.05); }
</style>
</head>
<body class="p-4">

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-5">
        <h2><i class="bi bi-balloon-heart-fill me-3" style="color:#ffb74d;"></i>Retail DW Analytics Dashboard</h2> 
        <span class="text-secondary small"><i class="bi bi-calendar-check me-1"></i>อัพเดตล่าสุด: <?= date("d M Y") ?></span>
    </div>

    <?php if (isset($mysqli) && $mysqli->connect_error): ?>
        <div class="alert alert-danger">Database Connection Error: <?= htmlspecialchars($mysqli->connect_error) ?></div>
    <?php elseif ($data['error']): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($data['error']) ?></div>
    <?php else: ?>
    <div class="row g-4 mb-5">
        
<div class="col-md-4"><div class="card kpi-card">
            <div class="kpi-content">
                <div class="kpi-title"><i class="bi bi-currency-dollar me-1" style="color:#66cdaa;"></i>ยอดขาย 30 วัน</div>
                <div class="kpi-value">฿<?= nf($data['kpi']['sales_30d']) ?></div>
            </div>
            <div class="kpi-chart-wrapper">
                <canvas id="kpiSalesChart"></canvas>
                <div class="kpi-percentage"><?= round($data['kpi_pct']['sales']) ?>%</div>
            </div>
        </div></div>

        
<div class="col-md-4"><div class="card kpi-card">
            <div class="kpi-content">
                <div class="kpi-title"><i class="bi bi-box me-1" style="color:#ffb74d;"></i>จำนวนชิ้นขาย</div>
                <div class="kpi-value"><?= number_format((int)$data['kpi']['qty_30d']) ?> ชิ้น</div>
            </div>
            <div class="kpi-chart-wrapper">
                <canvas id="kpiQtyChart"></canvas>
                <div class="kpi-percentage"><?= round($data['kpi_pct']['qty']) ?>%</div>
            </div>
        </div></div>

        
<div class="col-md-4"><div class="card kpi-card">
            <div class="kpi-content">
                <div class="kpi-title"><i class="bi bi-people-fill me-1" style="color:#fff176;"></i>ผู้ซื้อ (30 วัน)</div>
                <div class="kpi-value"><?= number_format((int)$data['kpi']['buyers_30d']) ?> คน</div>
            </div>
            <div class="kpi-chart-wrapper">
                <canvas id="kpiBuyersChart"></canvas>
                <div class="kpi-percentage"><?= round($data['kpi_pct']['buyers']) ?>%</div>
            </div>
        </div></div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8"><div class="card p-4">
            <h5><i class="bi bi-graph-up me-2" style="color:#66cdaa;"></i>ยอดขายรายเดือน</h5><canvas id="monthlyChart"></canvas>
        </div></div>
        <div class="col-lg-4"><div class="card p-4">
            <h5><i class="bi bi-tags-fill me-2" style="color:#ffb74d;"></i>ยอดขายตามหมวด</h5><canvas id="categoryChart"></canvas>
        </div></div>

        <div class="col-lg-6"><div class="card p-4">
            <h5><i class="bi bi-geo-alt-fill me-2" style="color:#fff176;"></i>ยอดขายตามภูมิภาค</h5><canvas id="regionChart"></canvas>
        </div></div>
        <div class="col-lg-6"><div class="card p-4">
            <h5><i class="bi bi-star-fill me-2" style="color:#81d4fa;"></i>สินค้าขายดี</h5><canvas id="topChart"></canvas>
        </div></div>

        <div class="col-lg-6"><div class="card p-4">
            <h5><i class="bi bi-credit-card-2-front-fill me-2" style="color:#b39ddb;"></i>วิธีการชำระเงิน</h5><canvas id="payChart"></canvas>
        </div></div>
        <div class="col-lg-6"><div class="card p-4">
            <h5><i class="bi bi-clock-fill me-2" style="color:#66cdaa;"></i>ยอดขายรายชั่วโมง</h5><canvas id="hourChart"></canvas>
        </div></div>

        <div class="col-12"><div class="card p-4">
            <h5><i class="bi bi-person-lines-fill me-2" style="color:#ffb74d;"></i>ลูกค้าใหม่ vs ลูกค้าเดิม</h5><canvas id="custChart"></canvas>
        </div></div>
    </div>
    <?php endif; ?>
</div>

<footer>© <?= date("Y") ?> Retail DW Analytics Dashboard. All rights reserved.</footer>

<script>
// JavaScript/Chart.js Configuration (แก้ไข Error โดยการตรวจสอบ Context)
const d = <?= json_encode($data, JSON_UNESCAPED_UNICODE) ?>;
const ctx = id => document.getElementById(id);

// ฟังก์ชันสำคัญ: ตรวจสอบว่า Canvas Element มีอยู่และดึง 2D Context ได้หรือไม่
const chartContext = id => ctx(id) ? ctx(id).getContext('2d') : null; 

const toXY = (a, x, y) => ({labels:a.map(o=>o[x]),values:a.map(o=>parseFloat(o[y]))});

// ===== START: แก้ไข Base Options (Pastel Mint) =====
// Base Options สำหรับแผนภูมิทั้งหมด
const baseOpt = {
    responsive:true,
    maintainAspectRatio: false,
    plugins:{
        legend:{
            labels:{
                color:'#004d40', /* เปลี่ยนสี Legend เป็นสีเขียวเข้ม */
                boxWidth: 15,
                padding: 15
            }
        },
        tooltip:{
            backgroundColor:'#ffffff', /* Tooltip สีขาว */
            titleColor: '#00796b', /* สีเขียว Teal */
            bodyColor: '#004d40', /* สีตัวอักษรเขียวเข้ม */
            borderColor: 'rgba(0,0,0,0.1)',
            borderWidth: 1
        }
    },
    scales:{
        x:{
            grid:{ color:'rgba(0, 121, 107, 0.1)' }, /* เส้น Grid สีเขียวจางๆ */
            ticks:{ color:'#00897b' } /* ปรับสีแกน X เป็นสีเขียว Teal */
        },
        y:{
            grid:{ color:'rgba(0, 121, 107, 0.1)' },
            ticks:{ color:'#00897b' } /* ปรับสีแกน Y เป็นสีเขียว Teal */
        }
    },
    animation:{ duration:1200, easing:'easeOutCubic' }
};
// ===== END: แก้ไข Base Options =====

// Function สำหรับสร้าง Doughnut Chart ของ KPI
const createKpiDoughnut = (id, percentage, color) => {
    const context = chartContext(id);
    if (!context) return;
    new Chart(context, {
        type: 'doughnut',
        data: {
            labels: ['Achieved', 'Remaining'],
            datasets: [{
                data: [percentage, 100 - percentage],
                backgroundColor: [color, 'rgba(224, 242, 241, 0.5)'], // สีหลัก & สีพื้นหลังจางๆ
                borderWidth: 0,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '75%', // ทำให้เป็นโดนัท
            plugins: {
                legend: { display: false },
                tooltip: { enabled: false } // ปิด tooltip
            },
            animation: {
                animateScale: true,
                animateRotate: true,
                duration: 1000
            }
        }
    });
};

// สร้าง KPI Doughnut Charts
createKpiDoughnut('kpiSalesChart', d.kpi_pct.sales, '#66cdaa'); // Mint
createKpiDoughnut('kpiQtyChart', d.kpi_pct.qty, '#ffb74d');   // Peach
createKpiDoughnut('kpiBuyersChart', d.kpi_pct.buyers, '#fff176'); // Soft Yellow


// Monthly Chart (Line)
(() => {
    const context = chartContext('monthlyChart');
    if (!context) return; // แก้ Error: จะไม่พยายามสร้างถ้า Canvas เป็น null
    const {labels,values} = toXY(d.monthly,'ym','net_sales');
    new Chart(context, {
        type:'line',
        data:{ labels, datasets:[{
            label:'ยอดขาย (฿)',
            data:values,
            borderColor:'#66cdaa', /* MediumAquamarine (Mint) */
            backgroundColor:'rgba(102, 205, 170, 0.25)',
            pointBackgroundColor: '#ffffff',
            pointBorderColor: '#66cdaa',
            pointRadius: 4,
            fill:true,
            tension:0.4
        }]},
        options:baseOpt
    });
})();

// ===== START: เปลี่ยน Category Chart เป็น Bar Chart =====
(() => {
    const context = chartContext('categoryChart');
    if (!context) return;
    const {labels,values}=toXY(d.category,'category','net_sales');
    new Chart(context, {
        type:'bar', // เปลี่ยนจาก 'doughnut' เป็น 'bar'
        data:{labels,datasets:[{
            label: 'ยอดขาย (฿)', // เพิ่ม Label
            data:values,
            backgroundColor: '#66cdaa', // เปลี่ยนสี (ใช้สี Mint)
            borderRadius: 5 // เพิ่มความมน
        }]},
        options: baseOpt // ใช้ Base Options (มีแกน X/Y)
    });
})();
// ===== END: เปลี่ยน Category Chart เป็น Bar Chart =====

// Top products Chart (Vertical Bar)
(() => {
    const context = chartContext('topChart');
    if (!context) return;
    const labels=d.topProducts.map(o=>o.product_name);
    
    // (โค้ดแก้ปัญหาชาร์ตไม่ขึ้น จากครั้งที่แล้ว)
    const vals=d.topProducts.map(o=>parseFloat(o.qty_sold) || 0); 

    new Chart(context, {
        type:'bar',
        data:{labels,datasets:[{
            label:'ชิ้นขาย',
            data:vals,
            backgroundColor:'#ffb74d', /* "Peach" (Soft Orange) */
            borderRadius: 5
        }]},
        options:baseOpt 
    });
})();

// Region Chart (Bar)
(() => {
    const context = chartContext('regionChart');
    if (!context) return;
    const {labels,values}=toXY(d.region,'region','net_sales');
    new Chart(context, {
        type:'bar',
        data:{labels,datasets:[{
            label:'ยอดขาย (฿)',
            data:values,
            backgroundColor:'#fff176', /* Soft Yellow */
            borderRadius: 5
        }]},
        options:baseOpt
    });
})();

// ===== START: เปลี่ยน Payment Chart เป็น Bar Chart =====
(() => {
    const context = chartContext('payChart');
    if (!context) return;
    const {labels,values}=toXY(d.payment,'payment_method','net_sales');
    new Chart(context, {
        type:'bar', // เปลี่ยนจาก 'pie' เป็น 'bar'
        data:{labels,datasets:[{
            label: 'ยอดขาย (฿)', // เพิ่ม Label
            data:values,
            backgroundColor:'#b39ddb', /* Lilac (Purple) */
            borderRadius: 5 // เพิ่มความมน
        }]},
        options: baseOpt // ใช้ Base Options (มีแกน X/Y)
    });
})();
// ===== END: เปลี่ยน Payment Chart เป็น Bar Chart =====

// Hourly Chart (Bar)
(() => {
    const context = chartContext('hourChart');
    if (!context) return;
    const {labels,values}=toXY(d.hourly,'hour_of_day','net_sales');
    new Chart(context, {
        type:'bar',
        data:{labels,datasets:[{
            label:'ยอดขาย (฿)',
            data:values,
            backgroundColor:'#81d4fa', /* Soft Blue */
            borderRadius: 5
        }]},
        options:baseOpt
    });
})();

// New vs Returning Chart (Line)
(() => {
    const context = chartContext('custChart');
    if (!context) return;
    const labels=d.newReturning.map(o=>o.date_key);
    const n=d.newReturning.map(o=>parseFloat(o.new_customer_sales));
    const r=d.newReturning.map(o=>parseFloat(o.returning_sales));
    new Chart(context,{
        type:'line',
        data:{labels,datasets:[
            {label:'ลูกค้าใหม่',data:n,borderColor:'#66cdaa',tension:0.4, fill:false, pointRadius: 3}, /* Mint */
            {label:'ลูกค้าเดิม',data:r,borderColor:'#b39ddb',tension:0.4, fill:false, pointRadius: 3} /* Lilac (Purple) */
        ]},
        options:baseOpt
    });
})();
</script>
</body>
</html>