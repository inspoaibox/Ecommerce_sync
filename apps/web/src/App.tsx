import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import Layout from './components/Layout';
import Dashboard from './pages/Dashboard';
import ChannelList from './pages/channel/ChannelList';
import ShopList from './pages/shop/ShopList';
import ShopProducts from './pages/shop/ShopProducts';
import SyncRuleList from './pages/sync-rule/SyncRuleList';
import SyncLogList from './pages/sync-log/SyncLogList';
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
          <Route path="products/query" element={<ProductQuery />} />
          <Route path="products/list" element={<ProductList />} />
          <Route path="shops/list" element={<ShopList />} />
          <Route path="shops/:shopId/products" element={<ShopProducts />} />
          <Route path="sync-rules" element={<SyncRuleList />} />
          <Route path="sync-logs" element={<SyncLogList />} />
        </Route>
      </Routes>
    </BrowserRouter>
  );
}

export default App;
