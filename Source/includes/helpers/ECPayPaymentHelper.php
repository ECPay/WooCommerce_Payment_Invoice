<?php
require_once(dirname( __FILE__ ) . '/ECPayPaymentModuleHelper.php');

class ECPayPaymentHelper extends ECPayPaymentModuleHelper
{
    /**
     * @var string SDK class name(required)
     */
    private $prefix = 'ecpay';

    /**
     * @var string SDK class name(required)
     */
    protected $sdkClassName = 'ECPay_Woo_AllInOne';

    /**
     * @var string SDK file path(required)
     */
    protected $sdkFilePath = 'ECPay.Payment.Integration.Shell.php';

    /**
     * @var string Service provider
     */
    private $provider = 'ECPay';

    /**
     * @var int Encrypt type
     */
    private $encryptType = ''; // Encrypt type

    /**
     * @var array Service Urls
     */
    private $serviceUrls = array(
        'prod' => '',
        'stage' => '',
    );

    /**
     * @var array Service path
     */
    private $functionPaths = array(
        'checkOut' => '/Cashier/AioCheckOut/V5',
        'queryTrade' => '/Cashier/QueryTradeInfo/V5',
    );

    /**
     * @var array API success return code
     */
    private $successCodes = array(
            'payment' => 1,
            'atmGetCode' => 2,
            'cvsGetCode' => 10100073,
            'barcodeGetCode' => 10100073,
    );

    /**
     * @var array 綠界付款方式
     */
    public $ecpayPayment = array('ecpay', 'ecpay_dca');

    /**
     * @var array 綠界付款方式
     */
    public $ecpayPaymentMethods = array(
        'Credit'    ,
        'Credit_3'  ,
        'Credit_6'  ,
        'Credit_12' ,
        'Credit_18' ,
        'Credit_24' ,
        'UnionPay'  ,
        'WebATM'    ,
        'ATM'       ,
        'CVS'       ,
        'BARCODE'   ,
        'ApplePay'
    );

    /**
     * @var array 特約會員才能使用的付款方式
     */
    public $ecpayPaymentMethodsSpecial = array(
        'Credit_3'  ,
        'Credit_6'  ,
        'Credit_12' ,
        'Credit_18' ,
        'Credit_24' ,
        'UnionPay'  ,
        'GooglePay'
    );

    /**
     * @var array 是否到期
     */
    public $isExpire = array(
        'yes' => 'Y',
        'no' => 'N',
    );

    /**
     * @var array 交易狀態代碼
     */
    public $tradeStatusCodes = array(
        'notFoundTradeData'  => '10200047',
        'emptyPaymentMethod' => '10200095',
    );

    /**
     * @var array 訂單狀態
     */
    public $orderStatus = array(
        'pending'    => '', // 等待付款
        'processing' => '', // 處理中(已付款)
        'onHold'     => '', // 保留
        'cancelled'  => '', // 取消
        'ecpay'      => '', // ECPay Shipping
    );

    /**
     * @var array 提示訊息
     */
    public $msg = array(
        'unpaidOrder'     => 'Unpaid order cancelled - time limit reached.', // 未付款訂單已取消 - 付款期限已過。
        'invalidPayment'  => 'Invalid payment method.',                      // 無效的付款方式.
        'testOrderPrefix' => 'Test order will add date as prefix.',          // 測試訂單將加上日期作為前綴
        'simulatePaid'    => 'Simulate paid, update the note only.',         // 模擬付款，僅更新備註。
    );

    /**
     * ECPayPaymentHelper constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->encryptType = ECPay_EncryptType::ENC_MD5;
        $this->setStageMerchantIds(array('2000132', '2000214'));
        $this->serviceUrls['prod'] = 'https://payment.'. $this->prefix .'.com.tw';
        $this->serviceUrls['stage'] = 'https://payment-stage.'. $this->prefix .'.com.tw';
    }

    /**
     * checkoutPrepare
     * @param  array $data The data for checkout
     * @return void
     */
    private function checkoutPrepare($data)
    {
        // Filter inputs
        $whiteList = array(
            'choosePayment',
            'hashKey',
            'hashIv',
            'returnUrl',
            'periodReturnURL',
            'clientBackUrl',
            'orderId',
            'total',
            'itemName',
            'cartName',
            'currency',
            'needExtraPaidInfo',
        );
        $inputs = $this->only($data, $whiteList);

        $paymentType = $inputs['choosePayment'];

        // Set SDK parameters
        $this->sdk->MerchantID = $this->getMerchantId();
        $this->sdk->HashKey = $inputs['hashKey'];
        $this->sdk->HashIV = $inputs['hashIv'];
        $this->sdk->ServiceURL = $this->getUrl('checkOut'); // Get Checkout URL
        $this->sdk->EncryptType = $this->encryptType;
        $this->sdk->Send['ReturnURL'] = $inputs['returnUrl'];
        $this->sdk->Send['ClientBackURL'] = $this->filterUrl($inputs['clientBackUrl']);
        $this->sdk->Send['OrderResultURL'] = $this->filterUrl($inputs['clientBackUrl']);
        $this->sdk->Send['MerchantTradeNo'] = $this->setMerchantTradeNo($inputs['orderId']);
        $this->sdk->Send['MerchantTradeDate'] = $this->getDateTime('Y/m/d H:i:s', '');
        $this->sdk->Send['TradeDesc'] = $this->getModuleDescription($inputs['cartName']);
        $this->sdk->Send['TotalAmount'] = $this->getAmount($inputs['total']);
        $this->sdk->Send['ChoosePayment'] = $this->getPaymentMethod($paymentType);
        $this->sdk->Send['NeedExtraPaidInfo'] = $this->getSdkExtraPaymentInfoOption($inputs['needExtraPaidInfo']);

        // Set the product info
        $this->sdk->Send['Items'][] = array(
            'Name' => $inputs['itemName'],
            'Price' => $this->sdk->Send['TotalAmount'],
            'Currency'  => $inputs['currency'],
            'Quantity' => 1,
            'URL' => '',
        );

        // Set the extend information
        switch ($this->sdk->Send['ChoosePayment']) {
            case $this->getSdkPaymentMethod('credit'):
                // Do not support UnionPay
                $this->sdk->SendExtend['UnionPay'] = false;

                // Credit installment parameters
                $creditParam = $this->getInstallment($paymentType);
                $creditParamCount = count($creditParam);
                if ($creditParamCount == 2) {
                    // 分期付款
                    $installments = 0;
                    if (isset($creditParam[1]) === true) {
                        $installments = $this->getAmount($creditParam[1]);
                    }

                    $this->sdk->SendExtend['CreditInstallment'] = $installments;
                    $this->sdk->SendExtend['InstallmentAmount'] = $this->sdk->Send['TotalAmount'];
                    $this->sdk->SendExtend['Redeem'] = false;
                } elseif ($creditParamCount == 4) {
                    // 定期定額
                    $this->sdk->SendExtend['PeriodAmount'] = $this->sdk->Send['TotalAmount'];
                    $this->sdk->SendExtend['PeriodType'] = $creditParam[1];
                    $this->sdk->SendExtend['Frequency'] = $creditParam[2];
                    $this->sdk->SendExtend['ExecTimes'] = $creditParam[3];
                    $this->sdk->SendExtend['PeriodReturnURL'] = $inputs['periodReturnURL'];
                }
                break;
            case $this->getSdkPaymentMethod('unionpay'):
                $this->sdk->Send['ChoosePayment'] = 'Credit';
                $this->sdk->SendExtend['UnionPay'] = true;
                break;
            case $this->getSdkPaymentMethod('webatm'):
            case $this->getSdkPaymentMethod('androidpay'):
            case $this->getSdkPaymentMethod('googlepay'):
                break;
            case $this->getSdkPaymentMethod('atm'):
                $this->sdk->SendExtend['ExpireDate'] = 3;
                $this->sdk->SendExtend['PaymentInfoURL'] = $this->sdk->Send['ReturnURL'];
                break;
            case $this->getSdkPaymentMethod('cvs'):
            case $this->getSdkPaymentMethod('barcode'):
                $this->sdk->SendExtend['Desc_1'] = '';
                $this->sdk->SendExtend['Desc_2'] = '';
                $this->sdk->SendExtend['Desc_3'] = '';
                $this->sdk->SendExtend['Desc_4'] = '';
                $this->sdk->SendExtend['PaymentInfoURL'] = $this->sdk->Send['ReturnURL'];
                break;
            default:
                throw new Exception('Invalid payment method.');
                break;
        }
    }

    /**
     * Checkout
     * @param  array $data The data for checkout
     * @return void
     * @throws Exception
     */
    public function checkout($data)
    {
        $this->checkoutPrepare($data);
        $this->sdk->CheckOut();
    }

    /**
     * 反查綠界訂單-取消過期訂單
     *
     * @param  array $data
     * @return array
     */
    public function expiredOrder($data)
    {
        // Filter inputs
        $whiteList = array(
            'hashKey'            ,
            'hashIv'             ,
            'orderId'            ,
            'holdStockMinute'    ,
            'orderStatus'        ,
            'paymentMethod'      ,
            'ecpayPaymentMethod' ,
            'createDate'         ,
            'stageOrderPrefix'   ,
        );
        $inputs = $this->only($data, $whiteList);

        $feedback = array();

        // 確認付款方式為'綠界'且訂單狀態為'等待付款中'或'取消'
        if (in_array($inputs['paymentMethod'], $this->ecpayPayment) && ($inputs['orderStatus'] == $this->getOrderStatusPending() || $inputs['orderStatus'] == $this->getOrderStatusCancelled())) {

            // 計算訂單建立時間是否超過指定時間
            if (strpos($inputs['ecpayPaymentMethod'], "Credit") === false) {
                $offset =  30; // 非信用卡
            } else {
                $offset =  60; // 信用卡
            }

            // 若使用者自訂的保留時間 > 綠界時間，則使用使用者設定的時間
            if ($inputs['holdStockMinute'] > $offset) {
                $offset = $inputs['holdStockMinute'];
            }

            // 比對時間，使用 Unix time 比對
            $createDate  = $inputs['createDate'];
            $dateCompare = $this->getUnixTime('- '. $offset .' minute');

            if ($createDate <= $dateCompare) {

                // 反查綠界訂單記錄API
                if ($this->isTestMode($this->getMerchantId()) === true) {
                    $merchantTradeNo = $inputs['stageOrderPrefix'] . $inputs['orderId'];
                } else {
                    $merchantTradeNo = $inputs['orderId'];
                }

                $data = array(
                    'hashKey'         => $inputs['hashKey'],
                    'hashIv'          => $inputs['hashIv'],
                    'merchantTradeNo' => $merchantTradeNo,
                );
                $feedback =  $this->getTradeInfo($data);
            }
        }

        return $feedback;
    }

    /**
     * Get checkout form
     * @param  array $data The data for checkout
     * @return void
     * @throws Exception
     */
    public function getCheckoutForm($data)
    {
        $this->checkoutPrepare($data);
        return $this->sdk->CheckOutString();
    }

    /**
     * Get valid feedback
     * @param  array $data The data for getting AIO feedback
     * @return array
     * @throws Exception
     */
    public function getValidFeedback($data)
    {
        $feedback = $this->getFeedback($data); // feedback
        $data['merchantTradeNo'] = $feedback['MerchantTradeNo'];
        $info = $this->getTradeInfo($data); // Trade info

        // Check the amount
        if (!$this->validAmount($feedback['TradeAmt'], $info['TradeAmt'])) {
            throw new Exception('Invalid ' . $this->provider . ' feedback.(1)');
        }

        return $feedback;
    }

    /**
     * Get the order id from AIO merchant trade number
     * @param  string $merchantTradeNo AIO merchant trade number
     * @return string|false
     */
    public function getOrderId($merchantTradeNo = '')
    {
        // Filter inputs
        if (empty($merchantTradeNo) === true) {
            return false;
        }
        unset($inputs);

        $merchantId = $this->getMerchantId();
        if ($this->isTestMode($merchantId) === true) {
            $start = $this->getMerchantOrderPrefixLength();
            $orderId = substr($merchantTradeNo, $start);
        } else {
            $orderId = $merchantTradeNo;
        }
        return $orderId;
    }

    /**
     * Get AIO response state
     * @param  array $feedback  AIO feedback
     * @param  array $orderInfo Order info
     * @return integer
     * @throws Exception
     */
    public function getResponseState($feedback = array(), $orderInfo = array())
    {
        // Filter inputs
        $whiteList = array(
            'PaymentType',
            'SimulatePaid',
            'RtnCode',
            'RtnMsg',
        );
        $inputFeedback = $this->only($feedback, $whiteList);
        unset($whiteList);

        $whiteList = array(
            'validState',
            'orderId',
        );
        $inputOrder = $this->only($orderInfo, $whiteList);
        unset($whiteList);

        // Set parameters
        $orderId = $inputOrder['orderId'];
        $validState = $inputOrder['validState'];
        $paymentMethod = $this->getPaymentMethod($inputFeedback['PaymentType']);
        $paymentFailed = $this->getPaymentFailed($orderId, $inputFeedback);
        $getSuccessData = array(
            'validState' => $validState,
            'simulatePaid' => $inputFeedback['SimulatePaid'],
        );
        unset($inputOrder);

        // Check the response state
        //   1:Paid
        //   2:ATM get code
        //   3:CVS get code
        //   4:BARCODE get code
        //   5:State error
        //   6:Simulate Paid
        switch($paymentMethod) {
            case $this->getSdkPaymentMethod('credit'):
            case $this->getSdkPaymentMethod('unionpay'):
            case $this->getSdkPaymentMethod('webatm'):
            case $this->getSdkPaymentMethod('androidpay'):
            case $this->getSdkPaymentMethod('googlepay'):
                if ($this->isSuccess($inputFeedback, 'payment') === true) {
                    $responseState = $this->getSuccessState($getSuccessData);
                    if ($responseState === false) {
                        throw new Exception($paymentFailed);
                    }
                } else {
                    throw new Exception($paymentFailed);
                }
                break;
            case $this->getSdkPaymentMethod('atm'):
                if ($this->isSuccess($inputFeedback, 'payment') === true) {
                    $responseState = $this->getSuccessState($getSuccessData);
                    if ($responseState === false) {
                        throw new Exception($paymentFailed);
                    }
                } elseif ($this->isSuccess($inputFeedback, 'atmGetCode') === true) {
                    $responseState = 2; // ATM get code
                } else {
                    throw new Exception($paymentFailed);
                }
                break;
            case $this->getSdkPaymentMethod('cvs'):
                if ($this->isSuccess($inputFeedback, 'payment') === true) {
                    $responseState = $this->getSuccessState($getSuccessData);
                    if ($responseState === false) {
                        throw new Exception($paymentFailed);
                    }
                } elseif ($this->isSuccess($inputFeedback, 'cvsGetCode') === true) {
                    $responseState = 3; // CVS get code
                } else {
                    throw new Exception($paymentFailed);
                }
                break;
            case $this->getSdkPaymentMethod('barcode'):
                if ($this->isSuccess($inputFeedback, 'payment') === true) {
                    $responseState = $this->getSuccessState($getSuccessData);
                    if ($responseState === false) {
                        throw new Exception($paymentFailed);
                    }
                } elseif ($this->isSuccess($inputFeedback, 'barcodeGetCode') === true) {
                    $responseState = 4; // Barcode get code
                } else {
                    throw new Exception($paymentFailed);
                }
                break;
            default:
                throw new Exception($this->getInvalidPayment($orderId));
        }
        return $responseState;
    }

    /**
     * Get payment success message
     * @param  string $pattern  Message pattern
     * @param  array  $feedback AIO feedback
     * @return string
     */
    public function getPaymentSuccessComment($pattern = '', $feedback = array())
    {
        // Filter inputs
        if (empty($pattern) === true) {
            return false;
        }

        $list = array(
            'RtnCode',
            'RtnMsg',
            'PaymentType',
        );
        $inputs = $this->only($feedback, $list);
        if ($this->hasEmpty($inputs) === true) {
            return false;
        }

        // Set the parameters
        $paymentType = $this->getFeedbackPaymentType($inputs['PaymentType']);
        $paymentMethod = $this->getPaymentMethod($paymentType);
        unset($paymentType);

        return sprintf(
            $pattern,
            $paymentMethod,
            $inputs['RtnCode'],
            $inputs['RtnMsg']
        );
    }

    /**
     * Get obtaining code comment
     * @param  string $pattern  Message pattern
     * @param  string  $error    Error message
     * @return string|boolean
     */
    public function getFailedComment($pattern = '', $error = '')
    {
        if (empty($pattern) === true) {
            return false;
        }

        if (empty($error) === true) {
            return false;
        }

        return sprintf($pattern, $error);
    }

    /**
     * Get the feedback payment type option
     * @param  string  $paymentType AIO payment type
     * @return string
     */
    public function getFeedbackPaymentType($paymentType = '')
    {
        $pieces = explode('_', $paymentType);
        return strtolower($pieces[0]);
    }

    /**
     * Get obtaining code comment
     * @param  string $pattern  Message pattern
     * @param  array  $feedback AIO feedback
     * @return string
     */
    public function getObtainingCodeComment($pattern = '', $feedback = array())
    {
        // Filter inputs
        $undefinedMessage = 'undefined';
        if (empty($pattern) === true) {
            return $undefinedMessage;
        }

        $list = array(
            'PaymentType',
            'RtnCode',
            'RtnMsg',
            'BankCode',
            'vAccount',
            'ExpireDate',
            'PaymentNo',
            'Barcode1',
            'Barcode2',
            'Barcode3',
        );
        $inputs = $this->only($feedback, $list);

        $type = $this->getPaymentMethod($inputs['PaymentType']);
        switch($type) {
            case 'ATM':
                return sprintf(
                    $pattern,
                    $inputs['RtnCode'],
                    $inputs['RtnMsg'],
                    $inputs['BankCode'],
                    $inputs['vAccount'],
                    $inputs['ExpireDate']
                );
                break;
            case 'CVS':
                return sprintf(
                    $pattern,
                    $inputs['RtnCode'],
                    $inputs['RtnMsg'],
                    $inputs['PaymentNo'],
                    $inputs['ExpireDate']
                );
                break;
                case 'BARCODE':
                    return sprintf(
                        $pattern,
                        $inputs['RtnCode'],
                        $inputs['RtnMsg'],
                        $inputs['ExpireDate'],
                        $inputs['Barcode1'],
                        $inputs['Barcode2'],
                        $inputs['Barcode3']
                    );
                    break;
            default:
                break;
        }
        return $undefinedMessage;
    }

    /**
     * Get AIO URL
     * @param  string $type URL type
     * @return string|boolean
     */
    private function getUrl($type = '')
    {
        if (isset($this->functionPaths[$type]) === false) {
            return false;
        }

        $merchantId = $this->getMerchantId();
        if ($this->isTestMode($merchantId) === true) {
            $url = $this->serviceUrls['stage'];
        } else {
            $url = $this->serviceUrls['prod'];
        }
        return $url . $this->functionPaths[$type];
    }

    /**
     * Filter the specific character
     * @param  string $url URL
     * @return string
     */
    private function filterUrl($url)
    {
        return str_replace('&amp;', '&', $url);
    }

    /**
     * Get the module description
     * @param  string $cartName Cart name
     * @return string
     */
    private function getModuleDescription($cartName = '')
    {
        return strtolower($this->provider) . '_module_' . strtolower($cartName);
    }

    /**
     * Get SDK payment method
     * @param  string $paymentType payment type
     * @return string|bool
     */
    private function getSdkPaymentMethod($paymentType = '')
    {
        // Filter inputs
        if (empty($paymentType) === true) {
            return false;
        }

        $lower = strtolower($paymentType);
        switch ($lower) {
            case 'all':
                $sdkPayment = ECPay_PaymentMethod::ALL;
                break;
            case 'credit':
                $sdkPayment = ECPay_PaymentMethod::Credit;
                break;
            case 'webatm':
                $sdkPayment = ECPay_PaymentMethod::WebATM;
                break;
            case 'atm':
                $sdkPayment = ECPay_PaymentMethod::ATM;
                break;
            case 'cvs':
                $sdkPayment = ECPay_PaymentMethod::CVS;
                break;
            case 'barcode':
                $sdkPayment = ECPay_PaymentMethod::BARCODE;
                break;
            case 'androidpay':
                $sdkPayment = ECPay_PaymentMethod::AndroidPay;
                break;
            case 'googlepay':
                $sdkPayment = ECPay_PaymentMethod::GooglePay;
                break;
            case 'unionpay':
                $sdkPayment = 'UnionPay';
                break;
            default:
                $sdkPayment = '';
                break;
        }
        return $sdkPayment;
    }

    /**
     * Get SDK NeedExtraPaidInfo option
     * @param  string  $type Type
     * @return string
     */
    private function getSdkExtraPaymentInfoOption($type = '')
    {
        if ($type === 'Y') {
            return ECPay_ExtraPaymentInfo::Yes;
        }
        return ECPay_ExtraPaymentInfo::No;
    }

    /**
     * Get the credit installment
     * @param  string $paymentType Payment type
     * @return integer|bool
     */
    private function getInstallment($paymentType = '')
    {
        // Filter inputs
        if (empty($paymentType) === true) {
            return false;
        }

        $pieces = explode('_', $paymentType);

        return $pieces;
    }

    /**
     * Get the payment method from the payment type
     * @param  string $paymentType Payment type
     * @return string|bool
     */
    public function getPaymentMethod($paymentType = '')
    {
        // Filter inputs
        if (empty($paymentType) === true) {
            return false;
        }

        $pieces = explode('_', $paymentType);
        return $this->getSdkPaymentMethod($pieces[0]);
    }

    /**
     * Get the feedback
     * @param  array $data The data for the feedback
     * @return mixed
     * @throws Exception
     */
    public function getFeedback($data)
    {
        // Filter inputs
        $whiteList = array(
            'hashKey',
            'hashIv',
        );
        $inputs = $this->only($data, $whiteList);

        // Set SDK parameters
        $this->sdk->MerchantID = $this->getMerchantId();
        $this->sdk->HashKey = $inputs['hashKey'];
        $this->sdk->HashIV = $inputs['hashIv'];
        $this->sdk->EncryptType = $this->encryptType;
        $feedback = $this->sdk->CheckOutFeedback();
        if (count($feedback) < 1) {
            throw new Exception($this->provider . ' feedback is empty.');
        }
        return $feedback;
    }

    /**
     * Get the trade info
     * @param  array $feedback AIO feedback
     * @param  array $data     The data for querying aio trade info
     * @return array
     * @throws Exception
     */
    public function getTradeInfo($data)
    {
        // Filter inputs
        $whiteList = array(
            'hashKey',
            'hashIv',
            'merchantTradeNo',
        );
        $inputs = $this->only($data, $whiteList);

        // Set SDK parameters
        $this->sdk->MerchantID = $this->getMerchantId();
        $this->sdk->HashKey = $inputs['hashKey'];
        $this->sdk->HashIV = $inputs['hashIv'];
        $this->sdk->ServiceURL = $this->getUrl('queryTrade');
        $this->sdk->EncryptType = $this->encryptType;
        $this->sdk->Query['MerchantTradeNo'] = $inputs['merchantTradeNo'];
        $info = $this->sdk->QueryTradeInfo();
        if (count($info) < 1) {
            throw new Exception($this->provider . ' trade info is empty.');
        }
        return $info;
    }

    /**
     * Check AIO feedback state
     * @param  array   $feedback AIO feedback
     * @param  string  $type     Feedback type
     * @return bool
     */
    private function isSuccess($feedback, $type)
    {
        // Filter inputs
        $whiteList = array(
            'RtnCode',
        );
        $inputs = $this->only($feedback, $whiteList);
        if ($this->hasEmpty($inputs) === true) {
            return false;
        }

        return ($this->toInt($feedback['RtnCode']) === $this->toInt($this->successCodes[$type]));
    }

    /**
     * Get payment failed message
     * @param  mixed $orderId  Order id
     * @param  array $feedback AIO feedback
     * @return string|bool
     */
    private function getPaymentFailed($orderId = 0, $feedback = array())
    {
        // Filter inputs
        if (empty($orderId) === true) {
            return false;
        }

        $whiteList = array(
            'RtnCode',
            'RtnMsg'
        );
        $inputs = $this->only($feedback, $whiteList);
        if ($this->hasEmpty($inputs) === true) {
            return false;
        }

        return sprintf('Order %s Exception.(%s: %s)', $orderId, $inputs['RtnCode'], $inputs['RtnMsg']);
    }

    /**
     * Get success state
     * @param array $data Check data
     * @return bool|int
     */
    private function getSuccessState($data = array())
    {
        // Filter inputs
        $whiteList = array(
            'validState',
            'simulatePaid'
        );
        $inputs = $this->only($data, $whiteList);

        if ($inputs['validState'] === true) {
            if ($this->toInt($inputs['simulatePaid']) === 0) {
                $responseState = 1; // Paid
            } else {
                $responseState = 6; // Simulate Paid
            }
        } else {
            $responseState = 5; // State error
        }
        return $responseState;
    }

    /**
     * Get invalid payment message
     * @param  mixed   $orderId  Order id
     * @return string|boolean
     */
    private function getInvalidPayment($orderId = 0)
    {
        // Filter inputs
        if (empty($orderId) === true) {
            return false;
        }

        return sprintf('Order %s, payment method is invalid.', $orderId);
    }

    /**
     * getOrderStatusPending function
     * 取得購物車訂單狀態 - 等待付款
     *
     * @return string 等待付款
     */
    public function getOrderStatusPending()
    {
        return $this->orderStatus['pending'];
    }

    /**
     * getOrderStatusProcessing function
     * 取得購物車訂單狀態 - 處理中(已付款)
     *
     * @return string 處理中(已付款)
     */
    public function getOrderStatusProcessing()
    {
        return $this->orderStatus['processing'];
    }

    /**
     * getOrderStatusOnHold function
     * 取得購物車訂單狀態 - 保留
     *
     * @return string 保留
     */
    public function getOrderStatusOnHold()
    {
        return $this->orderStatus['onHold'];
    }

    /**
     * getOrderStatusCancelled function
     * 取得購物車訂單狀態 - 取消
     *
     * @return string 取消
     */
    public function getOrderStatusCancelled()
    {
        return $this->orderStatus['cancelled'];
    }

    /**
     * getOrderStatusEcpay function
     * 取得購物車訂單狀態 - ECPay Shipping
     *
     * @return string ECPay Shipping
     */
    public function getOrderStatusEcpay()
    {
        return $this->orderStatus['ecpay'];
    }

    /**
     * setOrderStatus function
     * 設定購物車訂單狀態 - 全部
     *
     * @param  array $data
     * @return void
     */
    public function setOrderStatus($data)
    {
        $status = array('Pending', 'Processing', 'OnHold', 'Cancelled', 'Ecpay');

        foreach($status as $value) {
            $funName = 'setOrderStatus' . $value; // 組合 function name
            $this->$funName($data[$value]);
        }
    }

    /**
     * setOrderStatusPending function
     * 設定購物車訂單狀態 - 等待付款
     *
     * @param  string $value 要儲存的值
     * @return void
     */
    public function setOrderStatusPending($value)
    {
        $this->orderStatus['pending'] = $value;
    }

    /**
     * setOrderStatusProcessing function
     * 設定購物車訂單狀態 - 處理中(已付款)
     *
     * @param  string $value 要儲存的值
     * @return void
     */
    public function setOrderStatusProcessing($value)
    {
        $this->orderStatus['processing'] = $value;
    }

    /**
     * setOrderStatusOnHold function
     * 設定購物車訂單狀態 - 保留
     *
     * @param  string $value 要儲存的值
     * @return void
     */
    public function setOrderStatusOnHold($value)
    {
        $this->orderStatus['onHold'] = $value;
    }

    /**
     * setOrderStatusCancelled function
     * 設定購物車訂單狀態 - 取消
     *
     * @param  string $value 要儲存的值
     * @return void
     */
    public function setOrderStatusCancelled($value)
    {
        $this->orderStatus['cancelled'] = $value;
    }

    /**
     * setOrderStatusEcpay function
     * 設定購物車訂單狀態 - ECPay Shipping
     *
     * @param  string $value 要儲存的值
     * @return void
     */
    public function setOrderStatusEcpay($value)
    {
        $this->orderStatus['ecpay'] = $value;
    }
}