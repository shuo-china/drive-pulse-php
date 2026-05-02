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
        $user = User::where('id', $this->request->userId)->find();
        if ($user->balance_limit) {
            $userReleaseCount = Order::where('channel_id', $post['channel_id'])->where('user_id', $this->request->userId)->sum('count');
            $userTakeCount = Order::where('channel_id', $post['channel_id'])->where('target_user_id', $this->request->userId)->sum('count');
            $userBalanceCount = $userReleaseCount - $userTakeCount;
            if ($userBalanceCount + $post['count'] > config('sys.drive_pulse.balance_count_max')) {
                $errorMessage = "您的当前结余为{$userBalanceCount}，本次报单后您的结余为" . ($userBalanceCount + $post['count']) . "，高于限制" . config('sys.drive_pulse.balance_count_max');
                $this->error(400, $errorMessage, 'BALANCE_LIMIT');
            }
        }

        $targetUser = User::where('uid', $post['uid'])->find();
        if ($targetUser->balance_limit) {
            $targetUserReleaseCount = Order::where('channel_id', $post['channel_id'])->where('user_id', $targetUser['id'])->sum('count');
            $targetUserTakeCount = Order::where('channel_id', $post['channel_id'])->where('target_user_id', $targetUser['id'])->sum('count');
            $targetUserBalanceCount = $targetUserReleaseCount - $targetUserTakeCount;
            if ($targetUserBalanceCount - $post['count'] < config('sys.drive_pulse.balance_count_min')) {
                $errorMessage = "对方当前结余为{$targetUserBalanceCount}，本次报单后对方结余为" . ($targetUserBalanceCount - $post['count']) . "，低于限制" . config('sys.drive_pulse.balance_count_min');
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
