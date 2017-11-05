<?php
/**
 * File Paypal.php.
 *
 * @author Marcio Camello <marciocamello@outlook.com>
 * @author Luis Gonzalez INQUID <contact@inquid.co>
 * @see https://github.com/paypal/rest-api-sdk-php/blob/master/sample/
 * @see https://developer.paypal.com/webapps/developer/applications/accounts
 */
namespace inquid\yii2-paypal;

use Exception;
use PayPal\Api\PaymentExecution;
use Yii;
use yii\base\ErrorException;
use yii\helpers\ArrayHelper;
use yii\base\Component;
use PayPal\Api\Amount;
use PayPal\Api\Payer;
use PayPal\Api\Payment;
use PayPal\Api\Transaction;
use PayPal\Auth\OAuthTokenCredential;
use PayPal\Api\Details;
use PayPal\Api\Item;
use PayPal\Api\ItemList;
use PayPal\Api\RedirectUrls;
use PayPal\Rest\ApiContext;

class Paypal extends Component
{
    //region Mode (production/development)
    const MODE_SANDBOX = 'sandbox';
    const MODE_LIVE = 'live';
    //endregion
    //region Log levels
    /*
     * Logging level can be one of FINE, INFO, WARN or ERROR.
     * Logging is most verbose in the 'FINE' level and decreases as you proceed towards ERROR.
     */
    const LOG_LEVEL_FINE = 'FINE';
    const LOG_LEVEL_INFO = 'INFO';
    const LOG_LEVEL_WARN = 'WARN';
    const LOG_LEVEL_ERROR = 'ERROR';
    //endregion
    //region API settings
    public $clientId;
    public $clientSecret;
    public $isProduction;
    public $currency;
    public $config = [];

    /** @var ApiContext */
    private $_apiContext = null;

    /**
     * @setConfig
     * _apiContext in init() method
     */
    public function init()
    {
        $this->setConfig();
    }

    private function setConfig()
    {
        // ### Api context
        // Use an ApiContext object to authenticate
        // API calls. The clientId and clientSecret for the
        // OAuthTokenCredential class can be retrieved from
        // developer.paypal.com

        $this->_apiContext = new ApiContext(
            new OAuthTokenCredential(
                $this->clientId,
                $this->clientSecret
            )
        );

        // #### SDK configuration

        // Comment this line out and uncomment the PP_CONFIG_PATH
        // 'define' block if you want to use static file
        // based configuration
        $this->_apiContext->setConfig(ArrayHelper::merge(
            [
                'http.ConnectionTimeOut' => 30,
                'http.Retry' => 1,
                'log.LogEnabled' => YII_DEBUG ? 1 : 0,
                'log.LogLevel' => self::LOG_LEVEL_FINE,
                'validation.level' => 'log',
                'cache.enabled' => 'true',
                'log.FileName' => Yii::getAlias('@runtime/logs/paypal.log'),
            ], $this->config)
        );

        // Set file name of the log if present
        if (isset($this->config['log.FileName'])
            && isset($this->config['log.LogEnabled'])
            && ((bool)$this->config['log.LogEnabled'] == true)
        ) {
            $logFileName = \Yii::getAlias($this->config['log.FileName']);

            if ($logFileName) {
                if (!file_exists($logFileName)) {
                    if (!touch($logFileName)) {
                        throw new ErrorException('Can\'t create paypal.log file at: ' . $logFileName);
                    }
                }
            }

            $this->config['log.FileName'] = $logFileName;
        }

        return $this->_apiContext;
    }

    public function payer($tipo)
    {
        $payer = new Payer();
        $payer->setPaymentMethod($tipo);
        return $payer;
    }

    public function item($arrayParam)
    {
        $item = new Item();
        $item->setName($arrayParam['concepto'])
            ->setCurrency($this->currency)
            ->setQuantity($arrayParam['cantidad'])
            ->setPrice($arrayParam['total']);
        return $item;
    }

    public function itemList($arrayItem)
    {
        $itemList = new ItemList();
        $itemList->setItems($arrayItem);
        return $itemList;
    }

    public function details($arrayDetails)
    {
        $details = new Details();
        $details->setShipping($arrayDetails['shipping'])
            ->setTax($arrayDetails['tax'])
            ->setSubtotal($arrayDetails['subtotal']);
        return $details;
    }

    public function amount($arrayAmount)
    {
        $amount = new Amount();
        $amount->setCurrency($this->currency)
            ->setTotal($arrayAmount['total'])
            ->setDetails($arrayAmount['details']);
        return $amount;
    }

    public function transaction($arrayTransaction)
    {
        $transaction = new Transaction();
        $transaction->setAmount($arrayTransaction['amount'])
            ->setItemList($arrayTransaction['itemList'])
            ->setDescription($arrayTransaction['descripcion'])
            ->setInvoiceNumber($arrayTransaction['uniqid']);
        return $transaction;
    }

    public function redirectUrls($ruta)
    {
        $redirectUrls = new RedirectUrls();
        $redirectUrls->setReturnUrl($ruta . '&successPaypal=1')
            ->setCancelUrl($ruta . '&errorPaypal=1');
        return $redirectUrls;
    }

    public function payment($arrayPyment)
    {
        $payment = new Payment();
        $payment->setIntent($arrayPyment['intent'])
            ->setPayer($arrayPyment['payer'])
            ->setRedirectUrls($arrayPyment['redirectUrls'])
            ->setTransactions($arrayPyment['transaction']);
        $request = clone $payment;

        try {
            $payment->create($this->_apiContext);
        } catch (Exception $ex) {
            return $ex;
        }

        return $approvalUrl = $payment->getApprovalLink();
    }

	public function address($arrayAddress){
		$addr = new Address();
		$addr->setLine1($arrayPyment['line1']);
		$addr->setCity($arrayPyment['city']);
		$addr->setCountryCode($arrayPyment['country']);
		$addr->setPostalCode($arrayPyment['postal_code']);
		$addr->setState($arrayPyment['state']);	
		return $addr;
	}

	public function card($arrayCard){
		$card = new CreditCard();
		$card->setNumber(arrayCard['number']);
		$card->setType(arrayCard['type']);
		$card->setExpireMonth(arrayCard['exp_month']);
		$card->setExpireYear(arrayCard['exp_year']);
		$card->setCvv2(arrayCard['cvv2']);
		$card->setFirstName(arrayCard['first_name']);
		$card->setLastName(arrayCard['last_name']);
		$card->setBillingAddress(arrayCard['addr']);
		return $card;
	}





    

    public function execute($paymentId, $payerId, $shipping, $tax, $subtotal, $total)
    {
        $payment = Payment::get($paymentId, $this->_apiContext);
        $execution = new PaymentExecution();
        $execution->setPayerId($payerId);
        $transaction = new Transaction();
        $amount = new Amount();
        $details = new Details();
        $details->setShipping($shipping)
            ->setTax($tax)
            ->setSubtotal($subtotal);
        $amount->setCurrency($this->currency);
        $amount->setTotal($total);
        $amount->setDetails($details);
        $transaction->setAmount($amount);
        $execution->addTransaction($transaction);
        try {
            $result = $payment->execute($execution, $this->_apiContext);
            try {
                $payment = Payment::get($paymentId, $this->_apiContext);
                return true;
            } catch (Exception $ex) {
                print_r($ex);
                return false;
            }
        } catch (Exception $ex) {
            return false;
        }
    }

}
