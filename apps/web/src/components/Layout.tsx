import { useState, useEffect } from 'react';
import { Outlet, useNavigate, useLocation } from 'react-router-dom';
import { Layout as AntLayout, Menu } from 'antd';
import type { MenuProps } from 'antd';
import {
  DashboardOutlined,
  ApiOutlined,
  ShopOutlined,
  SyncOutlined,
  FileTextOutlined,
  SearchOutlined,
  AppstoreOutlined,
  UnorderedListOutlined,
  CloudSyncOutlined,
} from '@ant-design/icons';
import { shopApi } from '@/services/api';

const { Sider, Content } = AntLayout;

type MenuItem = Required<MenuProps>['items'][number];

export default function Layout() {
  const [collapsed, setCollapsed] = useState(false);
  const [shops, setShops] = useState<any[]>([]);
  const navigate = useNavigate();
  const location = useLocation();

  useEffect(() => {
    loadShops();
  }, []);

  const loadShops = async () => {
    try {
      const res: any = await shopApi.list({ pageSize: 100 });
      setShops(res.data || []);
    } catch (e) {
      console.error(e);
    }
  };

  // 动态生成菜单
  const menuItems: MenuItem[] = [
    { key: '/dashboard', icon: <DashboardOutlined />, label: '仪表盘' },
    { key: '/channels', icon: <ApiOutlined />, label: '渠道管理' },
    {
      key: '/products',
      icon: <AppstoreOutlined />,
      label: '商品中心',
      children: [
        { key: '/products/query', icon: <SearchOutlined />, label: '商品查询' },
        { key: '/products/list', icon: <UnorderedListOutlined />, label: '商品管理' },
      ],
    },
    {
      key: '/shops',
      icon: <ShopOutlined />,
      label: '平台店铺',
      children: [
        { key: '/shops/list', label: '店铺列表' },
        { key: '/shops/sync-tasks', label: '同步记录' },
        { key: '/shops/feed-status', icon: <CloudSyncOutlined />, label: 'Feed状态' },
        ...shops.map((shop) => ({
          key: `/shops/${shop.id}/products`,
          label: shop.name,
        })),
      ],
    },
    { key: '/sync-rules', icon: <SyncOutlined />, label: '同步规则' },
    { key: '/sync-logs', icon: <FileTextOutlined />, label: '同步日志' },
  ];

  // 获取当前选中的菜单key
  const getSelectedKeys = () => {
    const path = location.pathname;
    // 店铺商品页面
    if (path.includes('/shops/') && path.includes('/products')) {
      return [path];
    }
    // 同步记录页面
    if (path === '/shops/sync-tasks') {
      return ['/shops/sync-tasks'];
    }
    // Feed状态页面
    if (path === '/shops/feed-status') {
      return ['/shops/feed-status'];
    }
    // 店铺列表页面
    if (path === '/shops/list') {
      return ['/shops/list'];
    }
    return [path];
  };

  // 获取展开的菜单key
  const getOpenKeys = () => {
    const keys: string[] = [];
    if (location.pathname.startsWith('/shops')) {
      keys.push('/shops');
    }
    if (location.pathname.startsWith('/products')) {
      keys.push('/products');
    }
    return keys;
  };

  return (
    <AntLayout style={{ minHeight: '100vh' }}>
      <Sider collapsible collapsed={collapsed} onCollapse={setCollapsed} width={200}>
        <div style={{ height: 32, margin: 16, background: 'rgba(255,255,255,0.2)', borderRadius: 6, display: 'flex', alignItems: 'center', justifyContent: 'center', color: '#fff', fontWeight: 'bold' }}>
          {collapsed ? 'ES' : '电商同步系统'}
        </div>
        <Menu
          theme="dark"
          mode="inline"
          selectedKeys={getSelectedKeys()}
          defaultOpenKeys={getOpenKeys()}
          items={menuItems}
          onClick={({ key }) => navigate(key)}
        />
      </Sider>
      <AntLayout>
        <Content style={{ margin: 16, padding: 24, background: '#fff', borderRadius: 8 }}>
          <Outlet context={{ refreshShops: loadShops }} />
        </Content>
      </AntLayout>
    </AntLayout>
  );
}
