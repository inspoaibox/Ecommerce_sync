import { useState, useEffect } from 'react';
import { Outlet, useNavigate, useLocation } from 'react-router-dom';
import { Layout as AntLayout, Menu } from 'antd';
import type { MenuProps } from 'antd';
import {
  DashboardOutlined,
  ApiOutlined,
  ShopOutlined,
  SearchOutlined,
  AppstoreOutlined,
  UnorderedListOutlined,
  CloudSyncOutlined,
  SyncOutlined,
  HistoryOutlined,
  UploadOutlined,
  BarcodeOutlined,
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
      key: '/listing',
      icon: <UploadOutlined />,
      label: '商品刊登',
      children: [
        { key: '/listing/query', icon: <SearchOutlined />, label: '商品查询' },
        { key: '/listing/products', icon: <UnorderedListOutlined />, label: '刊登管理' },
        { key: '/listing/categories', icon: <AppstoreOutlined />, label: '平台类目' },
        { key: '/listing/upc', icon: <BarcodeOutlined />, label: 'UPC 管理' },
      ],
    },
    {
      key: '/shops',
      icon: <ShopOutlined />,
      label: '平台店铺',
      children: [
        { key: '/shops/list', label: '店铺列表' },
        ...shops.map((shop) => ({
          key: `/shops/${shop.id}/products`,
          label: shop.name,
        })),
        { key: '/shops/auto-sync', icon: <SyncOutlined />, label: '自动同步' },
        { key: '/shops/feed-status', icon: <CloudSyncOutlined />, label: 'Feed状态' },
        { key: '/shops/operation-log', icon: <HistoryOutlined />, label: '操作日志' },
      ],
    },
  ];

  // 获取当前选中的菜单key
  const getSelectedKeys = () => {
    const path = location.pathname;
    // 店铺商品页面
    if (path.includes('/shops/') && path.includes('/products')) {
      return [path];
    }
    // Feed状态页面
    if (path === '/shops/feed-status') {
      return ['/shops/feed-status'];
    }
    // 自动同步页面
    if (path === '/shops/auto-sync') {
      return ['/shops/auto-sync'];
    }
    // 操作日志页面
    if (path === '/shops/operation-log') {
      return ['/shops/operation-log'];
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
    if (location.pathname.startsWith('/listing')) {
      keys.push('/listing');
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
