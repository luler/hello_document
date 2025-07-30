<?php
declare (strict_types=1);

namespace app\common\command;

use app\common\logic\FileLogic;
use app\common\service\ZincSearchService;
use think\console\Command;
use think\console\Input;
use think\console\Output;

class Debug extends Command
{
    protected function configure()
    {
        // 指令配置
        $this->setName('debug')->setDescription('调试专用命令');
    }

    protected function execute(Input $input, Output $output)
    {
//        dump(ZincSearchService::getConfig());
//        FileLogic::startWork();
//        dump(ZincSearchService::searchDocument('ITEM_WX'));
//        file_put_contents('1.json',json_encode(ZincSearchService::searchDocument('ZMATKL_03'),256));
//        ZincSearchService::createIndex();
    }
}
