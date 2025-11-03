<?php

use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;

if (ini_get('date.timezone') === '') {
    date_default_timezone_set('Asia/Jakarta');
} else {
    date_default_timezone_set(ini_get('date.timezone'));
}

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} elseif (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
} else {
    die('Autoloader not found.');
}

/**
 * Tripay FOSSBilling Integration.
 *
 * @property mixed $apiId
 * @author Cak Adi <cakadi190@gmail.com>
 *
 */
class Payment_Adapter_Tripay implements \FOSSBilling\InjectionAwareInterface
{
    protected ?Pimple\Container $di = null;
    private array $config = [];
    private Logger $logger;

    public function __construct($config)
    {
        $this->config = $config;

        $apiKey = $this->getApiKey();
        $privateKey = $this->getPrivateKey();
        $merchantCode = $this->getMerchantCode();

        if (empty($apiKey)) {
            throw new Payment_Exception('The ":pay_gateway" payment gateway is not fully configured. Please configure the :missing', [':pay_gateway' => 'Tripay', ':missing' => 'API Key']);
        }
        if (empty($privateKey)) {
            throw new Payment_Exception('The ":pay_gateway" payment gateway is not fully configured. Please configure the :missing', [':pay_gateway' => 'Tripay', ':missing' => 'Private Key']);
        }
        if (empty($merchantCode)) {
            throw new Payment_Exception('The ":pay_gateway" payment gateway is not fully configured. Please configure the :missing', [':pay_gateway' => 'Tripay', ':missing' => 'Merchant Code']);
        }

        $this->initLogger();
    }

    private function initLogger(): void
    {
        $this->logger = new Logger('Tripay');
        $logDir = __DIR__ . '/logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        $this->logger->pushHandler(new RotatingFileHandler($logDir . '/tripay.log', 0, Logger::DEBUG));
    }

    public function setDi(Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?Pimple\Container
    {
        return $this->di;
    }

    public static function getConfig(): array
    {
        return [
            'supports_one_time_payments' => true,
            'description' => 'Configure Tripay API Key, Private Key, and Merchant Code to start accepting payments via Tripay.',
            'logo' => [
                'logo' => 'Tripay.png',
                'height' => '60px',
                'width' => '60px',
            ],
            'form' => [
                'api_key' => [
                    'text',
                    [
                        'label' => 'Tripay API Key',
                        'required' => true,
                    ],
                ],
                'sandbox_api_key' => [
                    'text',
                    [
                        'label' => 'Tripay Sandbox API Key',
                        'required' => false,
                    ],
                ],
                'private_key' => [
                    'text',
                    [
                        'label' => 'Tripay Private Key',
                        'required' => true,
                    ],
                ],
                'sandbox_private_key' => [
                    'text',
                    [
                        'label' => 'Tripay Sandbox Private Key',
                        'required' => false,
                    ],
                ],
                'merchant_code' => [
                    'text',
                    [
                        'label' => 'Merchant Code',
                        'required' => true,
                    ],
                ],
                'sandbox_merchant_code' => [
                    'text',
                    [
                        'label' => 'Sandbox Merchant Code',
                        'required' => false,
                    ],
                ],
                'use_sandbox' => [
                    'radio',
                    [
                        'label' => 'Use Sandbox',
                        'multiOptions' => [
                            '1' => 'Yes',
                            '0' => 'No',
                        ],
                        'required' => true,
                    ],
                ],
                'enable_logging' => [
                    'radio',
                    [
                        'label' => 'Enable Logging',
                        'multiOptions' => [
                            '1' => 'Yes',
                            '0' => 'No',
                        ],
                        'required' => true,
                    ],
                ],
            ],
        ];
    }

    private function getConfigValue($key)
    {
        $prefix = $this->config['use_sandbox'] ? 'sandbox_' : '';
        return $this->config[$prefix . $key] ?? null;
    }

    public function getHtml($api_admin, $invoice_id, $subscription)
    {
        try {
            $invoice = $this->di['db']->getExistingModelById('Invoice', $invoice_id, 'Invoice not found');
            $tripayTransaction = $this->createTripayTransaction($invoice);

            if ($this->config['enable_logging']) {
                $this->logger->info('Tripay transaction created: ' . json_encode($tripayTransaction));
            }

            return $this->generatePaymentForm($tripayTransaction['data']['checkout_url'], $invoice->id);
        } catch (Exception $e) {
            if ($this->config['enable_logging']) {
                $this->logger->error('Error in getHtml: ' . $e->getMessage());
            }
            throw new Payment_Exception('Error processing Tripay payment: ' . $e->getMessage());
        }
    }

    private function generatePaymentForm($checkoutUrl, $invoiceId): string
    {
        $html = '<form id="tripay-payment-form" method="get" action="' . $checkoutUrl . '">';
        $html .= '<input type="hidden" name="merchant_ref" value="' . $invoiceId . '">';
        $html .= '<input type="submit" value="Pay with Tripay" style="display:none;">';
        $html .= '</form>';
        $html .= '<script type="text/javascript">document.getElementById("tripay-payment-form").submit();</script>';
        return $html;
    }


    public function recurrentPayment($api_admin, $id, $data, $gateway_id)
    {
        throw new Payment_Exception('Tripay does not support recurrent payments');
    }

    public function processTransaction($api_admin, $id, $data, $gateway_id)
    {
        try {
            if ($this->config['enable_logging']) {
                $this->logger->info('Processing transaction: ' . json_encode([
                    'id' => $id,
                    'data' => $data,
                    'gateway_id' => $gateway_id
                ]));
            }

            $tripayData = null;
            if (isset($data['http_raw_post_data'])) {
                $tripayData = json_decode($data['http_raw_post_data'], true);
            } elseif (is_string($data)) {
                $tripayData = json_decode($data, true);
            } else {
                $tripayData = $data;
            }

            if (!$tripayData) {
                throw new \Exception('Invalid data received from Tripay');
            }

            $invoice_id = $tripayData['merchant_ref'] ?? null;

            if (!$invoice_id) {
                throw new \Exception('Invalid Tripay callback: missing merchant_ref');
            }

            $this->logger->info('Looking for invoice with ID: ' . $invoice_id);

            $invoice = $this->di['db']->getExistingModelById('Invoice', $invoice_id, 'Invoice not found');

            $tx = $this->di['db']->findOne('Transaction', 'invoice_id = ?', [$invoice_id]);
            if (!$tx) {
                $tx = $this->di['db']->dispense('Transaction');
                $tx->invoice_id = $invoice_id;
                $tx->gateway_id = $gateway_id;
            }

            $clientService = $this->di['mod_service']('Client');
            $client = $clientService->get(['id' => $invoice->client_id]);

            $invoiceService = $this->di['mod_service']('Invoice');
            $invoiceTotal = $invoiceService->getTotalWithTax($invoice);

            $tx_desc = 'Payment Method: ' . ($tripayData['payment_method'] ?? 'Unknown') . ' - ' . ($tripayData['payment_name'] ?? 'Unknown') . ' - ' . 'Ref no: ' . ($tripayData['reference'] ?? 'Unknown');
            $clientService->addFunds($client, $invoiceTotal, $tx_desc, []);

            $invoiceService->markAsPaid($invoice);

            $tx->status = 'complete';
            $tx->txn_id = $tripayData['reference'] ?? null;
            $tx->amount = $invoiceTotal;
            $tx->currency = $invoice->currency;
            $tx->updated_at = date('Y-m-d H:i:s');

            $result = $this->di['db']->store($tx);

            if ($this->config['enable_logging']) {
                $this->logger->info('Transaction processed successfully: ' . $tx->id);
            }

            return $result;
        } catch (\Exception $e) {
            if ($this->config['enable_logging']) {
                $this->logger->error('Error processing transaction: ' . $e->getMessage());
            }
            return false;
        }
    }

    private function handleWebhook($id, $data)
    {
        $rawInput = file_get_contents('php://input');
        $ipn = json_decode($rawInput, true);

        if ($this->config['enable_logging']) {
            $this->logger->info('Tripay webhook raw input: ' . $rawInput);
            $this->logger->info('Tripay webhook decoded: ' . json_encode($ipn));
        }

        // Verify callback signature
        $callbackSignature = $_SERVER['HTTP_X_CALLBACK_SIGNATURE'] ?? '';
        $privateKey = $this->getPrivateKey();

        $calculatedSignature = hash_hmac('sha256', $rawInput, $privateKey);

        if ($callbackSignature !== $calculatedSignature) {
            $this->logger->error('Invalid Tripay callback signature');
            http_response_code(400);
            return false;
        }

        if (!isset($ipn['merchant_ref'])) {
            $this->logger->error('Invalid Tripay callback: missing merchant_ref');
            http_response_code(400);
            return false;
        }

        $invoice_id = $ipn['merchant_ref'];

        try {
            $tx = $this->di['db']->find_one('Transaction', 'invoice_id = ?', [$invoice_id]);
            if (!$tx) {
                $tx = $this->di['db']->dispense('Transaction');
                $tx->invoice_id = $invoice_id;
                $tx->gateway_id = $this->config['id'];
            }

            switch ($ipn['status']) {
                case 'PAID':
                    return $this->processTransaction(null, $invoice_id, $ipn, $this->config['id']);

                case 'EXPIRED':
                    $tx->txn_status = 'expired';
                    $tx->error = 'Tripay payment expired';
                    break;

                case 'UNPAID':
                    $tx->txn_status = 'pending';
                    break;

                case 'FAILED':
                    $tx->txn_status = 'failed';
                    $tx->error = 'Tripay payment failed';
                    break;

                case 'REFUND':
                    $tx->txn_status = 'refunded';
                    $tx->error = 'Tripay payment refunded';
                    break;

                default:
                    $tx->txn_status = 'unknown';
                    $tx->error = 'Unknown Tripay payment status: ' . $ipn['status'];
                    break;
            }

            $tx->updated_at = date('Y-m-d H:i:s');
            $this->di['db']->store($tx);

            if ($this->config['enable_logging']) {
                $this->logger->info('Transaction status updated: ' . $tx->id . ' - ' . $tx->txn_status);
            }

            http_response_code(200);
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Error processing webhook: ' . $e->getMessage());
            http_response_code(500);
            return false;
        }
    }

    private function createTripayTransaction($invoice)
    {
        $invoiceService = $this->di['mod_service']('Invoice');

        if (!$invoice instanceof \Model_Invoice) {
            $invoice = $this->di['db']->getExistingModelById('Invoice', $invoice->id, 'Invoice not found');
        }

        $thankyou_url = $this->di['url']->link('invoice/' . $invoice->hash, [
            'bb_invoice_id' => $invoice->id,
            'bb_gateway_id' => $this->config['id'],
            'restore_session' => session_id()
        ]);
        $invoice_url = $this->di['tools']->url('invoice/' . $invoice->hash, ['restore_session' => session_id()]);

        $items = $this->di['db']->getAll("SELECT title, price, quantity FROM invoice_item WHERE invoice_id = :invoice_id", [':invoice_id' => $invoice->id]);

        $orderItems = [];
        foreach ($items as $item) {
            $orderItems[] = [
                'name' => $item['title'],
                'price' => (int) $item['price'],
                'quantity' => (int) ($item['quantity'] ?? 1),
            ];
        }

        $merchantRef = 'INV-' . $invoice->id . '-' . time();
        $amount = (int) $invoiceService->getTotalWithTax($invoice);

        // Generate signature
        $signature = $this->generateSignature($merchantRef, $amount);

        $data = [
            'method' => '',  // Payment method code, empty for payment selection page
            'merchant_ref' => $merchantRef,
            'amount' => $amount,
            'customer_name' => $invoice->buyer_first_name . ' ' . $invoice->buyer_last_name,
            'customer_email' => $invoice->buyer_email,
            'customer_phone' => $invoice->buyer_phone ?? '08123456789',
            'order_items' => $orderItems,
            'return_url' => $thankyou_url,
            'expired_time' => (time() + (24 * 60 * 60)), // 24 hours from now
            'signature' => $signature,
        ];

        if ($this->config['enable_logging']) {
            $this->logger->info('Creating Tripay transaction with data: ' . json_encode($data));
        }

        $apiUrl = $this->getApiUrl() . '/transaction/create';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->getApiKey()
        ]);

        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            if ($this->config['enable_logging']) {
                $this->logger->error('Tripay API Error: ' . curl_error($ch));
            }
            throw new Payment_Exception('Error creating Tripay transaction: ' . curl_error($ch));
        }
        curl_close($ch);

        $result = json_decode($response, true);
        if (!isset($result['data']['checkout_url'])) {
            if ($this->config['enable_logging']) {
                $this->logger->error('Tripay API Error: ' . $response);
            }
            throw new Payment_Exception('Invalid response from Tripay: ' . ($result['message'] ?? $response));
        }

        if ($this->config['enable_logging']) {
            $this->logger->info('Tripay transaction created: ' . json_encode($result));
        }

        return $result;
    }

    private function generateSignature($merchantRef, $amount)
    {
        $merchantCode = $this->getMerchantCode();
        $privateKey = $this->getPrivateKey();

        $signature = hash_hmac('sha256', $merchantCode . $merchantRef . $amount, $privateKey);

        if ($this->config['enable_logging']) {
            $this->logger->info('Generated signature for: ' . $merchantCode . $merchantRef . $amount);
        }

        return $signature;
    }

    private function getApiUrl()
    {
        $baseUrl = $this->config['use_sandbox'] ? 'https://tripay.co.id/api-sandbox' : 'https://tripay.co.id/api';
        return $baseUrl;
    }

    private function getApiKey()
    {
        return $this->config['use_sandbox'] ? $this->config['sandbox_api_key'] : $this->config['api_key'];
    }

    private function getPrivateKey()
    {
        return $this->config['use_sandbox'] ? $this->config['sandbox_private_key'] : $this->config['private_key'];
    }

    private function getMerchantCode()
    {
        return $this->config['use_sandbox'] ? $this->config['sandbox_merchant_code'] : $this->config['merchant_code'];
    }
}
