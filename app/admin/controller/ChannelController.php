<?php

namespace app\admin\controller;

use app\admin\model\Channel;
use app\admin\model\UserChannel;

class ChannelController extends BaseController
{
    public function pagination()
    {
        $channelsUserCount = UserChannel::where('audit_status', 2)
            ->field('channel_id, COUNT(*) as count')
            ->group('channel_id')
            ->select()
            ->column('count', 'channel_id');

        $channels = Channel::paginate()->each(function ($item) use ($channelsUserCount) {
            $item->count = $channelsUserCount[$item->id] ?? 0;
        });

        $this->success(200, $channels);
    }

    public function options()
    {
        $channels = Channel::where('status', 1)->select();
        $this->success(200, $channels);
    }

    public function detail()
    {
        $id = $this->request->param('id');
        $channel = Channel::find($id);
        $this->success(200, $channel);
    }

    public function create()
    {
        $post = $this->request->post();
        Channel::create($post);
        $this->success(201);
    }

    public function update()
    {
        $post = $this->request->post();
        Channel::update($post);
        $this->success(201);
    }

    public function delete()
    {
        $id = $this->request->param('id');
        $channel = Channel::destroy($id);
        $this->success(204);
    }
    public function applyPagination()
    {
        $userChannels = UserChannel::with(['user', 'channel'])->where('audit_status', '=', 1)->paginate();
        $this->success(200, $userChannels);
    }

    public function audit()
    {
        $post = $this->request->post();
        $post['audit_time'] = time();
        UserChannel::update($post);
        $this->success(201);
    }
}
