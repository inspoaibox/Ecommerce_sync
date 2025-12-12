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
import ListingQuery from './pages/listing/ListingQuery';
import ListingProducts from './pages/listing/ListingProducts';
import CategoryBrowser from './pages/listing/CategoryBrowser';
import UpcManagement from './pages/listing/UpcManagement';
import ProductPool from './pages/listing/ProductPool';
import ListingLogs from './pages/listing/ListingLogs';
import ListingFeedStatus from './pages/listing/ListingFeedStatus';
import StandardFields from './pages/help/StandardFields';
// AI 优化模块
import AiModels from './pages/listing/ai/AiModels';
import PromptTemplates from './pages/listing/ai/PromptTemplates';
import AiOptimize from './pages/listing/ai/AiOptimize';
import OptimizationLogs from './pages/listing/ai/OptimizationLogs';
// 图片处理模块
import ImageConfig from './pages/listing/image/ImageConfig';
import ImageBatchProcess from './pages/listing/image/ImageBatchProcess';
import ImageProcessLogs from './pages/listing/image/ImageProcessLogs';

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
          {/* 商品刊登 */}
          <Route path="listing/query" element={<ListingQuery />} />
          <Route path="listing/products" element={<ListingProducts />} />
          <Route path="listing/categories" element={<CategoryBrowser />} />
          <Route path="listing/upc" element={<UpcManagement />} />
          <Route path="listing/product-pool" element={<ProductPool />} />
          <Route path="listing/logs" element={<ListingLogs />} />
          <Route path="listing/feed-status" element={<ListingFeedStatus />} />
          {/* AI 大模型 */}
          <Route path="listing/ai/models" element={<AiModels />} />
          <Route path="listing/ai/templates" element={<PromptTemplates />} />
          <Route path="listing/ai/optimize" element={<AiOptimize />} />
          <Route path="listing/ai/logs" element={<OptimizationLogs />} />
          {/* 图片处理 */}
          <Route path="listing/image/config" element={<ImageConfig />} />
          <Route path="listing/image/batch" element={<ImageBatchProcess />} />
          <Route path="listing/image/logs" element={<ImageProcessLogs />} />
          {/* 帮助中心 */}
          <Route path="help/standard-fields" element={<StandardFields />} />
        </Route>
      </Routes>
    </BrowserRouter>
  );
}

export default App;
