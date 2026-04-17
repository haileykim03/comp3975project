import 'bootstrap/dist/css/bootstrap.min.css';
import './App.css';
import { BrowserRouter, Routes, Route } from 'react-router-dom';
import LandingPage from './pages/LandingPage';
import SignInPage from './pages/SignInPage';
import SignUpPage from './pages/SignUpPage';
import MainPage from './pages/MainPage';
import ItemsListPage from './pages/ItemsListPage';
import AddItemPage from './pages/AddItemPage';
import MyClaimsPage from './pages/MyClaimsPage';
import MyAddedItemsPage from './pages/MyAddedItemsPage';
import NotFoundPage from './pages/NotFoundPage';

function App() {
  return (
    <BrowserRouter>
      <Routes>
        <Route path="/" element={<LandingPage />} />
        <Route path="/signin" element={<SignInPage />} />
        <Route path="/signup" element={<SignUpPage />} />
        <Route path="/main" element={<MainPage />} />
        <Route path="/items" element={<ItemsListPage />} />
        <Route path="/add-item" element={<AddItemPage />} />
        <Route path="/my-claims" element={<MyClaimsPage />} />
        <Route path="/my-added-items" element={<MyAddedItemsPage />} />
        <Route path="*" element={<NotFoundPage />} />
      </Routes>
    </BrowserRouter>
  );
}

export default App;