<?php
declare (strict_types=1);

namespace app\admin\controller;

use app\admin\model\RoomTemplate;
use app\admin\validate\RoomTemplate as RoomTemplateValidate;
use app\admin\model\Room;
use app\common\http\singleton\TemplateSingleton;
use app\common\http\singleton\ThemeSingleton;
use think\exception\ValidateException;
use think\helper\Arr;
use think\facade\Db;
class Template extends Base
{

    /**
     *教室模版列表
     *
     */
    public function index()
    {
        $this->initDefaultTpl();
        $RoomTemplate = new RoomTemplate();
        $RoomTemplate->isPage = false;
        return $this->success($this->searchList($RoomTemplate));
    }

    /**
     * 创建模版
     *
     */
    public function save()
    {
        $this->validate($this->param, RoomTemplateValidate::class);

        $tMax = Db::table('saas_room_template')
            ->where('company_id', $this->request->user['company_id'])
            ->where('state', RoomTemplate::OPEN_STATE)
            ->where('delete_time', 0)
            ->max('sort');

        $cMax = Db::table('saas_room_template_config')
            ->where('company_id', $this->request->user['company_id'])
            ->where('state', RoomTemplate::OPEN_STATE)
            ->max('sort');

        $max = max($tMax, $cMax);
        $this->param['sort'] = $max + 1;
        $model = RoomTemplate::create($this->param);

        return $this->success($model);
    }


    /**
     * 教室模版详情
     * @param $id
     * @return \think\response\Json
     */
    public function read($id)
    {
        return $this->success((new RoomTemplate)->findOrFail($id)->append(['configitems_tool', 'configitems_func']));
    }

    /**
     * 更新详情
     * @param $id
     * @return \think\response\Json
     */
    public function update($id)
    {
        $this->validate($this->param, RoomTemplateValidate::class);

        $model = RoomTemplate::findOrFail($id);

        if (!$model['company_id']) {
            $tMax = Db::table('saas_room_template')
                ->where('company_id', $this->request->user['company_id'])
                ->where('state', RoomTemplate::OPEN_STATE)
                ->where('delete_time', 0)
                ->max('sort');

            $cMax = Db::table('saas_room_template_config')
                ->where('company_id', $this->request->user['company_id'])
                ->where('state', RoomTemplate::OPEN_STATE)
                ->max('sort');

            $max = max($tMax, $cMax);
            $this->param['sort'] = $max + 1;
        }
        $model->save(Arr::except($this->param, ['company_id', 'openanalyze']));

        return $this->success($model);
    }

    /**
     * 删除教室模版
     * @param $id
     * @return \think\response\Json
     */
    public function delete($id)
    {
        $model = RoomTemplate::findOrFail($id);

        $model->delete();

        return $this->success();
    }

    /**
     * 更新状态
     * @param $id
     * @param $state
     * @return \think\response\Json
     */
    public function changeState($id, $state)
    {
        $model = RoomTemplate::findOrFail($id);

        if (!$model['company_id']) {
            Db::transaction(function () use ($id, $state, $model) {
                Db::table('saas_room_template_config')
                    ->where('room_template_id', $id)
                    ->where('company_id', $this->request->user['company_id'])
                    ->update(['state' => $state]);
            });
        } else {
            $model->save(['state' => $state]);
        }

        return $this->success();
    }

    /**
     * 获取教室模板基础设置信息
     *
     */
    public function settingItemsInfo()
    {
        $setting = TemplateSingleton::getInstance()->getSetting();

        $type = [Room::ROOMTYPE_ONEROOM, Room::ROOMTYPE_CROWDROOM, Room::ROOMTYPE_LARGEROOM];

        $configitems_tool = [];
        foreach (RoomTemplate::TOOL_SET_FIELDS as $k => $v) {

            $configitems_tool[$k] = [
                'name' => lang($k),
                'value' => $setting[$v],
                'type' => in_array($v, [66]) ? [Room::ROOMTYPE_LARGEROOM] : (!in_array($v, [61, 63]) ? [Room::ROOMTYPE_ONEROOM, Room::ROOMTYPE_CROWDROOM] : $type)
            ];

        }

        $configitems_func = [];
        foreach (RoomTemplate::FUNC_SET_FIELDS as $k => $v) {

            $configitems_func[$k] = [
                'name' => lang($k),
                'value' => $setting[$v],
                'type' => in_array($v, [152, 153, 135, 108, 119]) ? [Room::ROOMTYPE_ONEROOM, Room::ROOMTYPE_CROWDROOM] : $type
            ];
        }


        return $this->success([
            'layout_list' => TemplateSingleton::getInstance()->getLayout(),
            'theme_list' => ThemeSingleton::getInstance()->getData(),
            'videotype_list' => TemplateSingleton::getInstance()->getVideoList(TemplateSingleton::getInstance()->getVideo()),
            'company_roomlogo' => TemplateSingleton::getInstance()->getLogo(),
            'configitems_tool' => $configitems_tool,
            'configitems_func' => $configitems_func,
            'layouticonpath' => config('app.host.local') . '/image/icon/',
        ]);
    }

    /**
     * 模板上移
     */
    public function up($id)
    {
        $model = RoomTemplate::findOrFail($id);
        //默认模板操作
        if (!$model['company_id']) {
            $currentSort = Db::table('saas_room_template_config')
                ->where('room_template_id', $id)
                ->where('company_id', $this->request->user['company_id'])
                ->value('sort');
        } else {
            //私有模板操作
            $currentSort = Db::table('saas_room_template')
                ->where('id', $id)
                ->where('company_id', $this->request->user['company_id'])
                ->where('state', RoomTemplate::OPEN_STATE)
                ->where('delete_time', 0)
                ->value('sort');
        }

        $tplSort = Db::table('saas_room_template')
            ->where('company_id', $this->request->user['company_id'])
            ->where('state', RoomTemplate::OPEN_STATE)
            ->where('delete_time', 0)
            ->min('sort');

        $configSort = Db::table('saas_room_template_config')
            ->where('company_id', $this->request->user['company_id'])
            ->min('sort');
        //当前模板已经是最靠前位置,无法操作

        if ($currentSort <= $tplSort && $currentSort <= $configSort) {
            throw new ValidateException(lang('tpl_top'));
        }

        //获取当前节点的上一节点
        $tplPre = Db::table('saas_room_template')
            ->where('company_id', $this->request->user['company_id'])
            ->where('sort', '<', $currentSort)
            ->where('state', RoomTemplate::OPEN_STATE)
            ->where('delete_time', 0)
            ->order('sort', 'desc')
            ->find();

        $configPre = Db::table('saas_room_template_config')
            ->where('company_id', $this->request->user['company_id'])
            ->where('sort', '<', $currentSort)
            ->order('sort', 'desc')
            ->find();

        Db::transaction(function () use ($tplPre, $configPre, $currentSort) {
            $inrSort = $currentSort + 1;
            //两张表都存在上一节点
            if ($tplPre && $configPre) {
                //上一节点在主表
                if ($tplPre['sort'] > $configPre['sort']) {
                    $finalId = $tplPre['id'];
                    //更新上一节点sort
                    Db::table('saas_room_template')
                        ->where('company_id', $this->request->user['company_id'])
                        ->where('id', $tplPre['id'])
                        ->where('delete_time', 0)
                        ->where('state', RoomTemplate::OPEN_STATE)
                        ->update(['sort' => $inrSort]);
                } else {
                    //上一节点在配置表
                    //更新上一节点sort
                    Db::table('saas_room_template_config')
                        ->where('company_id', $this->request->user['company_id'])
                        ->where('room_template_id', $configPre['room_template_id'])
                        ->update(['sort' => $inrSort]);

                    $finalId = $configPre['room_template_id'];
                }

            } else {
                if (isset($tplPre['id'])) {
                    //更新上一节点sort
                    Db::table('saas_room_template')
                        ->where('company_id', $this->request->user['company_id'])
                        ->where('id', $tplPre['id'])
                        ->where('state', RoomTemplate::OPEN_STATE)
                        ->where('delete_time', 0)
                        ->update(['sort' => $inrSort]);

                    $finalId = $tplPre['id'];
                } else {
                    //更新上一节点sort
                    Db::table('saas_room_template_config')
                        ->where('company_id', $this->request->user['company_id'])
                        ->where('room_template_id', $configPre['room_template_id'])
                        ->update(['sort' => $inrSort]);

                    $finalId = $configPre['room_template_id'];
                }
            }

            //更新当前节点后续的节点表1
            Db::table('saas_room_template')
                ->where('company_id', $this->request->user['company_id'])
                ->whereNotIn('id', [$finalId])
                ->where('sort', '>', $currentSort)
                ->where('state', RoomTemplate::OPEN_STATE)
                ->where('delete_time', 0)
                ->inc('sort')
                ->update();

            //更新当前节点后续节点表2
            Db::table('saas_room_template_config')
                ->where('company_id', $this->request->user['company_id'])
                ->whereNotIn('room_template_id', [$finalId])
                ->where('sort', '>', $currentSort)
                ->inc('sort')
                ->update();
        });

        return $this->success();
    }

    /**
     * 模板下移
     * @param $id
     */
    public function down($id)
    {
        $model = RoomTemplate::findOrFail($id);
        //默认模板操作
        if (!$model['company_id']) {
            $currentSort = Db::table('saas_room_template_config')
                ->where('room_template_id', $id)
                ->where('company_id', $this->request->user['company_id'])
                ->value('sort');
        } else {
            //私有模板操作
            $currentSort = Db::table('saas_room_template')
                ->where('id', $id)
                ->where('company_id', $this->request->user['company_id'])
                ->where('delete_time', 0)
                ->where('state', RoomTemplate::OPEN_STATE)
                ->value('sort');
        }

        $tplSort = Db::table('saas_room_template')
            ->where('company_id', $this->request->user['company_id'])
            ->where('state', RoomTemplate::OPEN_STATE)
            ->where('delete_time', 0)
            ->max('sort');

        $configSort = Db::table('saas_room_template_config')
            ->where('company_id', $this->request->user['company_id'])
            ->max('sort');
        //当前模板已经是最靠后位置,无法操作
        if ($currentSort >= $tplSort && $currentSort >= $configSort) {
            throw new ValidateException(lang('tpl_top'));
        }
        //获取当前节点的下一节点
        $tplPre = Db::table('saas_room_template')
            ->where('company_id', $this->request->user['company_id'])
            ->where('sort', '>', $currentSort)
            ->where('state', RoomTemplate::OPEN_STATE)
            ->where('delete_time', 0)
            ->order('sort')
            ->find();

        $configPre = Db::table('saas_room_template_config')
            ->where('company_id', $this->request->user['company_id'])
            ->where('sort', '>', $currentSort)
            ->order('sort')
            ->find();

        Db::transaction(function () use ($tplPre, $configPre, $model) {
            if ($tplPre && $configPre) {
                $minSort = min($tplPre['sort'], $configPre['sort']);
            } else {
                $minSort = $tplPre ? $tplPre['sort'] : $configPre['sort'];
            }
            $nextSort = $minSort + 1;
            //更新当前节点sort
            if ($model['company_id']) {
                Db::table('saas_room_template')
                    ->whereIn('company_id', [$this->request->user['company_id']])
                    ->where('id', $model['id'])
                    ->where('state', RoomTemplate::OPEN_STATE)
                    ->where('delete_time', 0)
                    ->update(['sort' => $nextSort]);
            } else {
                Db::table('saas_room_template_config')
                    ->where('company_id', $this->request->user['company_id'])
                    ->where('room_template_id', $model['id'])
                    ->update(['sort' => $nextSort]);
            }

            //更新当前节点后续的节点表1
            Db::table('saas_room_template')
                ->whereIn('company_id', [$this->request->user['company_id']])
                ->whereNotIn('id', [$model['id']])
                ->where('sort', '>', $minSort)
                ->where('state', RoomTemplate::OPEN_STATE)
                ->where('delete_time', 0)
                ->inc('sort')
                ->update();

            //更新当前节点后续节点表2
            Db::table('saas_room_template_config')
                ->where('company_id', $this->request->user['company_id'])
                ->whereNotIn('room_template_id', [$model['id']])
                ->where('sort', '>', $minSort)
                ->inc('sort')
                ->update();
        });

        return $this->success();
    }


    /**
     * 置顶
     * @param $id
     */
    public function top($id)
    {
        $model = RoomTemplate::findOrFail($id);
        //默认模板操作
        if (!$model['company_id']) {
            $currentSort = Db::table('saas_room_template_config')
                ->where('room_template_id', $id)
                ->where('company_id', $this->request->user['company_id'])
                ->value('sort');
        } else {
            //私有模板操作
            $currentSort = Db::table('saas_room_template')
                ->where('id', $id)
                ->where('company_id', $this->request->user['company_id'])
                ->where('state', RoomTemplate::OPEN_STATE)
                ->where('delete_time', 0)
                ->value('sort');
        }

        $tplSort = Db::table('saas_room_template')
            ->where('company_id', $this->request->user['company_id'])
            ->where('state', RoomTemplate::OPEN_STATE)
            ->where('delete_time', 0)
            ->min('sort');

        $configSort = Db::table('saas_room_template_config')
            ->where('company_id', $this->request->user['company_id'])
            ->min('sort');
        //当前模板已经是最靠后位置,无法操作
        if ($currentSort <= $tplSort && $currentSort <= $configSort) {
            throw new ValidateException(lang('tpl_top'));
        }

        if ($tplSort && $configSort) {
            $min = min($tplSort, $configSort);
        } else {
            $min = $tplSort ?: $configSort;
        }

        $final = $min;

        Db::transaction(function () use ($final,$min, $model) {
            if ($model['company_id']) {
                Db::table('saas_room_template')
                    ->whereIn('company_id', [$this->request->user['company_id']])
                    ->where('id', $model['id'])
                    ->where('state', RoomTemplate::OPEN_STATE)
                    ->where('delete_time', 0)
                    ->update(['sort' => $final]);
            } else {
                Db::table('saas_room_template_config')
                    ->where('company_id', $this->request->user['company_id'])
                    ->where('room_template_id', $model['id'])
                    ->update(['sort' => $final]);
            }

            //更新当前节点后续的节点表1
            Db::table('saas_room_template')
                ->whereIn('company_id', [$this->request->user['company_id']])
                ->whereNotIn('id', [$model['id']])
                ->where('state', RoomTemplate::OPEN_STATE)
                ->where('delete_time', 0)
                ->inc('sort',$min)
                ->update();

            //更新当前节点后续节点表2
            Db::table('saas_room_template_config')
                ->where('company_id', $this->request->user['company_id'])
                ->whereNotIn('room_template_id', [$model['id']])
                ->inc('sort',$min)
                ->update();
        });

        return $this->success();
    }


    /**
     * 初始化默认模板排序信息到配置表
     */
    private function initDefaultTpl()
    {
        $companyId = $this->request->user['company_id'];
        $data = Db::table('saas_room_template')
            ->field('id as room_template_id,sort')
            ->where('company_id', 0)
            ->where('delete_time', 0)
            ->where('state', RoomTemplate::OPEN_STATE)
            ->select()
            ->toArray();

        if (!$data) {
            return false;
        }

        foreach ($data as &$value) {
            $value['company_id'] = $companyId;
        }

        return Db::table('saas_room_template_config')
            ->duplicate(['room_template_id' => Db::raw('VALUES(room_template_id)')])
            ->insertAll($data);
    }
}
