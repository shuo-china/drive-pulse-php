<?php

namespace app\admin\controller;

use app\admin\model\Order;

class OrderController extends BaseController
{
    public function pagination()
    {
        $map = [];

        $param = $this->request->param();
        if (!empty($param['channel_id'])) {
            $map['channel_id'] = $param['channel_id'];
        }
        if (!empty($param['user_id'])) {
            $map['user_id'] = $param['user_id'];
        }
        if (!empty($param['target_user_id'])) {
            $map['target_user_id'] = $param['target_user_id'];
        }

        $order = new Order();
        $orders = $order->where($map)->with(['user', 'target_user'])->paginate();
        $this->success(200, $orders);
    }
}