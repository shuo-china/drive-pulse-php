<?php

namespace app\wxapp\model;

use think\model\Pivot;

class UserChannel extends Pivot
{
    /**
     * 是否需要自动写入时间戳
     * @var bool
     */
    protected $autoWriteTimestamp = true;

    /**
     * 创建时间获取器
     * @param $value
     * @return string
     */
    public function getCreateTimeAttr($value)
    {
        return $value ? date('Y-m-d H:i', $value) : '';
    }

    /**
     * 更新时间获取器
     * @param $value
     * @return string
     */
    public function getUpdateTimeAttr($value)
    {
        return $value ? date('Y-m-d H:i', $value) : '';
    }
}
