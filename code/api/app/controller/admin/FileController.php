<?php

namespace app\controller\admin;

use app\ApiBaseController;
use app\common\exception\CommonException;
use app\common\helper\PageHelper;
use app\common\logic\FileLogic;
use app\common\service\ZincSearchService;
use app\job\TransformJob;
use app\model\File;
use app\model\User;
use think\exception\ValidateException;
use think\facade\Db;
use think\facade\Queue;
use think\helper\Str;
use ZipStream\Option\Archive;
use ZipStream\ZipStream;

class FileController extends ApiBaseController
{
    /**
     * 上传文件
     * @return \think\response\Json|\think\response\Jsonp
     * @throws CommonException
     * @author 我只想看看蓝天 <1207032539@qq.com>
     */
    public function uploadFile()
    {
        $files = request()->file();
        if (empty($files)) {
            throw new CommonException('上传文件不能为空');
        }
        $filesize = 1024 * 1024 * 10;
//        $ext = 'doc,docx,xls,xlsx,ppt,pptx,txt,pdf';
        try {
            validate([
//                'files' => "fileSize:{$filesize}|fileExt:{$ext}"
                'files' => "fileSize:{$filesize}"
            ])->check($files);
            $files = $files['files'];
            foreach ($files as $file) {
                $mime_type = mime_content_type($file->getRealPath());
                $name = $file->getOriginalName();
                if (!Str::startsWith($mime_type, 'text')
                    &&
                    !preg_match('/\.(doc|docx|xls|xlsx|ppt|pptx|pdf)$/i', $name)) {
                    throw new CommonException("【{$name}】文件上传失败，暂不支持该文件类型");
                }
            }
            $data = [];
            foreach ($files as $file) {
                $md5 = $file->md5();
                $name = $file->getOriginalName();
                if ($exist_name = File::where('md5', $md5)->where('creator_uid', is_login())->value('name')) {
                    throw new CommonException("【{$name}】文件上传失败，当前用户已上传的相同文件【{$exist_name}】");
                }
                $path = \think\facade\Filesystem::disk('public')->putFile('doc', $file);
                $path = \think\facade\Filesystem::disk('public')->url($path);
                if (preg_match('/\.txt/i', $path)) {
                    FileLogic::transformTextFileToUtf8(app()->getRootPath() . 'public' . $path);
                }
                $data[] = [
                    'name' => $name,
                    'path' => $path,
                    'size' => $file->getSize(),
                    'md5' => $md5,
                    'creator_uid' => is_login(),
                    'create_time' => time(),
                    'update_time' => time(),
                ];
            }

            File::insertAll($data);
            Queue::push(TransformJob::class);
        } catch (ValidateException $e) {
            throw new CommonException($e->getMessage());
        }
        return $this->successResponse('上传成功');
    }

    /**
     * 获取文档列表
     */
    public function getFileList()
    {
        $field = ['search', 'page', 'page_rows'];
        $param = $this->_apiParam($field);
        $where = [];
        $highlights = [];
        $is_admin = User::isSuperAdmin();
        $total = 0;
        if (!empty($param['search'])) {
            $result = ZincSearchService::searchDocument(
                $param['search'],
                $is_admin ? 0 : is_login(),
                $param['page'] ?? 1,
                $param['page_rows'] ?? 10);
            $hits = $result['hits']['hits'] ?? [];
            $total = $result['hits']['total']['value'] ?? 0;
            $highlights = collect($hits)->column('highlight', '_id');
            $ids = array_column($hits, '_id');
            $where[] = ['a.id', 'in', $ids];
        }
        //判断用户权限
        if (!$is_admin) {
            $where[] = ['a.creator_uid', '=', is_login()];
        }
        $res = (new PageHelper(new File()))
            ->alias('a')
            ->join('user b', 'a.creator_uid=b.id')
            ->where($where)
            ->field('a.*,b.title as creator_name')
            ->order('a.id', 'desc')
            ->autoPage(!$total)
            ->get();

        if (!empty($total)) {
            $res['total'] = $total;
        }
        $list = [];
        foreach ($highlights as $id => $item) {
            $value = collect($res['list'])->where('id', $id)->first();
            $highlight_text = [];
            foreach ($item as $part) {
                $highlight_text = array_merge($highlight_text, $part);
            }
            $value['highlight_text'] = join('...', $highlight_text);
            $list[] = $value;
        }

        !empty($list) && $res['list'] = $list;

        return $this->successResponse('获取成功', $res);
    }

    /**
     * 删除文档
     * @author 我只想看看蓝天 <1207032539@qq.com>
     */
    public function delFile()
    {
        $field = ['ids'];
        $param = $this->_apiParam($field);
        checkData($param, [
            'ids|删除文件' => 'require|array',
        ]);

        $where = [];
        //判断用户权限
        if (!User::isSuperAdmin()) {
            $where[] = ['creator_uid', '=', is_login()];
        }
        $where[] = ['id', 'in', $param['ids']];
        $files = File::where($where)->select();
        foreach ($files as $file) {
            Db::startTrans();
            //删除索引文档
            ZincSearchService::delDocument($file['id']);
            //删除文件
            @unlink(app()->getRootPath() . 'public' . $file['path']);
            if (!empty($file['pdf'])) {
                @unlink(app()->getRootPath() . 'public' . $file['pdf']);
            }
            //删除数据库
            $file->delete();
            Db::commit();
        }

        return $this->successResponse('删除成功');
    }

    /**
     * 下载文件
     * @author 我只想看看蓝天 <1207032539@qq.com>
     */
    public function downloadFile()
    {
        $field = ['ids'];
        $param = $this->_apiParam($field);
        checkData($param, [
            'ids|文件标识' => 'require',
        ]);

        $where = [];
        //判断用户权限
        if (!User::isSuperAdmin()) {
            $where[] = ['creator_uid', '=', is_login()];
        }
        $ids = explode(',', $param['ids']);
        $where[] = ['id', 'in', $ids];
        $files = File::where($where)->select();

        if (empty($files)) {
            throw new CommonException('文件不存在或下载权限');
        }

        if (count($files) > 1) { //多个文件则打包下载
            $options = new Archive();
            $options->setSendHttpHeaders(true);
            $zip = new ZipStream('文件打包-' . date('Y-m-d-H-i-s') . '.zip', $options);
            $filenames = collect($files)->column('name');
            $exist_filenames = [];
            foreach ($files as $file) {
                $index = 0;
                again:
                $index++;
                $filename = $file['name'];
                if (in_array($filename, $exist_filenames)) {
                    $pieces = explode('.', $filename);
                    if (count($pieces) == 1) {
                        $filename .= "({$index})";
                    } else {
                        $pieces[count($pieces) - 2] .= "({$index})";
                        $filename = join('.', $pieces);
                    }
                    if (in_array($filename, $filenames)) {
                        goto again;
                    }
                }
                $exist_filenames[] = $filename;
                $full_path = app()->getRootPath() . 'public' . $file['path'];
                $zip->addFileFromPath($filename, $full_path);
            }
            $zip->finish();
            exit();
        } else { //一个文件直接下载
            $full_path = app()->getRootPath() . 'public' . $files[0]['path'];
            return download($full_path, $files[0]['name']);
        }
    }
}
