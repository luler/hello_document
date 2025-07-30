<?php

use think\migration\Migrator;
use think\migration\db\Column;

class File extends Migrator
{
    /**
     * Change Method.
     *
     * Write your reversible migrations using this method.
     *
     * More information on writing migrations is available here:
     * http://docs.phinx.org/en/latest/migrations.html#the-abstractmigration-class
     *
     * The following commands can be used in this method and Phinx will
     * automatically reverse them when rolling back:
     *
     *    createTable
     *    renameTable
     *    addColumn
     *    renameColumn
     *    addIndex
     *    addForeignKey
     *
     * Remember to call "create()" or "update()" and NOT "save()" when working
     * with the Table class.
     */
    public function change()
    {
        $table = $this->table('file', array('engine' => 'InnoDB', 'comment' => '文件表'));
        $table->addColumn('name', 'string', ['limit' => 255, 'default' => '', 'comment' => '文件名', 'null' => false])
            ->addColumn('path', 'string', ['limit' => 255, 'default' => '', 'comment' => '文件路径', 'null' => false])
            ->addColumn('pdf', 'string', ['limit' => 255, 'default' => '', 'comment' => '转换成pdf的路径', 'null' => false])
            ->addColumn('pdf_status', 'integer', ['limit' => 4, 'default' => 0, 'comment' => '转换结果，0-待转换，1-正在转换，2-转换成功，3-转换失败', 'null' => false])
            ->addColumn('zincsearch_status', 'integer', ['limit' => 4, 'default' => 0, 'comment' => '推送全文索引引擎状态，0-待推送，1-已推送，1-推送失败', 'null' => false])
            ->addColumn('size', 'integer', ['default' => 0, 'comment' => '文件大小，单位字节', 'null' => false])
            ->addColumn('md5', 'string', ['limit' => 32, 'default' => '', 'comment' => '文件md5', 'null' => false])
            ->addColumn('creator_uid', 'integer', ['default' => 0, 'comment' => '创建人uid', 'null' => false])
            ->addColumn('create_time', 'integer', ['default' => 0, 'comment' => '创建时间', 'null' => false])
            ->addColumn('update_time', 'integer', ['default' => 0, 'comment' => '更新时间', 'null' => false])
            ->addIndex(['creator_uid'])
            ->create();
    }
}
