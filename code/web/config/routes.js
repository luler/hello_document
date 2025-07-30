export default [
  {
    path: '/user',
    layout: false,
    routes: [
      {
        path: '/user',
        routes: [
          {
            name: 'login',
            path: '/user/login',
            component: './user/Login',
          },
          {
            component: './404',
          },
        ],
      },
    ],
  },
  {
    path: '/',
    redirect: '/document/index',
  },
  {
    name: '文档列表',
    path: '/document/index',
    icon: 'DatabaseOutlined',
    component: './document/index',
  },
  {
    component: './404',
  },
];
