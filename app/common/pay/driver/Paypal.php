<?php

namespace app\common\pay\driver;

use app\common\pay\Driver;
use think\facade\Request;
use \Yansongda\Supports\Collection;
use PayPalCheckoutSdk\Orders\OrdersCaptureRequest;
use PayPalCheckoutSdk\Core\PayPalHttpClient;
use PayPalCheckoutSdk\Core\SandboxEnvironment;
use PayPalCheckoutSdk\Orders\OrdersGetRequest;
use PayPalCheckoutSdk\Core\ProductionEnvironment;
use PayPalCheckoutSdk\Orders\OrdersCreateRequest;

/**
 * paypal
 */
class Paypal extends Driver
{
    protected $result;

    public function __construct(array $config)
    {
        parent::__construct($config);

        $this->environment = env('app_debug') ? new SandboxEnvironment($this->config['clientId'], $this->config['clientSecret']) : new ProductionEnvironment($this->config['clientId'], $this->config['clientSecret']);
    }

    public function verify()
    {
        $token = Request::param('token');

        $client = new PayPalHttpClient($this->environment);
        $request = new OrdersGetRequest($token);
        $response = $client->execute($request);

        if ($response->result->status != 'APPROVED') {
            return redirect(sprintf(config('pay.front_return_url'), 0));
            // throw new \Exception("Order invalid");
        }

        try {
            $captureRequest = new OrdersCaptureRequest($token);
            $captureRequest->prefer('return=minimal');
            $captureResponse = $client->execute($captureRequest);

            $collection = new Collection();
            $collection->out_trade_no = $captureResponse->result->purchase_units[0]->reference_id;
            $collection->out_order_number = $captureResponse->result->id;
            $collection->status = $captureResponse->result->status == 'COMPLETED' ? 2 : 3;
        } catch (\Throwable $th) {
            return redirect(sprintf(config('pay.front_return_url'), 0));
            // throw $th;
        }


        return $collection;
    }

    public function web($order)
    {
        $client = new PayPalHttpClient($this->environment);
        $request = new OrdersCreateRequest();
        $request->prefer('return=representation');
        $request->body = $order;
        $response = $client->execute($request);
        for ($i = 0; $i < count($response->result->links); ++$i) {
            $link = $response->result->links[$i];
            if ($link->rel == 'approve') {
                return $link->href;
            }
        }
    }

    public function makeOrder($orderNumber, $money, $desc): array
    {
        return [
            "intent" => "CAPTURE",
            "purchase_units" => [[
                "reference_id" => $orderNumber,
                "amount" => [
                    "value" => $money,
                    "currency_code" => "USD"
                ]
            ]],
            "application_context" => [
                // "cancel_url" => "",
                "return_url" => $this->config['return_url']
            ]
        ];
    }

    public function success()
    {
        return redirect(sprintf(config('pay.front_return_url'), 1));
    }
}
