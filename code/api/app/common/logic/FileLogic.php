<?php

namespace app\common\logic;

use app\common\exception\CommonException;
use app\common\service\ZincSearchService;
use app\model\File;
use think\facade\Log;

class FileLogic
{
    /**
     * 开始处理文件后续逻辑
     * @author 我只想看看蓝天 <1207032539@qq.com>
     */
    public static function startWork()
    {
        try {
            $to = 'pdf';
            $res = File::where('pdf_status', 0)->select();
            $clean_dirs = [];
            $parser = new \Smalot\PdfParser\Parser();
            foreach ($res as $value) {
                File::where('id', $value['id'])->update(['update_time' => time(), 'pdf_status' => 1]);
                $file_path = app()->getRootPath() . 'public' . $value['path'];
                $temp_file_dir = dirname($file_path);
                $temp_file_dir = str_replace('/doc/', '/pdf/', $temp_file_dir);
                if (!file_exists($temp_file_dir)) {
                    mkdir($temp_file_dir, 0777, true);
                }
                $basename = basename($file_path);
                $pdf_file = preg_replace('/\.[^\.]*/', '', $basename) . '.pdf';
                $pdf_file = $temp_file_dir . '/' . $pdf_file;
                //判断是否pdf
                if (preg_match('/\.pdf$/i', $file_path)) {
                    copy($file_path, $pdf_file);
                } else {
                    putenv('LANG=en_US.UTF-8');//解决执行下面命令格式转化时，一般文本中文乱码，有点坑，原因未明
                    //设置转换在60秒后结束，避免无限执行下去
                    $process = proc_open("timeout 60 libreoffice --headless --convert-to {$to} {$file_path} --outdir {$temp_file_dir}",
                        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                        $pipes);
                    if (!is_resource($process)) {
                        throw new CommonException('无法获取系统命令');
                    }
                    fwrite($pipes[0], '');
                    fclose($pipes[0]);
                    $stdout = stream_get_contents($pipes[1]);
                    fclose($pipes[1]);
                    $stderr = stream_get_contents($pipes[2]);
                    fclose($pipes[2]);
                    $rtn = proc_close($process);
                    $clean_dirs[] = $temp_file_dir . '/*';
                }
                //判断是否转换成功
                if (file_exists($pdf_file)) { //成功
                    $pdf = substr($pdf_file, strpos($pdf_file, '/storage/'));
                    File::where('id', $value['id'])->update(['update_time' => time(), 'pdf_status' => 2, 'pdf' => $pdf]);

                    //解析出pdf中的文本
                    $pdf = $parser->parseFile($pdf_file);
                    try {
                        ZincSearchService::createDocumentsBulkV2([
                            [
                                '_id' => (string)$value['id'],
                                'uid' => (int)$value['creator_uid'],
                                'name' => $value['name'],
                                'text' => $pdf->getText(),
                            ],
                        ]);
                        File::where('id', $value['id'])->update(['update_time' => time(), 'zincsearch_status' => 1]);
                    } catch (\Exception $e) { //推送失败
                        Log::error($e->getMessage());
                        File::where('id', $value['id'])->update(['update_time' => time(), 'zincsearch_status' => 2]);
                    }

                } else { //失败
                    File::where('id', $value['id'])->update(['update_time' => time(), 'pdf_status' => 3]);
                }
            }

            //清理垃圾
            $clean_dirs = array_unique($clean_dirs);
            foreach ($clean_dirs as $clean_dir) {
                self::checkAndDelBadPdfFile($clean_dir);
            }

        } catch (\Exception $e) {
            Log::error($e->getMessage());
        }
    }

    /**
     * 删除因转换超时或失败而生成的中间文件
     * @param $path
     * @return void
     * @author 我只想看看蓝天 <1207032539@qq.com>
     */
    public static function checkAndDelBadPdfFile($path)
    {
        $files = glob($path);
        foreach ($files as $file) {
            if (!preg_match('/storage\/pdf\/.*\.pdf$/', $file)) {
                unlink($file);
            }
        }
    }

    /**
     * 转换文件为utf-8格式(解决中文乱码问题)
     * @param $file_path
     * @author 我只想看看蓝天 <1207032539@qq.com>
     */
    public static function transformTextFileToUtf8($file_path)
    {
        $content = file_get_contents($file_path);
        $encode = mb_detect_encoding($content, ['EUC-CN', 'ASCII', 'GB2312', 'GBK', 'UTF-8']);
        if ($encode != 'UTF-8') {
            $content = mb_convert_encoding($content, 'UTF-8', $encode);
            file_put_contents($file_path, $content);
        }
    }
}