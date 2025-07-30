import React from "react";
import {
  Button,
  Input,
  Modal,
  Table,
  message,
  Divider,
  Popconfirm,
  Tag,
  Badge,
  Upload,
  notification,
  Tooltip
} from "antd";
import {request_post} from "@/utils/request_tool";
import BaseComponent from "@/pages/BaseComponent";
import {InfoCircleOutlined, UploadOutlined} from "@ant-design/icons";
import {getAccessToken} from "@/utils/authority";
import * as queryString from "querystring";
import {cloneDeep} from "lodash";

export default class index extends BaseComponent {
  state = {
    param: {
      page: 1,
      page_rows: 10,
    },
    list: [],
    total: 0,
    temp_data: {},
    loading: false,
    interval: 0,
  }

  //转换字节为可读性更好的格式
  bytesToSize = (bytes) => {
    if (bytes === 0) return '0B'
    let k = 1024
    let sizes = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB']
    let i = Math.floor(Math.log(bytes) / Math.log(k))
    return (bytes / Math.pow(k, i)).toPrecision(3) + sizes[i]
  }

  columns = [
    {
      title: '名称',
      render: record => {
        return <a
          title='点击可预览'
          onClick={() => {
            if (record.pdf_status !== 2) {
              message.warning('未转换成功,无法预览')
              return
            }
            Modal.info({
              title: record.name,
              width: '100%',
              maskClosable: true,
              icon: false,
              okText: '关闭',
              content: <iframe
                width="100%"
                style={{
                  height: 600,
                }}
                src={'/backend/pdf.js/web/viewer.html?file=' + record.pdf}
              />
            })

          }}
        >
          {record.name}
        </a>
      }
    },
    {
      title: '搜索摘要',
      dataIndex: 'highlight_text',
      width: 500,
      render: (value) => {
        return <div dangerouslySetInnerHTML={{__html: value}}></div>
      },
    },
    {
      title: '大小',
      dataIndex: 'size',
      render: (value) => {
        return this.bytesToSize(value)
      },
    },
    {
      title: '转换状态',
      dataIndex: 'pdf_status',
      render: (value) => {
        return [
          <Tag>待转换</Tag>,
          <Tag color='#108ee9'>正在转换</Tag>,
          <Tag color='#87d068'>转换成功</Tag>,
          <Tag color='#f50'>转换失败</Tag>,
        ][value]
      }
    },
    {
      title: 'zincsearch',
      dataIndex: 'zincsearch_status',
      render: (value) => {
        return [
          <Badge status='default' text='未推送'/>,
          <Badge status='success' text='已推送'/>,
          <Badge status='error' text='推送失败'/>,
        ][value]
      }
    },
    {
      title: '创建者',
      dataIndex: 'creator_name',
    },
    {
      title: '创建时间',
      width: 160,
      dataIndex: 'create_time',
    },
    {
      title: '操作',
      render: (record) => {
        return <div>
          <a
            href={'/api/downloadFile?' + queryString.stringify({ids: record.id, Authorization: getAccessToken(),})}
          >
            下载
          </a>
          <Divider type='vertical'/>
          <Popconfirm
            title='您确定要删除吗？'
            onConfirm={() => {

              request_post('/api/delFile', {ids: [record.id]}).then(res => {
                if (res.code === 200) {
                  message.success('删除成功')
                  this.fetch()
                }
              })
            }}
          >
            <a
              style={{color: 'red'}}
            >
              删除
            </a>
          </Popconfirm>
        </div>
      }
    },
  ]

  componentDidMount() {
    this.fetch()
  }

  fetch(loading) {
    this.setStateSimple('selectedRowKeys', [])
    if (this.state.param.search) {
      this.setStateSimple('columns', this.columns)
    } else {
      let columns = cloneDeep(this.columns)
      columns.splice(1, 1)
      this.setStateSimple('columns', columns)
    }
    this.setState({loading: loading ?? true}, () => {
      request_post('/api/getFileList', this.state.param).then((res) => {
        this.setStateSimple('list', res.info.list)
        this.setStateSimple('total', res.info.total)
        this.setStateSimple('loading', false)
        if (res.info.list.findIndex(function (one) {
          return one.pdf_status === 0
            || one.pdf_status === 1
            || one.zincsearch_status === 0
        }) === -1) {
          if (this.state.interval > 0) {
            clearInterval(this.state.interval);
            this.setStateSimple('interval', 0);
          }
        } else {
          if (this.state.interval === 0) {
            const that = this
            this.setStateSimple('interval', setInterval(function () {
              that.fetch(false)
            }, 3000))
          }
        }
      })
    })
  }

  render() {
    return <div>
      <div
        style={{
          // background: 'white',
          // padding: 20,
          // margin: "20px 0",
          textAlign: 'center',
        }}
      >
        <Input.Search
          style={{
            width: "50%",
            // float: 'right',
          }}
          prefix={<Tooltip
            title="默认使用短语匹配模式，相当于elasticsearch的match_phrase搜索类型，当关键字包含*或?号时使用通配符模式匹配，相当于elasticsearch的wildcard搜索类型；当需要多个关键字同时匹配到时，可以通过&符号将关键字连接起来实现。">
            <InfoCircleOutlined style={{color: 'rgba(0,0,0,.45)'}}/>
          </Tooltip>}
          size='large'
          allowClear
          placeholder='请输入搜索关键字'
          onSearch={value => {
            this.setState({
              param: {
                ...this.state.param,
                search: value,
                page: 1,
              }
            }, () => {
              this.fetch()
            })
          }}
        />
      </div>
      <div
        style={{
          background: 'white',
          padding: 20,
          marginTop: 20,
          // margin: "20px 0"
        }}
      >
        <Button
          disabled={!this.state.selectedRowKeys || this.state.selectedRowKeys.length === 0}
          type='danger'
          onClick={() => {
            Modal.confirm({
              title: '提示',
              content: '您确定要删除选中项目吗？',
              onOk: () => {

                request_post('/api/delFile', {ids: this.state.selectedRowKeys}).then(res => {
                  if (res.code === 200) {
                    message.success('删除成功')
                    this.fetch()
                  }
                })

              }
            })
          }}
        >
          删除选中
        </Button>
        &nbsp;
        &nbsp;
        <Button
          disabled={!this.state.selectedRowKeys || this.state.selectedRowKeys.length === 0}
          type='dashed'
          onClick={() => {

            window.open('/api/downloadFile?' + queryString.stringify({
              ids: this.state.selectedRowKeys.join(','),
              Authorization: getAccessToken(),
            }))

          }}
        >
          下载选中
        </Button>
        &nbsp;
        &nbsp;
        <Upload
          name='files[]'
          action='/api/uploadFile'
          headers={{
            authorization: getAccessToken(),
          }}
          showUploadList={false}
          multiple={true}
          onChange={(info) => {
            // console.log(info.file.status)
            if (info.file.status === 'uploading') {
              this.setStateSimple('upload_status', true)
            } else {
              this.setStateSimple('upload_status', false)
            }
            if (info.file.status === 'done') {
              this.fetch()
            }
            if (info.file.status === 'error') {
              notification.error({
                description: info.file.name + ' 上传失败',
                message: info.file.response.message || '未知错误，请联系开发人员处理',
                duration: 5,
              });
            }
          }}
        >
          <Button loading={this.state.upload_status || false} type='primary' icon={<UploadOutlined/>}>上传</Button>
        </Upload>

      </div>
      <Table
        onChange={(pagination) => {
          this.setState({
            param: {
              ...this.state.param,
              page: pagination.current,
              page_rows: pagination.pageSize,
            }
          }, () => {
            this.fetch()
          })
        }}
        pagination={{
          showSizeChanger: true,
          current: this.state.param.page,
          total: this.state.total,
          pageSize: this.state.param.page_rows,
          showTotal: (total) => {
            return <div>总共 {total} 条数据</div>
          }
        }}
        rowSelection={{
          selectedRowKeys: this.state.selectedRowKeys || [],
          onChange: selectedRowKeys => {
            this.setStateSimple('selectedRowKeys', selectedRowKeys)
          }
        }}
        loading={this.state.loading}
        rowKey='id'
        dataSource={this.state.list}
        columns={this.state.columns || []}
      />
    </div>
  }

}
