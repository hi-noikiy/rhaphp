<?php
// +----------------------------------------------------------------------
// | [RhaPHP System] Copyright (c) 2017 http://www.rhaphp.com/
// +----------------------------------------------------------------------
// | [RhaPHP] 并不是自由软件,你可免费使用,未经许可不能去掉RhaPHP相关版权
// +----------------------------------------------------------------------
// | Author: Geeson <qimengkeji@vip.qq.com>
// +----------------------------------------------------------------------


namespace app\mp\controller;


use app\common\model\Addons;
use app\common\model\MpFriends;
use app\common\model\MpMsg;
use app\common\model\MpReply;
use app\common\model\MpRule;
use app\common\model\Qrcode;
use think\Db;
use think\Request;
use think\Session;
use think\Url;
use think\Validate;

class Mp extends Base
{

    public function _initialize()
    {
        parent::_initialize(); // TODO: Change the autogenerated stub

    }

    public function index()
    {
        $this->getAppStore();
        $model = new MpFriends();
        $result = $model->getFriendReport($this->mid);
        $this->assign('report', $result);
        return view();
    }


    /**
     * 自动回复
     * @author 314835050@qq.com
     * @param string $type
     * @return \think\response\View
     */
    public function autoReply($type = 'text')
    {
        switch ($type) {
            case 'text':
                $this->getRepayList('text');
                break;
            case 'news':
                $this->getRepayList('news');
                break;
            case 'addon':
                $ruleModel = new MpRule();
                $data = $ruleModel->alias('r')
                    ->where(['r.mpid' => $this->mid, 'r.type' => 'addon'])
                    ->join('__ADDONS__ a', 'a.addon=r.addon')->field('r.keyword,r.id,r.mpid,r.addon,r.type,r.status,a.name,a.desc,a.logo')
                    ->select();
                $this->assign('data', $data);
                $this->assign('type', $type);
                break;
            case 'voice':
                $this->getRepayList('voice');
                break;
            case 'image':
                $this->getRepayList('image');
                break;
            case 'video':
                $this->getRepayList('video');
                break;
            case 'music':
                $this->getRepayList('music');
                break;
            default:

                return view();
                break;

        }
        return view();
    }

    public function getRepayList($type)
    {
        $rePly = Db::name('mp_rule')->alias('r')
            ->where(['r.mpid' => $this->mid, 'r.type' => $type])
            ->join('__MP_REPLY__ p', 'p.reply_id=r.reply_id')
            ->order('r.id DESC')
            ->paginate(10);

        $this->assign('data', $rePly);
        $this->assign('type', $type);
    }

    /**
     * 增加关键词
     * @author geeson 314835050@qq.com
     * @return \think\response\View
     */
    public function addKeyword()
    {
        if (Request::instance()->isPost()) {
            $input = input();
            if (!isset($input['status'])) {
                $input['status'] = '0';
            }
            $data['mpid'] = $this->mid;
            $data['keyword'] = trim($input['keyword']);
            $data['status'] = $input['status'];

            $ruleModel = new MpRule();
            $replyMode = new MpReply();
            $validate = new Validate(
                [
                    'keyword' => 'require',
                ],
                [
                    'keyword.require' => '关键词不能为空',
                ]
            );
            $result = $validate->check($input);
            if ($result === false) {
                ajaxMsg(0, $validate->getError());
            }
            switch ($input['type']) {
                case 'text':
                    $data['content'] = $input['content'];
                    $data['type'] = 'text';
                    $validate = new Validate(
                        [
                            'content' => 'require'

                        ],
                        [
                            'content.require' => '回复内容不能为空',
                        ]
                    );

                    $result = $validate->check($data);
                    if ($result === false) {
                        ajaxMsg(0, $validate->getError());
                    }
                    $material = [
                        'mpid' => $this->mid,
                        'content' => $data['content']
                    ];
                    $this->material($input['type'], $material);
                    if ($reply_id = Db::name('mp_reply')->insertGetId(['content' => $input['content'], 'type' => 'text'])) {
                        $data['reply_id'] = $reply_id;
                        $rule = $ruleModel->allowField(true)->save($data);
                    }
                    if ($reply_id && $rule) {
                        ajaxReturn(['url' => getHostDomain() . url('mp/Mp/autoreply', ['type' => 'text'])], 1, '提交成功');
                    } else {
                        ajaxMsg(0, '提交失败');
                    }
                    break;
                case 'news':

                    $validate = new Validate(
                        [
                            'title' => 'require',
                            'picurl' => 'require',
                            'link' => 'require',
                        ],
                        [
                            'title.require' => '标题不能为空',
                            'picurl.require' => '请上传图文封面图',
                            'link.require' => '连接不能为空',
                        ]
                    );
                    $result = $validate->check(input());
                    if ($result === false) {
                        ajaxMsg(0, $validate->getError());
                    }
                    $data['title'] = $input['title'];
                    $data['url'] = $input['picurl'];
                    $data['content'] = $input['news_content'];
                    $data['link'] = $input['link'];
                    $data['type'] = 'news';
                    if ($res_1 = $replyMode->allowField(true)->save($data)) {
                        $data['reply_id'] = $replyMode->reply_id;
                        if (!$res_2 = $ruleModel->allowField(true)->save($data)) {
                            $replyMode::destroy(['reply_id' => $data['reply_id']]);
                        }
                    }
                    if ($res_1 && $res_2) {
                        ajaxReturn(['url' => getHostDomain() . url('mp/Mp/autoreply', ['type' => 'news'])], 1, '提交成功');
                    } else {
                        ajaxMsg(0, '提交失败');
                    }
                    break;
                case 'addon':
                    $validate = new Validate(
                        [
                            'addons' => 'require',
                        ],
                        [
                            'addons.require' => '应用不能为空',
                        ]
                    );
                    $result = $validate->check(input());
                    if ($result === false) {
                        ajaxMsg(0, $validate->getError());
                    }
                    $data['keyword'] = $input['keyword'];
                    $data['type'] = 'addon';
                    $data['addon'] = $input['addons'];

                    if ($ruleModel->allowField(true)->save($data)) {
                        ajaxReturn(['url' => getHostDomain() . url('mp/Mp/autoreply', ['type' => 'addon'])], 1, '提交成功');
                    } else {
                        ajaxMsg(0, '提交失败');
                    }
                    break;
                case 'voice':
                    $validate = new Validate(
                        [
                            'voice_title' => 'require',
                            'voice' => 'require',
                        ],
                        [
                            'voice_title.require' => '语音名称不能为空',
                            'voice.require' => '请上传语音',
                        ]
                    );

                    $result = $validate->check(input());
                    if ($result === false) {
                        ajaxMsg(0, $validate->getError());
                    }
                    $filePath = explode(getHostDomain(), $input['voice']);
                    if (isset($filePath[1]) && !empty($filePath[1])) {//认为新上传
                        $data['path'] = $filePath[1];
                        if ($input['voice_staus_type'] == '0') {
                            $media = uploadMedia($filePath[1], 'voice');
                        }
                        if ($input['voice_staus_type'] == '1') {

                            $media = uploadForeverMedia($filePath[1], 'voice');

                        }

                    } else {//认为选择了永久或者暂时音频
                        if (isset($filePath[0])) {
                            $materialModel = new \app\common\model\Material();
                            $materialArray = $materialModel->getMaterialByFind(['media_id' => $filePath[0], 'mpid' => $this->mid]);
                            if ($materialArray['from_type'] == 0) {//临时
                                if (empty($materialArray['url'])) ajaxMsg('0', '失败！资源地址为空');
                                $filePath = explode(getHostDomain(), $materialArray['url']);
                                if ($input['voice_staus_type'] == '0') {
                                    $media = uploadMedia($filePath[1], 'voice');
                                }
                                if ($input['voice_staus_type'] == '1') {

                                    $media = uploadForeverMedia($filePath[1], 'voice');

                                }

                            } else {//永久
                                $media['media_id'] = $materialArray['media_id'];
                            }
                        }
                    }
                    $data['title'] = $input['voice_title'];
                    $data['url'] = $input['voice'];
                    $data['type'] = 'voice';
                    $data['status_type'] = $input['voice_staus_type'];
                    $data['media_id'] = $media['media_id'];
                    $material = [
                        'mpid' => $this->mid,
                        'title' => $data['title'],
                        'url' => $data['url'],
                        'media_id' => $data['media_id']
                    ];
                    $this->material($input['type'], $material, $input['voice_staus_type']);
                    if ($res_1 = $replyMode->allowField(true)->save($data)) {
                        $data['reply_id'] = $replyMode->reply_id;
                        if (!$res_2 = $ruleModel->allowField(true)->save($data)) {
                            $replyMode::destroy(['reply_id' => $data['reply_id']]);
                        }
                    }
                    if ($res_1 && $res_2) {
                        ajaxReturn(['url' => getHostDomain() . url('mp/Mp/autoreply', ['type' => 'voice'])], 1, '提交成功');
                    } else {
                        ajaxMsg(0, '提交失败');
                    }
                    break;
                case 'image':
                    set_time_limit(0);
                    $materialModel = new \app\common\model\Material();
                    $validate = new Validate(
                        [
                            'reply_image' => 'require',
                        ],
                        [
                            'reply_image.require' => '请上传图片',
                        ]
                    );
                    $result = $validate->check(input());
                    if ($result === false) {
                        ajaxMsg(0, $validate->getError());
                    }
                    $sting=getSetting($this->mid,'cloud');
                    if(isset($sting['qiniu']['status']) && $sting['qiniu']['status']==1){
                            $ext=strrchr($input['reply_image'],'.');
                            $fileName_h=md5(rand_string(12)).$ext;
                        $filePath[1]=dowloadImage($input['reply_image'],'./uploads/',$fileName_h);
                        if(!$filePath[1]){
                            ajaxMsg(0, '获取图片失败');
                        }
                    }else{
                        $ext=strrchr($input['reply_image'],'.');
                        $fileName_h=md5(rand_string(12)).$ext;
                        $filePath[1]=dowloadImage($input['reply_image'],'./uploads/',$fileName_h);
                       // $filePath = explode(getHostDomain(),$input['reply_image']);//strpos
                    }
                    if (!strpos($input['reply_image'], 'show/image') || !strpos($input['reply_image'], 'qpic.cn')) {
                        //认为本地资源或者新上传
                        if (isset($filePath[1]) && !empty($filePath[1])) {
                            $data['path'] = $filePath[1];
                            if ($input['image_staus_type'] == '0') {
                                $media = uploadMedia($filePath[1], 'image');
                            }
                            if ($input['image_staus_type'] == '1') {
                                $media = uploadForeverMedia($filePath[1], 'image');
                            }

                        }
                    } else {
                        //认为永久、类型永久或者临时请忽略
                        $materialArray = $materialModel->getMaterialByFind(['url' => $input['reply_image'], 'mpid' => $this->mid]);
                        $media['media_id'] = $materialArray['media_id'];

                    }
                    $data['url'] = $input['reply_image'];
                    $data['type'] = 'image';
                    $data['media_id'] = $media['media_id'];
                    $data['status_type'] = $input['image_staus_type'];
                    $material = [
                        'mpid' => $this->mid,
                        'url' => $data['url'],
                        'media_id' => $data['media_id']
                    ];
                    $this->material($input['type'], $material, $input['image_staus_type']);
                    if ($res_1 = $replyMode->allowField(true)->save($data)) {
                        $data['reply_id'] = $replyMode->reply_id;
                        if (!$res_2 = $ruleModel->allowField(true)->save($data)) {
                            $replyMode::destroy(['reply_id' => $data['reply_id']]);
                        }
                    }
                    if ($res_1 && $res_2) {
                        ajaxReturn(['url' => getHostDomain() . url('mp/Mp/autoreply', ['type' => 'image'])], 1, '提交成功');
                    } else {
                        ajaxMsg(0, '提交失败');
                    }
                    break;
                case 'video':
                    $validate = new Validate(
                        [
                            'video_title' => 'require',
                            'reply_video' => 'require',
                        ],
                        [
                            'video_title.require' => '视频标题不能为空',
                            'reply_video.require' => '请上传视频',
                        ]
                    );
                    $result = $validate->check(input());
                    if ($result === false) {
                        ajaxMsg(0, $validate->getError());
                    }
                    $filePath = explode(getHostDomain(), $input['reply_video']);
                    if (isset($filePath[1]) && !empty($filePath[1])) {
                        if ($input['video_staus_type'] == '0') {
                            $media = uploadMedia($filePath[1], 'video');
                        }
                        if ($input['video_staus_type'] == '1') {

                            $media = uploadForeverMedia($filePath[1], 'video', true, ['title' => $input['video_title'], 'introduction' => $input['video_content']]);

                        }

                    } else {//认为选择了永久或者暂时音频
                        if (isset($filePath[0])) {
                            $materialModel = new \app\common\model\Material();
                            $materialArray = $materialModel->getMaterialByFind(['media_id' => $filePath[0], 'mpid' => $this->mid]);
                            if ($materialArray['from_type'] == 0) {//临时
                                if (empty($materialArray['url'])) ajaxMsg('0', '失败！资源地址为空');
                                $filePath = explode(getHostDomain(), $materialArray['url']);
                                if ($input['video_staus_type'] == '0') {
                                    $media = uploadMedia($filePath[1], 'video');
                                }
                                if ($input['video_staus_type'] == '1') {

                                    $media = uploadForeverMedia($filePath[1], 'video', true, ['title' => $input['video_title'], 'introduction' => $input['video_content']]);

                                }

                            } else {//永久
                                $media['media_id'] = $materialArray['media_id'];
                            }
                        }
                    }
                    $data['title'] = $input['video_title'];
                    $data['content'] = $input['video_content'];
                    $data['url'] = $input['reply_video'];
                    $data['type'] = 'video';
                    $data['status_type'] = $input['video_staus_type'];
                    $data['media_id'] = $media['media_id'];
                    $material = [
                        'mpid' => $this->mid,
                        'title' => $data['title'],
                        'content' => $data['content'],
                        'url' => $data['url'],
                        'media_id' => $data['media_id']
                    ];
                    $this->material($input['type'], $material, $input['video_staus_type']);
                    if ($res_1 = $replyMode->allowField(true)->save($data)) {
                        $data['reply_id'] = $replyMode->reply_id;
                        if (!$res_2 = $ruleModel->allowField(true)->save($data)) {
                            $replyMode::destroy(['reply_id' => $data['reply_id']]);
                        }
                    }
                    if ($res_1 && $res_2) {
                        ajaxReturn(['url' => getHostDomain() . url('mp/Mp/autoreply', ['type' => 'video'])], 1, '提交成功');
                    } else {
                        ajaxMsg(0, '提交失败');
                    }
                    break;
                case 'music':
                    $validate = new Validate(
                        [
                            'music_title' => 'require',
                            'music' => 'require',
                        ],
                        [
                            'music_title.require' => '音乐标题不能为空',
                            'music.require' => '请上传音乐',
                        ]
                    );
                    $result = $validate->check(input());
                    if ($result === false) {
                        ajaxMsg(0, $validate->getError());
                    }
                    $data['title'] = $input['music_title'];
                    $data['url'] = $input['music'];
                    $data['link'] = $input['music_link'];
                    $data['type'] = 'music';
                    $data['content'] = $input['music_content'];
                    $material = [
                        'mpid' => $this->mid,
                        'title' => $data['title'],
                        'content' => $data['content'],
                        'url' => $data['url'],
                    ];
                    $this->material($input['type'], $material);
                    if ($res_1 = $replyMode->allowField(true)->save($data)) {
                        $data['reply_id'] = $replyMode->reply_id;
                        if (!$res_2 = $ruleModel->allowField(true)->save($data)) {
                            $replyMode::destroy(['reply_id' => $data['reply_id']]);
                        }
                    }
                    if ($res_1 && $res_2) {
                        ajaxReturn(['url' => getHostDomain() . url('mp/Mp/autoreply', ['type' => 'voice'])], 1, '提交成功');
                    } else {
                        ajaxMsg(0, '提交失败');
                    }
                    break;
                default:

                    return view();
                    break;

            }
        } else {
            $addons = Db::name('addons')->order('id Asc')->select();
            $this->assign('addons', $addons);
            return view('addkeyword');
        }

    }

    public function delRule($id = '')
    {
        $where = [
            'id' => $id,
            'mpid' => $this->mid
        ];
        $model = new MpRule();
        if ($model->delRule($where)) {
            ajaxMsg(1, '成功删除');
        } else {
            ajaxMsg(0, '删除失败');
        }
    }

    public function updateRule($id = '', $status = '')
    {
        $model = new MpRule();
        $model->save(['status' => $status], ['id' => $id, 'mpid' => $this->mid]);
        ajaxMsg(1, '改变状态成功');
    }

    public function material($type = '', $data = [], $materialStatus = 0)
    {
        $model = new \app\common\model\Material();
        $model->addMaterial($type, $data, $materialStatus);
    }

    /**
     * 处理特殊消息
     * @author GEESON 314835050@QQ.COM
     * @return \think\response\View
     */
    public function special()
    {
        if (Request::instance()->isPost()) {
            $td = input();
            foreach ($td as $key => $val) {
                switch ($key) {
                    case 'image':
                        $this->doSpecial('image', $td);
                        break;
                    case 'voice':
                        $this->doSpecial('voice', $td);
                        break;
                    case 'video':
                        $this->doSpecial('video', $td);
                        break;
                    case 'shortvideo':
                        $this->doSpecial('shortvideo', $td);
                        break;
                    case 'location':
                        $this->doSpecial('location', $td);
                        break;
                    case 'event_location':
                        $this->doSpecial('event_location', $td);
                        break;
                    case 'link':
                        $this->doSpecial('link', $td);
                        break;
                    case 'view':
                        $this->doSpecial('view', $td);
                        break;
                    case 'subscribe':
                        $this->doSpecial('subscribe', $td);
                        break;
                    case 'unsubscribe':
                        $this->doSpecial('unsubscribe', $td);
                        break;
                }
            }
            ajaxMsg('1', '保存成功');


        } else {
            $where = [
                '0' => 'image',
                '1' => 'voice',
                '2' => 'video',
                '3' => 'shortvideo',
                '4' => 'location',
                '5' => 'event_location',
                '6' => 'link',
                '7' => 'view',
                '8' => 'subscribe',
                '9' => 'unsubscribe',
            ];
            foreach ($where as $key => $v) {
                $result = Db::name('mp_rule')
                    ->where('event', 'eq', $v)
                    ->where(['mpid' => $this->mid])
                    ->field('addon,keyword,event')->find();
                if (empty($result)) {
                    $arr = [
                        'event' => 'nocol',
                        'keyword' => '',
                        'addon' => '',
                    ];
                    $this->assign($v, $arr);
                } else {
                    $this->assign($v, $result);
                }

            }
            $addons = Db::name('addons')->order('id Asc')->select();
            $this->assign('addons', $addons);
            return view();
        }
    }

    public function doSpecial($type, $data)
    {
        switch ($data[$type]) {
            case 'nocol':
                Db::name('mp_rule')->where(['mpid' => $this->mid, 'event' => $type])->delete();
                break;
            case 'keyword':
                $key = $type . '_keyword';
                if ($data[$key]) {
                    if (Db::name('mp_rule')->where(['mpid' => $this->mid, 'event' => $type])->find()) {
                        Db::name('mp_rule')->where(['mpid' => $this->mid, 'event' => $type])->update(['keyword' => $data[$key], 'addon' => null]);
                    } else {
                        Db::name('mp_rule')->insert(['mpid' => $this->mid, 'event' => $type, 'keyword' => $data[$key]]);
                    }
                } else {
                    ajaxMsg('0', '表单中存在关键词没有填写');
                }
                break;
            case 'addon':
                $key = $type . '_addons';
                if ($data[$key]) {
                    if (Db::name('mp_rule')->where(['mpid' => $this->mid, 'event' => $type])->find()) {
                        Db::name('mp_rule')->where(['mpid' => $this->mid, 'event' => $type])->update(['addon' => $data[$key], 'keyword' => null]);
                    } else {
                        Db::name('mp_rule')->insert(['mpid' => $this->mid, 'event' => $type, 'addon' => $data[$key]]);
                    }
                } else {
                    ajaxMsg('0', '表单中存在应用没有选择');
                }
                break;
        }


    }

    /**
     * 自定义菜单首页
     * Eddy 沈阳 0711
     */
    public function menu()
    {
        ## 查询当前公众号的自定义菜单
        $mid = Session::get('mid');
        $list = Db::name('mp_menu')
            ->where(['mp_id' => $mid, 'pindex' => 0])
            ->select();

        $data = [];
        ## 如果表中存在，则直接读取；否则通过接口获取自定义菜单，并写入数据库
        if (!empty($list)) {
            foreach ($list as $key => $item) {
                $list[$key]['sub'] = Db::name('mp_menu')
                    ->where(['mp_id' => $mid, 'pindex' => $item['index']])
                    ->select();
            }
            $data['list'] = $list;
        } else {

            $data['list'] = array();
        }
        return view('', $data);
    }

    public function delMpMenu()
    {
        deleteMpMenu();
        Db::name('mp_menu')
            ->where(['mp_id' => $this->mid,])
            ->delete();

    }

    /**
     * 自定义菜单保存编辑
     * Eddy 0711
     */
    public function menuedit()
    {
        if (Request::instance()->isAjax()) {

            $post = input('post.');
            $data = $post['data'];
            if(empty($data)){
                deleteMpMenu();//空菜单认为删除全部菜单
                ajaxMsg(1, '保存成功');
            }
            Db::name('mp_menu')
                ->where(['mp_id' => $this->mid,])
                ->delete();

            foreach ($data as $key => $vo) {
                if (isset($vo['content'])) {
                    $data[$key]['content'] = str_replace('"', "'", $vo['content']);
                }
                $data[$key]['mp_id'] = $this->mid;
            }
            $_S = false;
            foreach ($data as $key => $val) {
                $_S = Db::name('mp_menu')->insert($data[$key]);
            }
            if ($_S) {
                $result = Db::name('mp_menu')
                    ->field('id,index,pindex,name,type,content')
                    ->where('status', '1')
                    ->where('mp_id',$this->mid)
                    ->order('sort ASC,id ASC')
                    ->select();
                $menu_type = [
                    'view' => '跳转URL',
                    'click' => '点击推事件',
                    'scancode_push' => '扫码推事件',
                    'scancode_waitmsg' => '扫码推事件且弹出“消息接收中”提示框',
                    'pic_sysphoto' => '弹出系统拍照发图',
                    'pic_photo_or_album' => '弹出拍照或者相册发图',
                    'pic_weixin' => '弹出微信相册发图器',
                    'location_select' => '弹出地理位置选择器',
                ];
                foreach ($result as &$row) {
                    empty($row['content']) && $row['content'] = uniqid();
                    switch ($row['type']) {
                        case 'miniprogram':
                            list($row['appid'], $row['url'], $row['pagepath']) = explode(',', $row['content'] . ',,');
                            break;
                        case 'view':
                            $row['url'] = preg_match('#^(\w+:)?//#i', $row['content']) ? $row['content'] : url($row['content'], '', true, true);
                            break;
                        case 'event':
                            if (isset($menu_type[$row['content']])) {
                                $row['type'] = $row['content'];
                                $row['key'] = "wechat_menu#id#{$row['id']}";
                            }
                            break;
                        case 'media_id':
                            $row['media_id'] = $row['content'];
                            break;
                        default :
                            (!in_array($row['type'], $menu_type)) && $row['type'] = 'click';
                            $row['key'] = "{$row['content']}";
                    }
                    unset($row['content']);
                }
                $menus = GetRreeByMpMenu($result, 'index', 'pindex', 'sub_button');
                foreach ($menus as &$menu) {
                    unset($menu['index'], $menu['pindex'], $menu['id']);
                    if (empty($menu['sub_button'])) {
                        continue;
                    }
                    foreach ($menu['sub_button'] as &$submenu) {
                        unset($submenu['index'], $submenu['pindex'], $submenu['id']);
                    }
                    unset($menu['type']);
                }
                $result = createMpMenu(['button' => $menus]);
                if (isset($result->errCode)) {
                    ajaxMsg(0, 'errCode: ' . $result->errCode . ' errMsg: ' . $result->errMsg);
                } elseif ($result == true) {
                    ajaxMsg(1, '发布成功');
                }
            }

        }
    }

    /**
     * 微信功能配置
     * @author geeson 314835050@qq.com
     * @param string $type
     * @return \think\response\View
     */
    public function mpSetting($type = 'wxpay')
    {
        if (Request::instance()->isPost()) {
            $input = input('post.');
            Db::name('setting')->where(['name' => $input['setting_name'], 'mpid' => $this->mid])->delete();
            $data['name'] = $input['setting_name'];
            $data['mpid'] = $this->mid;
            $data['value'] = json_encode($input);
            if (Db::name('setting')->insert($data)) {
                ajaxMsg('1', '配置成功');
            } else {
                ajaxMsg('0', '配置失败了');
            }
        } else {
            switch ($type) {
                case 'wxpay':
                    $result = Db::name('setting')->where(['name' => $type, 'mpid' => $this->mid])->find();
                    $arr1 = [
                        'appid' => '',
                        'appsecret' => '',
                        'mchid' => '',
                        'paysignkey' => '',
                        'apiclient_cert' => '',
                        'apiclient_key' => '',
                        'setting_name' => '',
                    ];
//                    if (empty($result)) {
//                        $config = $arr1;
//                    } else {
//                        $config = diffArrayValue($arr1, json_decode($result['value'], true));
//                    }
                    $array=json_decode($result['value'], true);
                    $arr2=$array?$array:[];
                    $config=array_merge($arr1,$arr2);
                    $this->assign('payUrl',getHostDomain().Url::build('service/Payment/wxPay','',false));
                    $this->assign('config', $config);
                    break;
                case 'uploadjsfile':

                    break;
                case 'sms':
                    $result = Db::name('setting')->where(['name' => $type, 'mpid' => $this->mid])->find();

                    $arr1 = [
                        'txsms' => [
                            'appid' => '',
                            'appsecret' => '',
                        ],
                        'alisms' => [
                            'appid' => '',
                            'appsecret' => '',
                        ]
                    ];
                    $config = json_decode($result['value'], true);
                    foreach ($arr1 as $key=>$val){
                        if(isset($config[$key])){
                            $config[$key]=array_merge($arr1[$key],$config[$key]);
                        }
                    }
                    $this->assign('config', $config);
                    break;
                case 'cloud':
                    $result = Db::name('setting')->where(['name' => $type, 'mpid' => $this->mid])->find();

                    $arr1 = [
                        'qiniu' => [
                            'accessKey' => '',
                            'secretKey' => '',
                            'bucke'=>'',
                            'domain'=>'',
                            'status'=>'',
                        ],

                    ];
                    $config = json_decode($result['value'], true);
                    foreach ($arr1 as $key=>$val){
                        if(isset($config[$key])){
                            $config[$key]=array_merge($arr1[$key],$config[$key]);
                        }
                    }
                    $this->assign('config', $config);
                    break;
            }

            $this->assign('mpInfo', $this->mpInfo);
            $this->assign('type', $type);
            return view('mpsetting');
        }
    }

    /**
     * 二维码/统计
     * @author geeson 314835050@qq.com
     * @param string $type
     * @return \think\response\View
     */
    public function qrcode($type = 'list')
    {
        if (Request::instance()->isPost()) {
        } else {
            $qrModel = $qrModel = new Qrcode();
            if ($type == 'list') {
                $data = $qrModel->where(['mpid' => $this->mid])->order('id DESC')->paginate(10);
                $this->assign('data', $data);
            }
            if ($type == 'statistics') {
                $data = $qrModel->where(['mpid' => $this->mid])->order('id DESC')->paginate(10);
                $this->assign('data', $data);
            }
            if ($type == 'friend') {
                $data=Db::name('qrcode_data')->alias('a')->where(['a.scene_id'=>input('scene_id'),'a.mpid'=>$this->mid,'a.type'=>'1'])
                    ->join('__MP_FRIENDS__ b','a.openid=b.openid')
                    ->order('a.create_time DESC')
                    ->field('a.*,b.nickname,b.headimgurl')
                    ->paginate(15);
                $this->assign('data', $data);
            }
            $this->assign('type', $type);
            return view();
        }
    }

    /**
     * 增加二维码
     * @author geeson 314835050@qq.com
     * @return \think\response\View
     */
    public function qrcodeAdd()
    {
        if (Request::instance()->isPost()) {
            $qrModel = new Qrcode();
            $IN = input();
            if ($qrModel->where(['scene_name'=>$IN['name'],'mpid'=>$this->mid])->find()) {
                ajaxMsg('0', '场景名称已经存在');
            }
            $data['mpid'] = $this->mid;
            $data['keyword'] = $IN['keyword'];
            $data['scene_name'] = $IN['name'];
            $data['qr_type'] = $IN['qr_type'];
            $data['scene_str'] = $IN['scene_str'];
            $data['create_time'] = time();
            $data['scene_id'] = mt_rand(1, 1000000);
            if ($data['qr_type'] == 0) {//临时二维码
                $data['expire'] = $IN['time'];
                if ($data['expire'] == '') {
                    $data['expire'] = 1800;
                }

                $result = get_qrcode($data['scene_id'], $data['qr_type'], $data['expire']);
                $data['ticket'] = $result['ticket'];
                $data['qrcode_url'] = getQrRUL($result['ticket']);
                $data['short_url'] = getQrshortUrl($data['qrcode_url']);
                $data['expire'] = $result['expire_seconds'];
                $data['url'] = $result['url'];
                if ($data['short_url'] == '') {
                    ajaxMsg('0', 'ErrMsg: 生成二维码短连接失败');
                }
                if (Qrcode::get(['scene_id' => $data['scene_id']])) {
                    $data['scene_id'] = mt_rand(1, 1000000);
                }
                if ($qrModel->save($data)) {
                    ajaxReturn(['url' => getHostDomain() . url('mp/Mp/qrcode', ['type' => 'list'])], 1, '增加二维码成功');
                }
            }
            if ($data['qr_type'] == 2) {//永久二维码
                if (!$data['scene_str']) {
                    $data['scene_str'] = $data['scene_id'];
                }
                if (strlen($data['scene_str']) > 64) {
                    ajaxMsg('0', 'ErrMsg: 场景值字符小于64个字符');
                }
                $result = get_qrcode($data['scene_str'], $data['qr_type']);
                $data['ticket'] = $result['ticket'];
                $data['qrcode_url'] = getQrRUL($result['ticket']);
                $data['short_url'] = getQrshortUrl($data['qrcode_url']);
                $data['url'] = $result['url'];
                if ($data['short_url'] == '') {
                    ajaxMsg('0', 'ErrMsg: 生成二维码短连接失败');
                }
                if (Qrcode::get(['scene_id' => $data['scene_id']])) {
                    $data['scene_id'] = mt_rand(1, 1000000);
                }
                $qrModel = new Qrcode();
                if ($qrModel->save($data)) {
                    ajaxReturn(['url' => getHostDomain() . url('mp/Mp/qrcode', ['type' => 'list'])], 1, '增加二维码成功');
                }

            }

        } else {
            return view('qrcode_add');
        }
    }

    public function getAppStore(){
        $data=[];
        $app=[];
        $result=getAppAndWindvaneByApi();
        if($result != false){
            if(isset($result['status']) && isset($result['data'])){
                if($result['status']==1){
                    $data=isset($result['data'])?$result['data']:[];
                    $app=isset($result['app'])?$result['app']:[];
                }
            }
        }
        $this->assign('app_by_api',$app);
        $this->assign('data_by_api',$data);
    }



}
