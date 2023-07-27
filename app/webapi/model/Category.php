<?php
declare (strict_types = 1);

namespace app\webapi\model;
use think\exception\ValidateException;
use app\common\http\HwCloud;

class Category extends Base
{

    protected $table = 'ch_category';

    protected $pk = 'id';

    protected $globalScope = ['company'];

    public static function onBeforeInsert($model)
    {
        parent::onBeforeInsert($model);

        $model->set('create_time',time());

    }

    public function watermark()
    {
        return $this->morphMany(Watermark::class, null, '2');
    }

    public static function onBeforeWrite($model)
    {
        parent::onBeforeWrite($model);

        validate(
            [
                'name' => 'unique:' . get_class($model) . ',parent_id^name'
            ],
            [
                'name' => lang('cate_name_exists'),
            ]
        )->check($model->toArray());

    }



    public function setParentIdAttr($value)
    {
        if ($value == 0) {
            $this->set('parent_id',0);
        } else {
            $pModel = $this->find($value);
            if (empty($pModel)) {
                throw new ValidateException(lang('cate_pid_not_exists'));
            }

            $this->set('parent_id',$value);
        }
        return $value;
    }


    /**
     * 更新视频分类名称
     * @param $params
     */
    public function updateInfo($name,$id)
    {
        $model = $this->where('id',$id)->findOrFail();

        $info  = $model->toArray();
        //同一分类下 不能存在相同名称
        if($this->where(['name'=>$name,'parent_id'=>$info['parent_id']])->find())
        {
            throw new ValidateException(lang('cate_name_exists'));
        }

        $api_params = [];
        $api_params['name'] = $name;
        $api_params['id']   = $info['category_id'];

        HwCloud::httpJson('asset/category', $api_params,[],'PUT');

        return $model->save(['name'=>$name]);
    }


    public function searchParentIdAttr($query, $value, $data)
    {
        $query->where('parent_id','=',$value)->visible(['id','name','parent_id']);
    }


    /**
     * 获取分类列表
     * @param $obj
     * @param $params
     */
    public function getCateList($list, $id)
    {

        $self_cat = $this->where('id', $id)->field('id,name,parent_id')->findOrFail();

        $list = $list->toArray();

        if (empty($list)) return $list;

        $list['category'] = $self_cat->toArray();

        $list['sub_category_total'] = $this->where('parent_id', $id)->count();

        $list['sub_categories'] = $list['data'];
        unset($list['data']);

        return $list;
    }


    /**
     * 删除单个分类(不允许从根分类开始删)
     * @param $params
     */
    public function doDelete($id)
    {
        $model = $this->findOrFail($id);

        $tree = $this->select();
        //得到该分类下的全部分类(不含自己)
        $hasChild = $tree->child($id, false, 'id', 'parent_id')->column('category_id');

        if ($hasChild) {
            throw new ValidateException(lang('exists_cate_child'));
        }

        HwCloud::httpJson('asset/category', [], ['id' => $model->category_id], 'DELETE');

        return $model->delete();
    }

}
