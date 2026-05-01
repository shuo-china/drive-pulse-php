<?php

namespace app\wxapp\controller;

use app\wxapp\model\User;
use app\wxapp\model\Order;

class OrderController extends BaseController
{
    public function pagination()
    {
        $param = $this->request->param();
        $map = [];
        if (!empty($param['channel_id'])) {
            $map[] = ['channel_id', '=', $param['channel_id']];
        }
        if (!empty($param['user_id'])) {
            $map[] = ['user_id', '=', $param['user_id']];
        }
        if (!empty($param['target_user_id'])) {
            $map[] = ['target_user_id', '=', $param['target_user_id']];
        }
        if (!empty($param['nickname'])) {
            $map[] = ['user.nickname', 'like', '%' . $param['nickname'] . '%'];
        }
        if (!empty($param['uid'])) {
            $map[] = ['user.uid', '=', $param['uid']];
        }
        if (!empty($param['date'])) {
            $startTime = strtotime($param['date'] . ' 00:00:00');
            if ($startTime !== false) {
                $endTime = $startTime + 86399;
                $map[] = ['create_time', 'between', [$startTime, $endTime]];
            }
        }
 
        $orders = Order::with(['user', 'target_user'])->where($map)->paginate();
        $this->success(200, $orders);
    }

    public function create()
    {
        $post = $this->request->post();
        $targetUser = User::where('uid', $post['uid'])->find();

        Order::create([
            'user_id' => $this->request->userId,
            'target_user_id' => $targetUser['id'],
            'channel_id' => $post['channel_id'],
            'count' => $post['count'],
        ]);

        $this->success(201);
    }
}
