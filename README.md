# ระบบเช่าโฮสติ้งและ VPS เชื่อมต่อ Reseller API (DirectAdmin Ready)

**พัฒนาโดย NAMICH-CLOUD**

ระบบเว็บให้บริการเช่าเว็บโฮสติ้ง (DirectAdmin Hosting) และคลาวด์เซิร์ฟเวอร์ (VPS) พัฒนาด้วย PHP และ MySQL ออกแบบมาให้มีโครงสร้างไฟล์เรียบง่าย พร้อมติดตั้งและรันบน **DirectAdmin Web Hosting** หรือ Shared Hosting ทั่วไปได้ทันที
<img width="1468" height="847" alt="image" src="https://github.com/user-attachments/assets/45002c46-3142-46ef-96dd-8554aacffda7" />

---

## 🌟 คุณสมบัติเด่นของระบบ

1. **เชื่อมต่อ Nami-CH Reseller API ครบทุก Endpoint**:
   - ดึงข้อมูลหมวดหมู่และแพ็กเกจ Hosting (`?action=categories`, `?action=packages`)
   - ดึงแพ็กเกจ VPS และตัวเลือกระบบปฏิบัติการ OS (`?action=vps_packages`)
   - สั่งซื้อโฮสติ้งและสร้างบัญชี DirectAdmin อัตโนมัติ (`POST ?action=order_hosting`)
   - สั่งซื้อคลาวด์ VPS และติดตั้ง OS อัตโนมัติ (`POST ?action=order_vps`)
   - ตรวจสอบสถานะการใช้งานจริง เช่น Disk Usage %, Bandwidth Usage %, IP, Nameservers (`?action=service`, `?action=vps_service`)
   - กดต่ออายุบริการ (Renew) แบบ Real-time (`POST ?action=renew`, `POST ?action=renew_vps`)
   - ตรวจสอบเครดิตคงเหลือในบัญชี Reseller (`?action=balance`)

2. **ระบบสมาชิกและกระเป๋าเงิน (User & Wallet)**:
   - ระบบสมัครสมาชิก, เข้าสู่ระบบ, เปลี่ยนรหัสผ่าน, จัดการโปรไฟล์
   - กระเป๋าเงินอิเล็กทรอนิกส์ (Wallet Balance)
   - **ระบบเติมเงิน TrueMoney Wallet (ซองของขวัญ / อั่งเปา) อัตโนมัติ 24 ชม.** ผ่าน API `api.xpluem.com` เงินเข้าทันที
   - ระบบเติมเงินผ่าน Thai QR PromptPay และโอนผ่านธนาคาร พร้อมระบบแนบสลิป
   - ประวัติการเงินและคำสั่งซื้อ (Transactions History) ละเอียดครบถ้วน

3. **ระบบผู้ดูแลระบบ (Admin Control Center)**:
   - ตรวจสอบยอดเครดิต Reseller API แบบ Real-time
   - **ระบบจัดการราคา กำไร และแพ็กเกจ (Package Pricing Control)**:
     - ตั้งสูตรบวกกำไรส่วนกลาง (Global Markup % หรือจำนวนเงินคงที่)
     - ปรับแต่งราคาเฉพาะแพ็กเกจ: เลือกระหว่างบวกกำไรเฉพาะ (+% +บาท) หรือตั้งราคาขายคงที่โดยตรง
     - เปิด/ปิดการขาย (Active/Inactive) เพื่อซ่อนแพ็กเกจที่ไม่ต้องการ
     - กำหนดป้ายแนะนำ (Featured Badge) เช่น "ยอดนิยม", "ขายดีที่สุด"
     - เปลี่ยนชื่อแพ็กเกจที่แสดงหน้าเว็บได้ตามต้องการ
   - จัดการสมาชิก ปรับเพิ่ม/ลดเครดิต กำหนดสิทธิ์ Admin/User
   - ตรวจสอบสลิปการโอนเงินและกดอนุมัติเพื่อเติมเครดิตให้อัตโนมัติ
   - ดูรายการบริการของลูกค้าทั้งหมด

4. **ดีไซน์โมเดิร์น & Responsive**:
   - ใช้ Bootstrap 5.3 + Font Prompt/Inter ทันสมัย รองรับทั้งมือถือและคอมพิวเตอร์

---

## 🚀 ขั้นตอนการติดตั้งบน DirectAdmin

### ขั้นตอนที่ 1: สร้างฐานข้อมูล MySQL
1. เข้าสู่ระบบ DirectAdmin
2. ไปที่เมนู **MySQL Management** (การจัดการ MySQL)
3. คลิก **Create New Database** (สร้างฐานข้อมูลใหม่)
4. กำหนดชื่อฐานข้อมูล, ชื่อผู้ใช้ (Database User) และรหัสผ่าน (Password)

### ขั้นตอนที่ 2: อัปโหลดไฟล์เข้าสู่โฮสติ้ง
1. อัปโหลดไฟล์ทั้งหมดในโปรเจกต์นี้เข้าไปในโฟลเดอร์ `public_html` บน DirectAdmin (ผ่าน File Manager หรือ FTP)

### ขั้นตอนที่ 3: แก้ไขไฟล์ `config.php`
เปิดไฟล์ `config.php` และระบุข้อมูลฐานข้อมูล:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'ชื่อฐานข้อมูลที่คุณสร้าง');
define('DB_USER', 'ชื่อผู้ใช้ฐานข้อมูล');
define('DB_PASS', 'รหัสผ่านฐานข้อมูล');
```

> **หมายเหตุ**: ระบบมีระบบ **Auto-Migration** จะสร้างตารางในฐานข้อมูลและบัญชี Admin เริ่มต้นให้อัตโนมัติเมื่อเปิดหน้าเว็บครั้งแรก หรือสามารถนำเข้าไฟล์ `schema.sql` ผ่าน phpMyAdmin ได้เช่นกัน

### ขั้นตอนที่ 4: เข้าสู่ระบบ Admin และใส่ API Key
1. เปิดหน้าเว็บของคุณ เช่น `https://yourdomain.com/login.php`
2. เข้าสู่ระบบด้วยบัญชี Admin เริ่มต้น:
   - **Username**: `admin`
   - **Password**: `password123`
3. ไปที่เมนู **ระบบจัดการหลังบ้าน (Admin)** &rarr; **ตั้งค่าระบบ & API Key** (`admin/settings.php`)
4. ใส่ **Reseller API Key** (`rk_...`) ที่ได้จาก [https://nami-ch.com/api-keys.php](https://nami-ch.com/api-keys.php)
5. ตั้งค่าชื่อเว็บไซต์ หมายเลขพร้อมเพย์ และอัตรากำไร (Markup %) ตามต้องการ
6. อย่าลืมเปลี่ยนรหัสผ่านของบัญชี admin ในหน้า **ข้อมูลส่วนตัว** เพื่อความปลอดภัย

---

## 📁 โครงสร้างไฟล์ในระบบ

```
├── config.php              # การตั้งค่าฐานข้อมูล, API และเว็บไซต์
├── db.php                  # PDO Connection และ Auto DB Schema Creator
├── api_helper.php          # NamiResellerAPI Client Class
├── functions.php           # ฟังก์ชันช่วยเหลือและคำนวณราคา
├── schema.sql              # โครงสร้างตารางฐานข้อมูล MySQL
├── .htaccess               # การตั้งค่าความปลอดภัย Apache/DirectAdmin
│
├── index.php               # หน้าแรก แสดงแพ็กเกจเด่นและจุดเด่น
├── packages.php            # หน้ารวมแพ็กเกจ Hosting ทั้งหมด
├── vps.php                 # หน้ารวมแพ็กเกจ VPS และ OS
├── order_hosting.php       # สั่งซื้อโฮสติ้ง DirectAdmin
├── order_vps.php           # สั่งซื้อ Cloud VPS
├── services.php            # รายการบริการของฉัน
├── service_detail.php      # รายละเอียดบริการ / ข้อมูล DA / ปุ่มต่ออายุ
├── topup.php               # เติมเงิน Wallet (PromptPay QR & แนบสลิป)
├── transactions.php        # ประวัติรายการเงิน
│
├── login.php               # เข้าสู่ระบบ
├── register.php            # สมัครสมาชิก
├── logout.php              # ออกจากระบบ
├── profile.php             # ข้อมูลส่วนตัวและเปลี่ยนรหัสผ่าน
│
├── includes/
│   ├── header.php          # Header และ Navbar
│   └── footer.php          # Footer และ Scripts
│
├── admin/
│   ├── index.php           # หน้าภาพรวมระบบและเช็ก API Balance
│   ├── settings.php        # ตั้งค่า API Key, ธนาคาร, ข้อมูลเว็บ
│   ├── pricing.php         # จัดการราคาบวกกำไร (Markup)
│   ├── users.php           # จัดการสมาชิกและปรับเครดิต
│   ├── topups.php          # ตรวจสอบและอนุมัติสลิปโอนเงิน
│   ├── services.php        # ดูรายการบริการของลูกค้าทั้งหมด
│   ├── header.php          # Header ของแอดมิน
│   └── footer.php          # Footer ของแอดมิน
│
├── assets/
│   ├── css/style.css       # ดีไซน์และชุดตกแต่ง UI
│   └── js/main.js          # JavaScript และฟังก์ชันคัดลอก/คำนวณ
│
└── uploads/
    └── slips/              # โฟลเดอร์เก็บรูปภาพสลิปที่ลูกค้าแนบ
```
