<?php

namespace app\wxapp\controller;

use app\wxapp\model\User;
use app\wxapp\model\Order;
use app\wxapp\model\Channel;

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
        if (!empty($param['date'])) {
            $startTime = strtotime($param['date'] . ' 00:00:00');
            if ($startTime !== false) {
                $endTime = $startTime + 86399;
                $map[] = ['create_time', 'between', [$startTime, $endTime]];
            }
        }

        if (!empty($param['user_nickname'])) {
            $userIds = User::whereLike('nickname', '%' . $param['user_nickname'] . '%')->column('id');
            $map[] = empty($userIds) ? ['user_id', '=', 0] : ['user_id', 'in', $userIds];
        }
        if (!empty($param['user_uid'])) {
            $userId = User::where('uid', '=', $param['user_uid'])->value('id');
            $map[] = empty($userId) ? ['user_id', '=', 0] : ['user_id', '=', $userId];
        }
        if (!empty($param['target_user_nickname'])) {
            $targetUserIds = User::whereLike('nickname', '%' . $param['target_user_nickname'] . '%')->column('id');
            $map[] = empty($targetUserIds) ? ['target_user_id', '=', 0] : ['target_user_id', 'in', $targetUserIds];
        }
        if (!empty($param['target_user_uid'])) {
            $targetUserId = User::where('uid', '=', $param['target_user_uid'])->value('id');
            $map[] = empty($targetUserId) ? ['target_user_id', '=', 0] : ['target_user_id', '=', $targetUserId];
        }

        $orders = Order::with(['user', 'targetUser'])->where($map)->order('create_time', 'desc')->paginate();
        $this->success(200, $orders);
    }

    public function create()
    {
        $post = $this->request->post();

        $targetUser = User::where('uid', $post['uid'])->find();
        if ($targetUser->balance_limit) {
            $targetUserReleaseCount = Order::where('channel_id', $post['channel_id'])->where('user_id', $targetUser['id'])->sum('count');
            $targetUserTakeCount = Order::where('channel_id', $post['channel_id'])->where('target_user_id', $targetUser['id'])->sum('count');
            $targetUserBalanceCount = $targetUserReleaseCount - $targetUserTakeCount;
            $balanceLimitCount = Channel::where('id', $post['channel_id'])->value('balance_limit_count');
            if ($targetUserBalanceCount - $post['count'] < $balanceLimitCount) {
                $errorMessage = "报单后对方结余为【" . ($targetUserBalanceCount - $post['count']) . "】，低于限制【" . $balanceLimitCount . "】，无法报单";
                $this->error(400, $errorMessage, 'BALANCE_LIMIT');
            }
        }

        Order::create([
            'user_id' => $this->request->userId,
            'target_user_id' => $targetUser['id'],
            'channel_id' => $post['channel_id'],
            'count' => $post['count'],
        ]);

        $this->success(201);
    }
}
