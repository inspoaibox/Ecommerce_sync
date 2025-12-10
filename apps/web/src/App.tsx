import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import Layout from './components/Layout';
import Dashboard from './pages/Dashboard';
import ChannelList from './pages/channel/ChannelList';
import ChannelApiTest from './pages/channel/ChannelApiTest';
import ShopList from './pages/shop/ShopList';
import ShopProducts from './pages/shop/ShopProducts';
import ShopSyncTasks from './pages/shop/ShopSyncTasks';
import FeedStatus from './pages/shop/FeedStatus';
import AutoSync from './pages/shop/AutoSync';
import OperationLog from './pages/shop/OperationLog';
import ProductQuery from './pages/product/ProductQuery';
import ProductList from './pages/product/ProductList';

function App() {
  return (
    <BrowserRouter>
      <Routes>
        <Route path="/" element={<Layout />}>
          <Route index element={<Navigate to="/dashboard" replace />} />
          <Route path="dashboard" element={<Dashboard />} />
          <Route path="channels" element={<ChannelList />} />
          <Route path="channels/:id/test" element={<ChannelApiTest />} />
          <Route path="products/query" element={<ProductQuery />} />
          <Route path="products/list" element={<ProductList />} />
          <Route path="shops/list" element={<ShopList />} />
          <Route path="shops/sync-tasks" element={<ShopSyncTasks />} />
          <Route path="shops/feed-status" element={<FeedStatus />} />
          <Route path="shops/operation-log" element={<OperationLog />} />
          <Route path="shops/auto-sync" element={<AutoSync />} />
          <Route path="shops/:shopId/products" element={<ShopProducts />} />
        </Route>
      </Routes>
    </BrowserRouter>
  );
}

export default App;
