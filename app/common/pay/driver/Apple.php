<?php

namespace app\common\pay\driver;

use app\common\pay\Driver;
use think\facade\Request;
use \Yansongda\Supports\Collection;
use app\wssx\model\MemberOrder;

/**
 * 苹果内购
 */
class Apple extends Driver
{
    protected $result;

    public function __construct(array $config)
    {
        parent::__construct($config);

        // $this->handler = Pay::alipay($this->config);
    }

    public function verify()
    {
        $class = new class extends \app\common\http\Base{};
        $collection = new Collection($class->httpJson($this->config['verify_url'],['receipt-data'=>Request::param('receipt_data'),'password'=>Request::param('password')]));

        $this->result = $collection->toArray();

        $collection->out_trade_no = config('app.order_prefix').Request::param('order_number');
        if ($collection->status==0) {
            $collection->status = $collection->receipt['bundle_id']==config('app.apple_bundle_id')?MemberOrder::PAID_STATUS:MemberOrder::CANCEL_STATUS;
            $collection->out_order_number = $collection->receipt['in_app'][0]['transaction_id'];
        }else {
            $collection->status = MemberOrder::CANCEL_STATUS;
        }
        
        
        return $collection;
    }

    public function success()
    {
        return json(['result'=>0,'data'=>$this->result,'msg'=>'success']);
    }
}
