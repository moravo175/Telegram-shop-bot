<?php
/**
 * کلاس درگاه پرداخت زرین‌پال
 * API v4 - REST
 */
class ZarinPal
{
    private string $merchantId;
    private bool $sandbox;
    private string $requestUrl;
    private string $verifyUrl;
    private string $gatewayUrl;

    public function __construct(string $merchantId, bool $sandbox = false)
    {
        $this->merchantId = $merchantId;
        $this->sandbox    = $sandbox;

        if ($sandbox) {
            $this->requestUrl = ZARINPAL_SANDBOX_REQUEST;
            $this->verifyUrl  = ZARINPAL_SANDBOX_VERIFY;
            $this->gatewayUrl = ZARINPAL_SANDBOX_GATEWAY;
        } else {
            $this->requestUrl = ZARINPAL_REQUEST_URL;
            $this->verifyUrl  = ZARINPAL_VERIFY_URL;
            $this->gatewayUrl = ZARINPAL_GATEWAY_URL;
        }
    }

    /**
     * ارسال درخواست پرداخت
     * مبلغ به تومان
     */
    public function request(int $amount, string $callbackUrl, string $description, ?string $mobile = null, ?string $email = null): array
    {
        // زرین‌پال مبلغ را به ریال می‌خواهد
        $amountRial = $amount * 10;

        $data = [
            'merchant_id'  => $this->merchantId,
            'amount'       => $amountRial,
            'callback_url' => $callbackUrl,
            'description'  => $description,
        ];
        if ($mobile) $data['metadata']['mobile'] = $mobile;
        if ($email)  $data['metadata']['email']  = $email;

        $response = $this->callApi($this->requestUrl, $data);

        if (isset($response['data']['authority']) && $response['data']['code'] == 100) {
            return [
                'success'   => true,
                'authority' => $response['data']['authority'],
                'url'       => $this->gatewayUrl . $response['data']['authority'],
            ];
        }

        return [
            'success' => false,
            'error'   => $response['errors']['message'] ?? ($response['errors']['validations'][0]['message'] ?? 'خطای ناشناخته'),
            'code'    => $response['errors']['code'] ?? -1,
        ];
    }

    /**
     * تأیید پرداخت
     */
    public function verify(string $authority, int $amount): array
    {
        $amountRial = $amount * 10;

        $data = [
            'merchant_id' => $this->merchantId,
            'authority'   => $authority,
            'amount'      => $amountRial,
        ];

        $response = $this->callApi($this->verifyUrl, $data);

        if (isset($response['data']['code']) && in_array($response['data']['code'], [100, 101])) {
            return [
                'success'    => true,
                'ref_id'     => (string)$response['data']['ref_id'],
                'code'       => $response['data']['code'],
                'duplicate'  => $response['data']['code'] == 101,
                'card_pan'   => $response['data']['card_pan'] ?? '',
                'card_hash'  => $response['data']['card_hash'] ?? '',
            ];
        }

        return [
            'success' => false,
            'error'   => $response['errors']['message'] ?? 'تأیید پرداخت ناموفق بود',
            'code'    => $response['errors']['code'] ?? -1,
        ];
    }

    /**
     * ارسال درخواست به API زرین‌پال
     */
    private function callApi(string $url, array $data): array
    {
        $jsonData = json_encode($data);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_USERAGENT      => 'ZarinPal Rest Api v4',
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_POSTFIELDS     => $jsonData,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($jsonData),
                'Accept: application/json',
            ],
        ]);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($result === false) {
            return ['errors' => ['message' => 'خطا در اتصال به زرین‌پال', 'code' => -99]];
        }

        $decoded = json_decode($result, true);
        return $decoded ?? ['errors' => ['message' => 'پاسخ نامعتبر از زرین‌پال', 'code' => -98]];
    }

    /**
     * ایجاد نمونه از روی تنظیمات دیتابیس
     */
    public static function fromSettings(Database $db): ?self
    {
        $merchant = $db->getSetting('zarinpal_merchant');
        $active   = $db->getSetting('zarinpal_active');
        $sandbox  = $db->getSetting('zarinpal_sandbox') == '1';

        if (!$active || !$merchant) return null;

        return new self($merchant, $sandbox);
    }
}
