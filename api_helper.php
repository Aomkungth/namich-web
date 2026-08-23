<?php
/**
 * Nami-CH Reseller API Client Helper
 * สำหรับเชื่อมต่อกับระบบ Reseller API เพื่อจัดการ Hosting, VPS, Invoices และ Balance
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

class NamiResellerAPI {
    private $apiKey;
    private $baseUrl;
    private $timeout;

    public function __construct($apiKey = null, $baseUrl = null, $timeout = 25) {
        $this->baseUrl = rtrim($baseUrl ?: getSetting('reseller_api_url', RESELLER_API_BASE_URL), '/') . '/';
        $this->apiKey = $apiKey ?: getSetting('reseller_api_key', DEFAULT_RESELLER_API_KEY);
        $this->timeout = $timeout;
    }

    /**
     * ส่ง HTTP Request ไปยัง Reseller API
     * @param string $action เช่น 'balance', 'packages', 'order_hosting'
     * @param string $method 'GET' หรือ 'POST'
     * @param array $params พารามิเตอร์ Query (GET) หรือ Data Body (POST)
     * @param bool $requiresAuth ระบุว่าต้องการบังคับ API Key หรือไม่
     * @return array [ 'ok' => bool, 'data' => mixed, 'error' => string, 'raw' => string ]
     */
    public function request($action, $method = 'GET', $params = [], $requiresAuth = true) {
        $url = $this->baseUrl . '?action=' . urlencode($action);

        $headers = [
            'Accept: application/json, text/plain, */*',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
        ];

        // ส่ง API Key เสมอหากมีการตั้งค่าไว้
        if (!empty($this->apiKey) && $this->apiKey !== 'rk_your_key_here') {
            $headers[] = 'X-Api-Key: ' . $this->apiKey;
        } elseif ($requiresAuth) {
            return [
                'ok'    => false,
                'error' => 'กรุณาตั้งค่า Reseller API Key ในระบบก่อนใช้งาน',
                'code'  => 401,
            ];
        }

        $ch = curl_init();

        if (strtoupper($method) === 'GET') {
            if (!empty($params)) {
                $url .= '&' . http_build_query($params);
            }
        } elseif (strtoupper($method) === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        }

        // -------------------------------------------------------
        // Loopback detection
        // เว็บไซต์รันบนเซิร์ฟเวอร์เดียวกับ Reseller API
        // curl ออกไปแล้วกลับมาหาตัวเองจะโดน 403 จาก Firewall/Apache
        // แก้ด้วยการ resolve domain → 127.0.0.1 พร้อมส่ง Host header
        // -------------------------------------------------------
        $parsedUrl   = parse_url($url);
        $apiHost     = $parsedUrl['host'] ?? '';
        $serverHost  = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
        // เปรียบเทียบ base domain (ตัด www. ออก)
        $stripWww = fn($h) => preg_replace('/^www\./i', '', strtolower($h));
        $isLoopback  = ($apiHost !== '' && $stripWww($apiHost) === $stripWww($serverHost));

        $curlOptions = [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ];

        if ($isLoopback) {
            // Resolve domain → 127.0.0.1 เพื่อหลีกเลี่ยง external DNS round-trip
            // และเพิ่ม Host header ให้ Apache/Nginx รู้ว่า virtual host ไหน
            $port = $parsedUrl['port'] ?? (($parsedUrl['scheme'] ?? 'http') === 'https' ? 443 : 80);
            $curlOptions[CURLOPT_RESOLVE] = ["{$apiHost}:{$port}:127.0.0.1"];
            $headers[] = 'Host: ' . $apiHost;
            $curlOptions[CURLOPT_HTTPHEADER] = $headers;
        }

        curl_setopt_array($ch, $curlOptions);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            return [
                'ok' => false,
                'error' => 'cURL Error: ' . $curlError,
                'http_code' => $httpCode
            ];
        }

        $result = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            // response ไม่ใช่ JSON — แปล HTTP code เป็น error ที่อ่านออก
            $httpMessages = [
                401 => 'API Key ไม่ถูกต้องหรือหมดอายุ (HTTP 401)',
                403 => 'API Key ไม่มีสิทธิ์เข้าถึง หรือ IP ถูกบล็อก (HTTP 403) — กรุณาตรวจสอบ API Key และ IP Whitelist ในการตั้งค่า',
                404 => 'ไม่พบ Endpoint ที่เรียก (HTTP 404)',
                429 => 'เกิน Rate Limit ของ Reseller API (HTTP 429) — กรุณารอสักครู่',
                500 => 'Reseller API มีปัญหาภายใน (HTTP 500) — กรุณาลองใหม่ภายหลัง',
                502 => 'Reseller API ไม่ตอบสนอง (HTTP 502)',
                503 => 'Reseller API ไม่พร้อมให้บริการชั่วคราว (HTTP 503)',
            ];
            $errMsg = $httpMessages[$httpCode]
                   ?? 'Response ไม่ถูกต้องจาก Server (HTTP ' . $httpCode . ')';

            return [
                'ok'        => false,
                'error'     => $errMsg,
                'raw'       => substr($response, 0, 500), // จำกัด raw เพื่อความปลอดภัย
                'http_code' => $httpCode,
            ];
        }

        // ถ้า response เป็น JSON แต่ไม่มี 'ok' key — ให้เพิ่ม http_code ไว้ด้วย
        if (!isset($result['http_code'])) {
            $result['http_code'] = $httpCode;
        }

        return $result;
    }

    // ==========================================
    // Package & Category Endpoints
    // ==========================================

    /**
     * ดึงหมวดหมู่แพ็กเกจ Hosting ทั้งหมด
     */
    public function getCategories() {
        return $this->request('categories', 'GET', [], true);
    }

    /**
     * ดึงรายการแพ็กเกจ Hosting ทั้งหมด หรือแยกตาม slug หมวดหมู่
     */
    public function getPackages($categorySlug = null) {
        $params = [];
        if (!empty($categorySlug)) {
            $params['category'] = $categorySlug;
        }
        return $this->request('packages', 'GET', $params, true);
    }

    /**
     * ดึงรายการแพ็กเกจ VPS และตัวเลือกระบบปฏิบัติการ (OS options)
     */
    public function getVPSPackages() {
        return $this->request('vps_packages', 'GET', [], true);
    }

    // ==========================================
    // Read Endpoints (ต้องการ API Key, Scope: read)
    // ==========================================

    /**
     * ดูยอดเครดิตคงเหลือในบัญชี Reseller
     */
    public function getBalance() {
        return $this->request('balance', 'GET', [], true);
    }

    /**
     * ดูรายการ Hosting Services ทั้งหมดในบัญชี Reseller
     */
    public function getServices() {
        return $this->request('services', 'GET', [], true);
    }

    /**
     * ดูรายละเอียด Hosting Service รายการเดียว
     */
    public function getService($serviceId) {
        return $this->request('service', 'GET', ['id' => (int)$serviceId], true);
    }

    /**
     * ดูรายการ VPS Services ทั้งหมด
     */
    public function getVPS() {
        return $this->request('vps', 'GET', [], true);
    }

    /**
     * ดูรายละเอียด VPS Service รายการเดียว
     */
    public function getVPSService($serviceId) {
        return $this->request('vps_service', 'GET', ['id' => (int)$serviceId], true);
    }

    /**
     * ดูรายการใบแจ้งหนี้ Invoices
     * @param string|null $status unpaid, paid, cancelled, refunded
     */
    public function getInvoices($status = null) {
        $params = [];
        if (!empty($status)) {
            $params['status'] = $status;
        }
        return $this->request('invoices', 'GET', $params, true);
    }

    /**
     * ดูรายละเอียดใบแจ้งหนี้เดียว
     */
    public function getInvoice($invoiceId) {
        return $this->request('invoice', 'GET', ['id' => (int)$invoiceId], true);
    }

    /**
     * ดูรายการแจ้งปัญหา (Hosting Reports) ทั้งหมด
     * @param string|null $status reported, acknowledged, in_progress, resolved, closed
     */
    public function getReports($status = null) {
        $params = [];
        if (!empty($status)) {
            $params['status'] = $status;
        }
        return $this->request('reports', 'GET', $params, true);
    }

    /**
     * ดูรายละเอียดแจ้งปัญหาเดียว
     */
    public function getReport($reportId) {
        return $this->request('report', 'GET', ['id' => (int)$reportId], true);
    }

    // ==========================================
    // Order Endpoints (ต้องการ API Key, Scope: order)
    // ==========================================

    /**
     * สั่งซื้อ Hosting ใหม่
     * @param int $productId ID แพ็กเกจจาก ?action=packages
     * @param string $domain ชื่อโดเมน เช่น example.com
     * @param string $username username DirectAdmin (a-z0-9 ยาว 4-16 ตัว เริ่มด้วยตัวอักษร)
     * @param string $password รหัสผ่าน (อย่างน้อย 8 ตัว)
     * @param string $billingCycle 'monthly' หรือ 'yearly'
     */
    public function orderHosting($productId, $domain, $username, $password, $billingCycle = 'monthly') {
        $payload = [
            'product_id'    => (int)$productId,
            'domain'        => trim($domain),
            'username'      => trim($username),
            'password'      => (string)$password,
            'billing_cycle' => in_array($billingCycle, ['monthly', 'yearly']) ? $billingCycle : 'monthly'
        ];
        return $this->request('order_hosting', 'POST', $payload, true);
    }

    /**
     * แจ้งปัญหาการใช้งาน (Hosting / VPS)
     * @param int $serviceId รหัสบริการ Hosting/VPS
     * @param string $category หมวดหมู่ปัญหา
     * @param string $description รายละเอียดปัญหา
     */
    public function reportHosting($serviceId, $category, $description) {
        $payload = [
            'service_id'  => (int)$serviceId,
            'category'    => trim($category),
            'description' => trim($description)
        ];
        return $this->request('report_hosting', 'POST', $payload, true);
    }

    /**
     * สั่งซื้อ VPS ใหม่
     * @param int $productId ID แพ็กเกจ VPS จาก ?action=vps_packages
     * @param int $osId ID ระบบปฏิบัติการจาก ?action=vps_packages (os_options)
     * @param string $billingCycle 'monthly' หรือ 'yearly'
     * @param string|null $hostname hostname (ไม่บังคับ)
     */
    public function orderVPS($productId, $osId, $billingCycle = 'monthly', $hostname = null) {
        $payload = [
            'product_id'    => (int)$productId,
            'os_id'         => (int)$osId,
            'billing_cycle' => in_array($billingCycle, ['monthly', 'yearly']) ? $billingCycle : 'monthly'
        ];
        if (!empty($hostname)) {
            $payload['hostname'] = trim($hostname);
        }
        return $this->request('order_vps', 'POST', $payload, true);
    }

    // ==========================================
    // Renew Endpoints (ต้องการ API Key, Scope: renew)
    // ==========================================

    /**
     * ต่ออายุ Hosting Service
     * @param int $serviceId ID ของ Hosting service ใน Reseller API
     */
    public function renewHosting($serviceId) {
        return $this->request('renew', 'POST', ['service_id' => (int)$serviceId], true);
    }

    /**
     * ต่ออายุ VPS Service
     * @param int $serviceId ID ของ VPS service ใน Reseller API
     */
    public function renewVPS($serviceId) {
        return $this->request('renew_vps', 'POST', ['service_id' => (int)$serviceId], true);
    }
}
