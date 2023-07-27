<?php

declare(strict_types=1);

namespace app\webapi\controller;

use app\webapi\model\Company as ModelCompany;
use think\helper\Arr;

class Company extends Base
{
    public function update($authkey)
    {
        $this->validate(
            $this->param,
            [
                'companystate' => 'in:0,1,2,3,4,5',
                'endtime' => 'date',
                'balance' => 'float',
                'credit_limit' => 'float'
            ]
        );
        $model = ModelCompany::where('authkey', $authkey)->findOrFail();
        $model->save(Arr::only($this->param, ['companystate', 'endtime', 'balance', 'credit_limit']));
        return $this->success();
    }
}
