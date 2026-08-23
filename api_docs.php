<?php
/**
 * API Documentation Page (api_docs.php)
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

$pageTitle = 'API Documentation - ' . getSetting('site_name', SITE_NAME);
require_once __DIR__ . '/includes/header.php';

$apiKey = getSetting('reseller_api_key', 'rk_your_key_here');
$apiUrl = getSetting('reseller_api_url', RESELLER_API_BASE_URL);
$maskedKey = (!empty($apiKey) && $apiKey !== 'rk_your_key_here')
    ? substr($apiKey, 0, 8) . str_repeat('*', 20)
    : 'rk_your_key_here';
?>
<style>
.api-docs-hero{background:linear-gradient(135deg,#090d16 0%,#1e293b 60%,#0f3460 100%);color:#fff;padding:3.5rem 0 2.5rem;border-bottom:1px solid rgba(255,255,255,.08)}
.api-section{background:#fff;border-radius:16px;border:1.5px solid #e2e8f0;box-shadow:0 2px 12px rgba(15,23,42,.06);margin-bottom:2rem;overflow:hidden}
.api-section-header{background:#f8fafc;padding:1.1rem 1.75rem;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;gap:10px}
.api-section-body{padding:1.75rem}
.code-block{background:#0f172a;color:#e2e8f0;border-radius:10px;padding:1.2rem 1.5rem;font-family:'Courier New',monospace;font-size:.88rem;line-height:1.7;position:relative;overflow-x:auto;margin:.75rem 0}
.code-block .c{color:#64748b}.code-block .k{color:#38bdf8}.code-block .v{color:#86efac}
.code-block .mg{color:#34d399;font-weight:700}.code-block .mp{color:#fb923c;font-weight:700}
.copy-btn{position:absolute;top:10px;right:10px;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.2);color:#cbd5e1;border-radius:6px;padding:3px 10px;font-size:.75rem;cursor:pointer;transition:all .2s}
.copy-btn:hover{background:rgba(255,255,255,.2);color:#fff}
.badge-get{display:inline-block;padding:3px 12px;border-radius:20px;font-size:.8rem;font-weight:700;background:#dcfce7;color:#15803d;border:1px solid #bbf7d0}
.badge-post{display:inline-block;padding:3px 12px;border-radius:20px;font-size:.8rem;font-weight:700;background:#fff7ed;color:#c2410c;border:1px solid #fed7aa}
.param-table th{background:#f1f5f9;font-weight:700;color:#0f172a;font-size:.875rem}
.param-table td{font-size:.9rem;color:#1e293b;vertical-align:middle}
.r-ok{background:#f0fdf4;border-left:4px solid #22c55e;border-radius:0 8px 8px 0;padding:1rem 1.25rem;margin:.5rem 0}
.r-err{background:#fef2f2;border-left:4px solid #ef4444;border-radius:0 8px 8px 0;padding:1rem 1.25rem;margin:.5rem 0}
.sidebar-nav{position:sticky;top:85px}
.sidebar-nav .nav-link{color:#334155;font-size:.9rem;padding:8px 16px;border-radius:8px;font-weight:500;transition:all .15s}
.sidebar-nav .nav-link:hover,.sidebar-nav .nav-link.active{background:#eff6ff;color:#2563eb;font-weight:700}
.scope-b{display:inline-flex;align-items:center;gap:5px;background:#f1f5f9;border:1px solid #cbd5e1;border-radius:20px;padding:3px 12px;font-size:.8rem;font-weight:600;color:#0f172a}
</style>

<div class="api-docs-hero">
  <div class="container">
    <div class="d-flex align-items-center gap-3 mb-3">
      <span class="badge bg-info text-dark fw-bold px-3 py-2 rounded-pill fs-6">API</span>
      <h1 class="mb-0 fw-bold fs-3">Nami Reseller API — Documentation</h1>
    </div>
    <p class="text-light opacity-75 mb-3">คู่มือการเชื่อมต่อและใช้งาน Reseller API สำหรับจัดการ Hosting, VPS, Invoices และ Wallet</p>
    <div class="d-flex flex-wrap gap-3">
      <div class="code-block d-inline-block mb-0 py-2 px-4" style="background:rgba(0,0,0,.4);border-radius:10px">
        <span class="k">Base URL:</span> <span class="v"><?= htmlspecialchars($apiUrl) ?></span>
      </div>
      <div class="code-block d-inline-block mb-0 py-2 px-4" style="background:rgba(0,0,0,.4);border-radius:10px">
        <span class="k">API Key:</span> <span class="v"><?= htmlspecialchars($maskedKey) ?></span>
      </div>
    </div>
  </div>
</div>

<div class="container py-5">
  <div class="row g-5">

    <div class="col-lg-3 d-none d-lg-block">
      <nav class="sidebar-nav">
        <div class="fw-bold text-muted small text-uppercase mb-2 px-2">เนื้อหา</div>
        <div class="nav flex-column">
          <a class="nav-link" href="#auth"><i class="bi bi-shield-lock me-2"></i>Authentication</a>
          <a class="nav-link" href="#scopes"><i class="bi bi-key me-2"></i>Scopes &amp; Limits</a>
          <a class="nav-link" href="#balance"><i class="bi bi-wallet2 me-2"></i>Balance</a>
          <a class="nav-link" href="#categories"><i class="bi bi-folder2 me-2"></i>Categories</a>
          <a class="nav-link" href="#packages"><i class="bi bi-server me-2"></i>Packages (Hosting)</a>
          <a class="nav-link" href="#vps-packages"><i class="bi bi-cpu me-2"></i>VPS Packages</a>
          <a class="nav-link" href="#services"><i class="bi bi-hdd-stack me-2"></i>Services (Hosting)</a>
          <a class="nav-link" href="#vps-services"><i class="bi bi-hdd-network me-2"></i>VPS Services</a>
          <a class="nav-link" href="#invoices"><i class="bi bi-receipt me-2"></i>Invoices</a>
          <a class="nav-link" href="#order-hosting"><i class="bi bi-cart-plus me-2"></i>Order Hosting</a>
          <a class="nav-link" href="#order-vps"><i class="bi bi-cart-check me-2"></i>Order VPS</a>
          <a class="nav-link" href="#renew"><i class="bi bi-arrow-clockwise me-2"></i>Renew Hosting</a>
          <a class="nav-link" href="#renew-vps"><i class="bi bi-arrow-repeat me-2"></i>Renew VPS</a>
          <a class="nav-link" href="#reports"><i class="bi bi-chat-square-text me-2"></i>Hosting Reports</a>
          <a class="nav-link" href="#errors"><i class="bi bi-exclamation-triangle me-2"></i>Error Codes</a>
        </div>
      </nav>
    </div>

    <div class="col-lg-9">

      <!-- AUTH -->
      <div class="api-section" id="auth">
        <div class="api-section-header">
          <i class="bi bi-shield-lock-fill text-warning fs-5"></i>
          <h4 class="mb-0 fw-bold">Authentication</h4>
        </div>
        <div class="api-section-body">
          <p>ส่ง API Key ผ่าน HTTP Header <code>X-Api-Key</code> ทุก Request:</p>
          <div class="code-block position-relative"><button class="copy-btn" onclick="copyCode(this)">Copy</button><span class="mg">GET</span> <?= htmlspecialchars($apiUrl) ?>?action=balance
<span class="k">X-Api-Key:</span> <span class="v">rk_your_key_here</span></div>
          <div class="alert alert-warning d-flex gap-2 mt-3 mb-0">
            <i class="bi bi-exclamation-triangle-fill mt-1 flex-shrink-0"></i>
            <div><strong>อย่าส่ง API Key ผ่าน Query String</strong> (<code>?key=</code>) เพราะจะรั่วใน Access Log ของเซิร์ฟเวอร์</div>
          </div>
        </div>
      </div>

      <!-- SCOPES -->
      <div class="api-section" id="scopes">
        <div class="api-section-header">
          <i class="bi bi-key-fill text-primary fs-5"></i>
          <h4 class="mb-0 fw-bold">Scopes &amp; Rate Limiting</h4>
        </div>
        <div class="api-section-body">
          <table class="table param-table table-bordered mb-4">
            <thead><tr><th>Scope</th><th>สิทธิ์</th><th>Endpoints</th></tr></thead>
            <tbody>
              <tr><td><span class="scope-b"><i class="bi bi-eye"></i> read</span></td><td>ดูข้อมูลบัญชี, services, invoices</td><td><code>balance, services, service, vps, vps_service, invoices, invoice</code></td></tr>
              <tr><td><span class="scope-b"><i class="bi bi-cart"></i> order</span></td><td>สั่งซื้อ Hosting / VPS ใหม่</td><td><code>order_hosting, order_vps</code></td></tr>
              <tr><td><span class="scope-b"><i class="bi bi-arrow-clockwise"></i> renew</span></td><td>ต่ออายุ Services ที่มีอยู่</td><td><code>renew, renew_vps</code></td></tr>
            </tbody>
          </table>
          <div class="row g-3">
            <div class="col-md-6">
              <div class="p-3 bg-light rounded-3">
                <i class="bi bi-speedometer2 text-primary me-2"></i><strong>Rate Limiting:</strong> 120 requests/นาที/API Key<br>
                <small class="text-muted">เมื่อเกินจะได้รับ <code>429 Too Many Requests</code> — รอ 1 นาทีแล้วลองใหม่</small><br>
                <small class="text-muted">Response headers: <code>X-RateLimit-Limit</code>, <code>X-RateLimit-Remaining</code></small>
              </div>
            </div>
            <div class="col-md-6">
              <div class="p-3 bg-light rounded-3">
                <i class="bi bi-geo-alt text-success me-2"></i><strong>IP Whitelist:</strong> ตั้งค่าได้ที่หน้า <a href="profile.php#api-keys">Profile → API Keys</a><br>
                <small class="text-muted">ระบุ IP ที่อนุญาต คั่นด้วย <code>,</code> เช่น <code>1.2.3.4, 5.6.7.8</code></small><br>
                <small class="text-muted">หากเว้นว่างจะอนุญาตทุก IP — แนะนำให้ตั้งค่าในสภาพแวดล้อม Production เสมอ</small>
              </div>
            </div>
          </div>
          <div class="alert alert-info mt-3 mb-0 py-2 small">
            <i class="bi bi-shield-check me-2"></i>
            <strong>Best practices:</strong>
            เปิด scope เฉพาะที่ใช้งานจริง &nbsp;·&nbsp;
            ตั้ง IP whitelist เสมอใน production &nbsp;·&nbsp;
            เก็บ API Key ใน environment variable ไม่ใช่ใน source code &nbsp;·&nbsp;
            ตรวจสอบ <code>"ok": true/false</code> ก่อนอ่านข้อมูลเสมอ
          </div>
        </div>
      </div>

      <!-- BALANCE -->
      <div class="api-section" id="balance">
        <div class="api-section-header">
          <i class="bi bi-wallet2 text-success fs-5"></i>
          <h4 class="mb-0 fw-bold">Balance — ดูยอดเครดิต Reseller</h4>
          <span class="badge-get ms-auto">GET</span>
          <span class="scope-b ms-2"><i class="bi bi-eye"></i> read</span>
        </div>
        <div class="api-section-body">
          <div class="code-block position-relative"><button class="copy-btn" onclick="copyCode(this)">Copy</button><span class="mg">GET</span> <?= htmlspecialchars($apiUrl) ?>?action=balance
<span class="k">X-Api-Key:</span> <span class="v">rk_your_key_here</span></div>
          <div class="r-ok"><strong>&#10003; Response 200:</strong>
            <div class="code-block mt-2">{ "<span class="k">ok</span>": <span class="v">true</span>, "<span class="k">data</span>": { "<span class="k">name</span>": <span class="v">"My Account"</span>, "<span class="k">credit</span>": <span class="v">10009.00</span> } }</div>
          </div>
        </div>
      </div>

      <!-- CATEGORIES -->
      <div class="api-section" id="categories">
        <div class="api-section-header">
          <i class="bi bi-folder2-open text-warning fs-5"></i>
          <h4 class="mb-0 fw-bold">Categories — หมวดหมู่แพ็กเกจ Hosting</h4>
          <span class="badge-get ms-auto">GET</span>
          <span class="ms-2 badge bg-secondary">Public</span>
        </div>
        <div class="api-section-body">
          <div class="code-block position-relative"><button class="copy-btn" onclick="copyCode(this)">Copy</button><span class="mg">GET</span> <?= htmlspecialchars($apiUrl) ?>?action=categories</div>
          <div class="r-ok mt-3"><strong>&#10003; Response:</strong>
            <div class="code-block mt-2">{ "<span class="k">ok</span>": <span class="v">true</span>, "<span class="k">count</span>": <span class="v">3</span>, "<span class="k">data</span>": [
  { "<span class="k">id</span>": <span class="v">1</span>, "<span class="k">name</span>": <span class="v">"TH DirectAdmin"</span>, "<span class="k">slug</span>": <span class="v">"th-directadmin"</span>, "<span class="k">description</span>": <span class="v">"..."</span>, "<span class="k">sort_order</span>": <span class="v">1</span> }
] }</div>
          </div>
        </div>
      </div>

      <!-- PACKAGES -->
      <div class="api-section" id="packages">
        <div class="api-section-header">
          <i class="bi bi-server text-primary fs-5"></i>
          <h4 class="mb-0 fw-bold">Packages — รายการแพ็กเกจ Hosting</h4>
          <span class="badge-get ms-auto">GET</span>
        </div>
        <div class="api-section-body">
          <div class="code-block position-relative"><button class="copy-btn" onclick="copyCode(this)">Copy</button><span class="c"># ทุกหมวดหมู่</span>
<span class="mg">GET</span> <?= htmlspecialchars($apiUrl) ?>?action=packages

<span class="c"># กรองหมวดหมู่</span>
<span class="mg">GET</span> <?= htmlspecialchars($apiUrl) ?>?action=packages&amp;category=th-directadmin</div>
          <div class="r-ok mt-3"><strong>&#10003; Response:</strong>
            <div class="code-block mt-2">{ "<span class="k">ok</span>": <span class="v">true</span>, "<span class="k">categories</span>": [ { "<span class="k">category_id</span>": <span class="v">1</span>, "<span class="k">category_name</span>": <span class="v">"TH DirectAdmin"</span>, "<span class="k">packages</span>": [
  { "<span class="k">id</span>": <span class="v">101</span>, "<span class="k">name</span>": <span class="v">"Nano TH"</span>, "<span class="k">price_monthly</span>": <span class="v">49.00</span>, "<span class="k">price_yearly</span>": <span class="v">490.00</span>,
    "<span class="k">disk_mb</span>": <span class="v">1024</span>, "<span class="k">bandwidth_mb</span>": <span class="v">0</span>, "<span class="k">domains</span>": <span class="v">0</span>, "<span class="k">databases</span>": <span class="v">0</span>, "<span class="k">emails</span>": <span class="v">0</span> }
] } ] }</div>
          </div>
          <div class="alert alert-info border-info mt-3 mb-0">
            <i class="bi bi-info-circle me-2"></i>
            ค่า <code>0</code> ใน <code>bandwidth_mb, databases, emails</code> = <strong>ไม่จำกัด (Unlimited)</strong> | ค่า <code>0</code> ใน <code>domains</code> = <strong>1 โดเมนหลัก</strong>
          </div>
        </div>
      </div>

      <!-- VPS PACKAGES -->
      <div class="api-section" id="vps-packages">
        <div class="api-section-header">
          <i class="bi bi-cpu text-info fs-5"></i>
          <h4 class="mb-0 fw-bold">VPS Packages — รายการแพ็กเกจ Cloud VPS</h4>
          <span class="badge-get ms-auto">GET</span>
        </div>
        <div class="api-section-body">
          <div class="code-block position-relative"><button class="copy-btn" onclick="copyCode(this)">Copy</button><span class="mg">GET</span> <?= htmlspecialchars($apiUrl) ?>?action=vps_packages
<span class="k">X-Api-Key:</span> <span class="v">rk_your_key_here</span></div>
          <div class="r-ok mt-3"><strong>&#10003; Response:</strong>
            <div class="code-block mt-2">{ "<span class="k">ok</span>": <span class="v">true</span>,
  "<span class="k">plans</span>": [ { "<span class="k">id</span>": <span class="v">201</span>, "<span class="k">name</span>": <span class="v">"VPS Starter"</span>, "<span class="k">price_monthly</span>": <span class="v">199.00</span>, "<span class="k">vcpu</span>": <span class="v">1</span>, "<span class="k">ram_mb</span>": <span class="v">1024</span>, "<span class="k">disk_gb</span>": <span class="v">25</span> } ],
  "<span class="k">os_options</span>": [ { "<span class="k">id</span>": <span class="v">1</span>, "<span class="k">name</span>": <span class="v">"Ubuntu 22.04"</span> }, { "<span class="k">id</span>": <span class="v">2</span>, "<span class="k">name</span>": <span class="v">"Debian 12"</span> } ]
}</div>
          </div>
        </div>
      </div>

      <!-- SERVICES -->
      <div class="api-section" id="services">
        <div class="api-section-header">
          <i class="bi bi-hdd-stack text-success fs-5"></i>
          <h4 class="mb-0 fw-bold">Services — รายการ Hosting</h4>
          <span class="badge-get ms-auto">GET</span>
          <span class="scope-b ms-2"><i class="bi bi-eye"></i> read</span>
        </div>
        <div class="api-section-body">
          <div class="code-block position-relative"><button class="copy-btn" onclick="copyCode(this)">Copy</button><span class="c"># ทุก services</span>
<span class="mg">GET</span> <?= htmlspecialchars($apiUrl) ?>?action=services

<span class="c"># service รายการเดียว</span>
<span class="mg">GET</span> <?= htmlspecialchars($apiUrl) ?>?action=service&amp;id=12345</div>
          <div class="r-ok mt-3"><strong>&#10003; Response:</strong>
            <div class="code-block mt-2">{ "<span class="k">ok</span>": <span class="v">true</span>, "<span class="k">count</span>": <span class="v">2</span>, "<span class="k">data</span>": [ { "<span class="k">id</span>": <span class="v">1</span>, "<span class="k">domain</span>": <span class="v">"mysite.com"</span>, "<span class="k">username</span>": <span class="v">"myuser"</span>, "<span class="k">password</span>": <span class="v">"mypassword"</span>, "<span class="k">ip_address</span>": <span class="v">"203.0.113.1"</span>, "<span class="k">status</span>": <span class="v">"active"</span>, "<span class="k">disk_percent</span>": <span class="v">10.0</span>, "<span class="k">bw_percent</span>": <span class="v">1.0</span>, "<span class="k">package</span>": <span class="v">"Business"</span>, "<span class="k">server_name</span>": <span class="v">"SRV-BKK-01"</span>, "<span class="k">server_hostname</span>": <span class="v">"srv1.example.com"</span>, "<span class="k">nameserver1</span>": <span class="v">"ns1.example.com"</span>, "<span class="k">nameserver2</span>": <span class="v">"ns2.example.com"</span> } ] }</div>
          </div>
          
          <h6 class="mt-4 fw-bold">คำอธิบายข้อมูล API (Hosting Service Response)</h6>
          <p class="text-muted small mb-2">ข้อมูลชุดนี้คือรายละเอียดของบริการโฮสติ้งที่ระบบ API ของเราส่งกลับไปให้เมื่อมีการเรียกดูข้อมูล โดยแต่ละตัวแปร (Field) มีความหมายดังนี้ครับ:</p>
          <div class="table-responsive">
            <table class="table param-table table-bordered mb-0">
              <thead><tr><th>Field</th><th>Type</th><th>คำอธิบาย</th></tr></thead>
              <tbody>
                <tr><td colspan="3" class="bg-light fw-bold text-dark">ข้อมูลสถานะทั่วไป</td></tr>
                <tr><td><code>ok</code></td><td>boolean</td><td>สถานะการตอบกลับของ API (true = ดึงข้อมูลสำเร็จ, false = เกิดข้อผิดพลาด)</td></tr>
                <tr><td><code>data</code></td><td>object/array</td><td>ก้อนข้อมูลรายละเอียดของโฮสติ้งที่เรียกดู</td></tr>
                
                <tr><td colspan="3" class="bg-light fw-bold text-dark">รายละเอียดภายใน data</td></tr>
                <tr><td><code>id</code></td><td>integer</td><td>รหัสอ้างอิงบริการ (Service ID) ในระบบของเรา</td></tr>
                <tr><td><code>domain</code></td><td>string</td><td>ชื่อโดเมนเนมที่ผูกกับบริการโฮสติ้งนี้</td></tr>
                <tr><td><code>username</code></td><td>string</td><td>ชื่อผู้ใช้งาน (Username) สำหรับใช้ล็อกอินเข้าสู่ระบบจัดการโฮสติ้ง (เช่น DirectAdmin) และ FTP หลัก</td></tr>
                <tr><td><code>password</code></td><td>string</td><td>รหัสผ่าน (Password) สำหรับล็อกอินระบบจัดการโฮสติ้ง และ FTP</td></tr>
                <tr><td><code>status</code></td><td>string</td><td>สถานะปัจจุบันของบริการโฮสติ้ง <br><small class="text-muted"><code>active</code> : เปิดใช้งานปกติ<br><code>pending</code> : รอการตรวจสอบหรือรอเปิดใช้งาน<br><code>suspended</code> : ถูกระงับการใช้งาน<br><code>terminated</code> : ถูกยกเลิกบริการและลบข้อมูลแล้ว</small></td></tr>
                
                <tr><td colspan="3" class="bg-light fw-bold text-dark">ข้อมูลเซิร์ฟเวอร์และการตั้งค่าโดเมน</td></tr>
                <tr><td><code>ip_address</code></td><td>string</td><td>หมายเลข IP ประจำเซิร์ฟเวอร์ (IP Address)</td></tr>
                <tr><td><code>server_name</code></td><td>string</td><td>ชื่อเรียกของเซิร์ฟเวอร์ในระบบ (ใช้สำหรับอ้างอิง)</td></tr>
                <tr><td><code>server_hostname</code></td><td>string</td><td>ชื่อ Hostname ของเซิร์ฟเวอร์ที่ให้บริการ (สามารถใช้แทน IP ในการทำ Record บางอย่างได้)</td></tr>
                <tr><td><code>nameserver1</code></td><td>string</td><td>Nameserver ตัวที่ 1 ที่ลูกค้าต้องนำไปตั้งค่าให้กับโดเมน (เพื่อให้โดเมนชี้มาที่โฮสติ้งนี้)</td></tr>
                <tr><td><code>nameserver2</code></td><td>string</td><td>Nameserver ตัวที่ 2 ที่ลูกค้าต้องนำไปตั้งค่าให้กับโดเมน</td></tr>
                
                <tr><td colspan="3" class="bg-light fw-bold text-dark">ข้อมูลการใช้งานทรัพยากร</td></tr>
                <tr><td><code>disk_percent</code></td><td>float</td><td>ปริมาณพื้นที่เก็บข้อมูล (Disk Space) ที่ใช้งานไปแล้ว คิดเป็นเปอร์เซ็นต์ (เช่น 10.0 = ใช้ไปแล้ว 10%)</td></tr>
                <tr><td><code>bw_percent</code></td><td>float</td><td>ปริมาณการรับส่งข้อมูล (Bandwidth) ที่ใช้งานไปแล้วในรอบเดือน คิดเป็นเปอร์เซ็นต์</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- VPS SERVICES -->
      <div class="api-section" id="vps-services">
        <div class="api-section-header">
          <i class="bi bi-hdd-network text-info fs-5"></i>
          <h4 class="mb-0 fw-bold">VPS Services — รายการ Cloud VPS</h4>
          <span class="badge-get ms-auto">GET</span>
          <span class="scope-b ms-2"><i class="bi bi-eye"></i> read</span>
        </div>
        <div class="api-section-body">
          <div class="code-block position-relative"><button class="copy-btn" onclick="copyCode(this)">Copy</button><span class="c"># ทุก VPS</span>
<span class="mg">GET</span> <?= htmlspecialchars($apiUrl) ?>?action=vps

<span class="c"># VPS รายการเดียว</span>
<span class="mg">GET</span> <?= htmlspecialchars($apiUrl) ?>?action=vps_service&amp;id=99</div>
          <div class="r-ok mt-3"><strong>&#10003; Response:</strong>
            <div class="code-block mt-2">{ "<span class="k">ok</span>": <span class="v">true</span>, "<span class="k">count</span>": <span class="v">1</span>, "<span class="k">data</span>": [ { "<span class="k">id</span>": <span class="v">1</span>, "<span class="k">hostname</span>": <span class="v">"vps01.example.com"</span>, "<span class="k">ip_address</span>": <span class="v">"203.0.113.1"</span>, "<span class="k">password</span>": <span class="v">"mypassword"</span>, "<span class="k">username</span>": <span class="v">"root"</span>, "<span class="k">os_name</span>": <span class="v">"Ubuntu 22.04 LTS"</span>, "<span class="k">status</span>": <span class="v">"active"</span> } ] }</div>
          </div>
        </div>
      </div>

      <!-- INVOICES -->
      <div class="api-section" id="invoices">
        <div class="api-section-header">
          <i class="bi bi-receipt text-secondary fs-5"></i>
          <h4 class="mb-0 fw-bold">Invoices — รายการใบแจ้งหนี้</h4>
          <span class="badge-get ms-auto">GET</span>
          <span class="scope-b ms-2"><i class="bi bi-eye"></i> read</span>
        </div>
        <div class="api-section-body">
          <div class="code-block position-relative"><button class="copy-btn" onclick="copyCode(this)">Copy</button><span class="c"># ทุก invoices</span>
<span class="mg">GET</span> <?= htmlspecialchars($apiUrl) ?>?action=invoices

<span class="c"># กรองสถานะ: unpaid | paid | cancelled | refunded</span>
<span class="mg">GET</span> <?= htmlspecialchars($apiUrl) ?>?action=invoices&amp;status=unpaid

<span class="c"># invoice รายการเดียว</span>
<span class="mg">GET</span> <?= htmlspecialchars($apiUrl) ?>?action=invoice&amp;id=555</div>
          <div class="r-ok mt-3"><strong>&#10003; Response:</strong>
            <div class="code-block mt-2">{ "<span class="k">ok</span>": <span class="v">true</span>, "<span class="k">invoices</span>": [ { "<span class="k">id</span>": <span class="v">555</span>, "<span class="k">amount</span>": <span class="v">199.00</span>, "<span class="k">status</span>": <span class="v">"paid"</span>, "<span class="k">created_at</span>": <span class="v">"2026-08-23"</span> } ] }</div>
          </div>
        </div>
      </div>

      <!-- ORDER HOSTING -->
      <div class="api-section" id="order-hosting">
        <div class="api-section-header">
          <i class="bi bi-cart-plus text-primary fs-5"></i>
          <h4 class="mb-0 fw-bold">Order Hosting — สั่งซื้อโฮสติ้งใหม่</h4>
          <span class="badge-post ms-auto">POST</span>
          <span class="scope-b ms-2"><i class="bi bi-cart"></i> order</span>
        </div>
        <div class="api-section-body">
          <p class="small text-muted mb-3"><i class="bi bi-info-circle me-1"></i>รองรับทั้ง <code>Content-Type: application/json</code> และ <code>application/x-www-form-urlencoded</code></p>
          <div class="code-block position-relative"><button class="copy-btn" onclick="copyCode(this)">Copy</button><span class="mp">POST</span> <?= htmlspecialchars($apiUrl) ?>?action=order_hosting
<span class="k">X-Api-Key:</span> <span class="v">rk_your_key_here</span>
<span class="k">Content-Type:</span> <span class="v">application/json</span>

{
  "<span class="k">product_id</span>":    <span class="v">101</span>,
  "<span class="k">domain</span>":        <span class="v">"example.com"</span>,
  "<span class="k">username</span>":      <span class="v">"myuser123"</span>,
  "<span class="k">password</span>":      <span class="v">"MyPass@2024"</span>,
  "<span class="k">billing_cycle</span>": <span class="v">"monthly"</span>
}</div>
          <h6 class="fw-bold mt-3">พารามิเตอร์:</h6>
          <table class="table param-table table-bordered">
            <thead><tr><th>Field</th><th>Type</th><th>จำเป็น</th><th>คำอธิบาย</th></tr></thead>
            <tbody>
              <tr><td><code>product_id</code></td><td>integer</td><td><span class="text-danger fw-bold">✱ Required</span></td><td>ID แพ็กเกจจาก <code>action=packages</code></td></tr>
              <tr><td><code>domain</code></td><td>string</td><td><span class="text-danger fw-bold">✱ Required</span></td><td>ชื่อโดเมน เช่น <code>example.com</code> (ไม่ต้องใส่ http://)</td></tr>
              <tr><td><code>username</code></td><td>string</td><td><span class="text-danger fw-bold">✱ Required</span></td><td>DirectAdmin username (a-z0-9, 4–16 ตัว, ขึ้นต้นด้วยตัวอักษร)</td></tr>
              <tr><td><code>password</code></td><td>string</td><td><span class="text-danger fw-bold">✱ Required</span></td><td>รหัสผ่าน อย่างน้อย 8 ตัว</td></tr>
              <tr><td><code>billing_cycle</code></td><td>string</td><td><span class="text-muted">Optional</span></td><td><code>monthly</code> (default) หรือ <code>yearly</code></td></tr>
            </tbody>
          </table>
          <div class="r-ok"><strong>&#10003; Response 201:</strong>
            <div class="code-block mt-2">{
  "<span class="k">ok</span>": <span class="v">true</span>,
  "<span class="k">data</span>": {
    "<span class="k">order_id</span>": <span class="v">42</span>,
    "<span class="k">invoice_id</span>": <span class="v">55</span>,
    "<span class="k">amount</span>": <span class="v">299.00</span>,
    "<span class="k">status</span>": <span class="v">"paid"</span>,
    "<span class="k">is_paid</span>": <span class="v">true</span>,
    "<span class="k">message</span>": <span class="v">"สร้างคำสั่งซื้อและตัดเครดิตเพื่อเปิดใช้งานสำเร็จ"</span>,
    "<span class="k">service_id</span>": <span class="v">12</span>,
    "<span class="k">ip_address</span>": <span class="v">"203.0.113.1"</span>,
    "<span class="k">nameserver1</span>": <span class="v">"ns1.example.com"</span>,
    "<span class="k">nameserver2</span>": <span class="v">"ns2.example.com"</span>
  }
}</div>
          </div>
        </div>
      </div>

      <!-- ORDER VPS -->
      <div class="api-section" id="order-vps">
        <div class="api-section-header">
          <i class="bi bi-cart-check text-info fs-5"></i>
          <h4 class="mb-0 fw-bold">Order VPS — สั่งซื้อ Cloud VPS ใหม่</h4>
          <span class="badge-post ms-auto">POST</span>
          <span class="scope-b ms-2"><i class="bi bi-cart"></i> order</span>
        </div>
        <div class="api-section-body">
          <p class="small text-muted mb-3"><i class="bi bi-info-circle me-1"></i>รองรับทั้ง <code>Content-Type: application/json</code> และ <code>application/x-www-form-urlencoded</code></p>
          <div class="code-block position-relative"><button class="copy-btn" onclick="copyCode(this)">Copy</button><span class="mp">POST</span> <?= htmlspecialchars($apiUrl) ?>?action=order_vps
<span class="k">X-Api-Key:</span> <span class="v">rk_your_key_here</span>
<span class="k">Content-Type:</span> <span class="v">application/json</span>

{
  "<span class="k">product_id</span>":    <span class="v">201</span>,
  "<span class="k">os_id</span>":          <span class="v">1</span>,
  "<span class="k">billing_cycle</span>": <span class="v">"monthly"</span>,
  "<span class="k">hostname</span>":      <span class="v">"my-vps.example.com"</span>
}</div>
          <table class="table param-table table-bordered mt-3">
            <thead><tr><th>Field</th><th>Type</th><th>จำเป็น</th><th>คำอธิบาย</th></tr></thead>
            <tbody>
              <tr><td><code>product_id</code></td><td>integer</td><td><span class="text-danger fw-bold">✱ Required</span></td><td>ID แพ็กเกจ VPS จาก <code>action=vps_packages</code></td></tr>
              <tr><td><code>os_id</code></td><td>integer</td><td><span class="text-danger fw-bold">✱ Required</span></td><td>ID ระบบปฏิบัติการจาก <code>os_options</code></td></tr>
              <tr><td><code>billing_cycle</code></td><td>string</td><td><span class="text-muted">Optional</span></td><td><code>monthly</code> (default) หรือ <code>yearly</code></td></tr>
              <tr><td><code>hostname</code></td><td>string</td><td><span class="text-muted">Optional</span></td><td>Hostname เช่น <code>vps.example.com</code></td></tr>
            </tbody>
          </table>
          <div class="r-ok"><strong>&#10003; Response 201:</strong>
            <div class="code-block mt-2">{ "<span class="k">ok</span>": <span class="v">true</span>, "<span class="k">data</span>": { "<span class="k">order_id</span>": <span class="v">1000</span>, "<span class="k">order_no</span>": <span class="v">"VXXXXXXXXXX"</span>, "<span class="k">invoice_id</span>": <span class="v">556</span>, "<span class="k">amount</span>": <span class="v">199.00</span>, "<span class="k">status</span>": <span class="v">"pending"</span> } }</div>
          </div>
        </div>
      </div>

      <!-- RENEW -->
      <div class="api-section" id="renew">
        <div class="api-section-header">
          <i class="bi bi-arrow-clockwise text-success fs-5"></i>
          <h4 class="mb-0 fw-bold">Renew Hosting — ต่ออายุโฮสติ้ง</h4>
          <span class="badge-post ms-auto">POST</span>
          <span class="scope-b ms-2"><i class="bi bi-arrow-clockwise"></i> renew</span>
        </div>
        <div class="api-section-body">
          <div class="code-block position-relative"><button class="copy-btn" onclick="copyCode(this)">Copy</button><span class="mp">POST</span> <?= htmlspecialchars($apiUrl) ?>?action=renew
<span class="k">X-Api-Key:</span> <span class="v">rk_your_key_here</span>
<span class="k">Content-Type:</span> <span class="v">application/json</span>

{ "<span class="k">service_id</span>": <span class="v">12345</span> }</div>
          <div class="r-ok mt-3"><strong>&#10003; Response:</strong>
            <div class="code-block mt-2">{ "<span class="k">ok</span>": <span class="v">true</span>, "<span class="k">data</span>": { "<span class="k">new_expiry_date</span>": <span class="v">"2026-10-23"</span> } }</div>
          </div>
        </div>
      </div>

      <!-- RENEW VPS -->
      <div class="api-section" id="renew-vps">
        <div class="api-section-header">
          <i class="bi bi-arrow-repeat text-info fs-5"></i>
          <h4 class="mb-0 fw-bold">Renew VPS — ต่ออายุ Cloud VPS</h4>
          <span class="badge-post ms-auto">POST</span>
          <span class="scope-b ms-2"><i class="bi bi-arrow-clockwise"></i> renew</span>
        </div>
        <div class="api-section-body">
          <div class="code-block position-relative"><button class="copy-btn" onclick="copyCode(this)">Copy</button><span class="mp">POST</span> <?= htmlspecialchars($apiUrl) ?>?action=renew_vps
<span class="k">X-Api-Key:</span> <span class="v">rk_your_key_here</span>
<span class="k">Content-Type:</span> <span class="v">application/json</span>

{ "<span class="k">service_id</span>": <span class="v">99</span> }</div>
          <div class="r-ok mt-3"><strong>&#10003; Response:</strong>
            <div class="code-block mt-2">{ "<span class="k">ok</span>": <span class="v">true</span>, "<span class="k">data</span>": { "<span class="k">new_expiry_date</span>": <span class="v">"2026-10-23"</span> } }</div>
          </div>
        </div>
      </div>

      <!-- REPORTS -->
      <div class="api-section" id="reports">
        <div class="api-section-header">
          <i class="bi bi-chat-square-text text-primary fs-5"></i>
          <h4 class="mb-0 fw-bold">Hosting Reports — ระบบแจ้งปัญหา</h4>
          <span class="badge-post ms-auto">POST</span>
          <span class="badge-get ms-2">GET</span>
          <span class="scope-b ms-2"><i class="bi bi-headset"></i> support</span>
          <span class="scope-b ms-2"><i class="bi bi-eye"></i> read</span>
        </div>
        <div class="api-section-body">
          <h5 class="fw-bold mb-3"><i class="bi bi-send text-primary me-2"></i>ส่งรายงานแจ้งปัญหาการใช้งาน (Hosting / VPS)</h5>
          <p class="small text-muted mb-3"><i class="bi bi-info-circle me-1"></i>ส่งเป็น <code>application/json</code> โดยระบุข้อมูลดังนี้:</p>
          <ul class="small mb-3">
            <li><code>service_id</code> <em>(int)</em>: รหัสบริการ Hosting หรือ VPS ที่ต้องการแจ้งปัญหา</li>
            <li><code>category</code> <em>(string)</em>: หมวดหมู่ปัญหาที่ให้เลือก
              <ul>
                <li><code>down</code> = Hosting เข้าไม่ได้ / Connection Error</li>
                <li><code>website</code> = เว็บไซต์มีปัญหา / Error 500</li>
                <li><code>database</code> = Database มีปัญหา / เชื่อมต่อไม่ได้</li>
                <li><code>email_dns_ssl</code> = Email / DNS / SSL มีปัญหา</li>
                <li><code>server_resource</code> = Server Load สูง / Disk เต็ม</li>
                <li><code>other</code> = อื่น ๆ</li>
              </ul>
            </li>
            <li><code>description</code> <em>(string)</em>: รายละเอียดปัญหาเบื้องต้น</li>
          </ul>
          <div class="code-block position-relative"><button class="copy-btn" onclick="copyCode(this)">Copy</button><span class="mp">POST</span> <?= htmlspecialchars($apiUrl) ?>?action=report_hosting
<span class="k">X-Api-Key:</span> <span class="v">rk_your_key_here</span>
<span class="k">Content-Type:</span> <span class="v">application/json</span>

{
  "<span class="k">service_id</span>": <span class="v">12</span>,
  "<span class="k">category</span>": <span class="v">"website"</span>,
  "<span class="k">description</span>": <span class="v">"หน้าเว็บขึ้น Error 500 หลังจากอัปเดตปลั๊กอินครับ รบกวนตรวจสอบให้ทีครับ"</span>
}</div>
          <div class="r-ok mt-3"><strong>&#10003; Response 201:</strong>
            <div class="code-block mt-2">{ "<span class="k">ok</span>": <span class="v">true</span>, "<span class="k">data</span>": { "<span class="k">report_id</span>": <span class="v">5</span>, "<span class="k">report_no</span>": <span class="v">"REP000005"</span>, "<span class="k">status</span>": <span class="v">"reported"</span>, "<span class="k">message</span>": <span class="v">"ส่งรายงานปัญหาเรียบร้อยแล้ว"</span> } }</div>
          </div>
          
          <hr class="my-5">
          
          <h5 class="fw-bold mb-3"><i class="bi bi-list-ul text-info me-2"></i>ดูรายการแจ้งปัญหาทั้งหมด</h5>
          <p class="small text-muted mb-3"><i class="bi bi-info-circle me-1"></i>สามารถระบุพารามิเตอร์ <code>&amp;status=</code> เพื่อกรองเฉพาะสถานะที่ต้องการ (reported, acknowledged, in_progress, resolved, closed)</p>
          <div class="code-block position-relative"><button class="copy-btn" onclick="copyCode(this)">Copy</button><span class="c"># ดูรายการแจ้งปัญหาทั้งหมด</span>
<span class="mg">GET</span> <?= htmlspecialchars($apiUrl) ?>?action=reports
<span class="k">X-Api-Key:</span> <span class="v">rk_your_key_here</span>

<span class="c"># กรองเฉพาะสถานะ</span>
<span class="mg">GET</span> <?= htmlspecialchars($apiUrl) ?>?action=reports&amp;status=reported
<span class="k">X-Api-Key:</span> <span class="v">rk_your_key_here</span></div>
          <div class="r-ok mt-3"><strong>&#10003; Response:</strong>
            <div class="code-block mt-2">{ "<span class="k">ok</span>": <span class="v">true</span>, "<span class="k">count</span>": <span class="v">1</span>, "<span class="k">data</span>": [ { "<span class="k">id</span>": <span class="v">1</span>, "<span class="k">report_no</span>": <span class="v">"REP000001"</span>, "<span class="k">service_id</span>": <span class="v">12</span>, "<span class="k">category</span>": <span class="v">"website"</span>, "<span class="k">status</span>": <span class="v">"reported"</span>, "<span class="k">created_at</span>": <span class="v">"2026-08-23 12:00:00"</span>, "<span class="k">updated_at</span>": <span class="v">"2026-08-23 12:00:00"</span> } ] }</div>
          </div>
          
          <hr class="my-5">
          
          <h5 class="fw-bold mb-3"><i class="bi bi-search text-secondary me-2"></i>ดูรายละเอียดแจ้งปัญหา (Report) แบบเจาะจง</h5>
          <div class="code-block position-relative"><button class="copy-btn" onclick="copyCode(this)">Copy</button><span class="mg">GET</span> <?= htmlspecialchars($apiUrl) ?>?action=report&amp;id=1
<span class="k">X-Api-Key:</span> <span class="v">rk_your_key_here</span></div>
          <div class="r-ok mt-3"><strong>&#10003; Response:</strong>
            <div class="code-block mt-2">{ "<span class="k">ok</span>": <span class="v">true</span>, "<span class="k">data</span>": { "<span class="k">id</span>": <span class="v">1</span>, "<span class="k">report_no</span>": <span class="v">"REP000001"</span>, "<span class="k">user_id</span>": <span class="v">42</span>, "<span class="k">service_id</span>": <span class="v">12</span>, "<span class="k">category</span>": <span class="v">"website"</span>, "<span class="k">description</span>": <span class="v">"หน้าเว็บขึ้น Error 500 ครับ"</span>, "<span class="k">status</span>": <span class="v">"reported"</span>, "<span class="k">created_at</span>": <span class="v">"2026-08-23 12:00:00"</span>, "<span class="k">updated_at</span>": <span class="v">"2026-08-23 12:00:00"</span> } }</div>
          </div>
        </div>
      </div>

      <!-- ERROR CODES -->
      <div class="api-section" id="errors">
        <div class="api-section-header">
          <i class="bi bi-exclamation-triangle text-danger fs-5"></i>
          <h4 class="mb-0 fw-bold">Error Codes — รหัสข้อผิดพลาด</h4>
        </div>
        <div class="api-section-body">
          <table class="table param-table table-bordered mb-0">
            <thead><tr><th>HTTP</th><th>ความหมาย</th><th>วิธีแก้ไข</th></tr></thead>
            <tbody>
              <tr><td><span class="badge bg-success">200</span></td><td>สำเร็จ</td><td>—</td></tr>
              <tr><td><span class="badge bg-success">201</span></td><td>สร้างรายการใหม่สำเร็จ (order)</td><td>—</td></tr>
              <tr><td><span class="badge bg-warning text-dark">400</span></td><td>Bad Request — ข้อมูลที่ส่งไปไม่ถูกต้อง</td><td>ตรวจสอบ Required fields และ format</td></tr>
              <tr><td><span class="badge bg-danger">401</span></td><td>Unauthorized — API Key ไม่ถูกต้องหรือขาดหาย</td><td>ตรวจสอบ Header <code>X-Api-Key</code></td></tr>
              <tr><td><span class="badge bg-danger">403</span></td><td>Forbidden — scope ไม่ครอบคลุม หรือ IP ไม่อยู่ใน whitelist</td><td>เปิด Scope ที่จำเป็น หรือเพิ่ม IP ใน whitelist</td></tr>
              <tr><td><span class="badge bg-warning text-dark">404</span></td><td>Not Found — ไม่พบ Resource ที่ระบุ</td><td>ตรวจสอบ ID ที่ส่งไป</td></tr>
              <tr><td><span class="badge bg-warning text-dark">405</span></td><td>Method Not Allowed — HTTP Method ไม่ถูกต้อง</td><td>ใช้ GET หรือ POST ให้ถูกต้อง</td></tr>
              <tr><td><span class="badge bg-danger">429</span></td><td>Too Many Requests — เกิน Rate Limit (120 req/min)</td><td>ดู Header <code>Retry-After</code> แล้วรอ 1 นาที</td></tr>
              <tr><td><span class="badge bg-secondary">500</span></td><td>Server Error — ระบบ API มีปัญหา</td><td>รอสักครู่แล้วลองใหม่หรือติดต่อ Support</td></tr>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </div>
</div>

<script>
function copyCode(btn) {
    const block = btn.closest('.code-block');
    let text = (block.innerText || block.textContent).replace(/^Copy\n?/,'').trim();
    navigator.clipboard.writeText(text).then(() => {
        btn.textContent = 'Copied!';
        btn.style.color = '#4ade80';
        setTimeout(() => { btn.textContent = 'Copy'; btn.style.color = ''; }, 2000);
    });
}
const obs = new IntersectionObserver(entries => {
    entries.forEach(e => {
        if(e.isIntersecting){
            document.querySelectorAll('.sidebar-nav .nav-link').forEach(l=>l.classList.remove('active'));
            const l = document.querySelector('.sidebar-nav a[href="#'+e.target.id+'"]');
            if(l) l.classList.add('active');
        }
    });
}, {rootMargin:'-20% 0px -70% 0px'});
document.querySelectorAll('.api-section[id]').forEach(s=>obs.observe(s));
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
