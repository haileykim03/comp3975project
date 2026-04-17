import { useEffect, useMemo, useState } from 'react';
import 'bootstrap/dist/css/bootstrap.min.css';
import '../App.css';
import Header from '../components/Header';
import Footer from '../components/Footer';
import { useNavigate } from 'react-router-dom';

type User = {
  id: number;
  first_name?: string;
  last_name?: string;
  email?: string;
};

type Item = {
  id: number;
  name: string;
  description: string | null;
  category: string;
  color: string | null;
  brand: string | null;
  location: string;
  finder_id: number | null;
  owner_id: number | null;
  status: 'pending' | 'active' | 'claim pending' | 'returned';
  found_at: string | null;
  image_url?: string | null;
};

const locationOptions = [
  'SW1', 'SW2', 'SW3', 'SW5', 'SW7', 'SW9', 'SW10', 'SW12', 'SW13', 'SW14', 'SW16',
  'SE1', 'SE2', 'SE3', 'SE4', 'SE6', 'SE8', 'SE9', 'SE10', 'SE12', 'SE14', 'SE16', 'SE30',
  'NE1', 'NE3', 'NE4', 'NE6', 'NE8', 'NE9', 'NE10', 'NE12',
  'NW1', 'NW3', 'NW4', 'NW5', 'NW6',
];

const categoryOptions = [
  'Wallet',
  'Backpack',
  'Keys',
  'Phone',
  'Earbuds',
  'Laptop',
  'ID',
  'Bottle',
  'Headphones',
  'Others',
];

const categoryIcons: Record<string, string> = {
  Wallet: '👛',
  Backpack: '🎒',
  Keys: '🔑',
  Phone: '📱',
  Earbuds: '🎧',
  Laptop: '💻',
  ID: '🪪',
  Bottle: '🧴',
  Headphones: '🎧',
  Others: '📦',
  Default: '📦',
};

function getTimeOfDay(dateString: string): string {
  const date = new Date(dateString);
  const hour = date.getHours();

  if (hour < 12) {
    return 'Morning';
  }

  if (hour < 17) {
    return 'Afternoon';
  }

  if (hour < 21) {
    return 'Evening';
  }

  return 'Night';
}

function getOrdinal(day: number): string {
  if (day > 3 && day < 21) {
    return 'th';
  }

  switch (day % 10) {
    case 1:
      return 'st';
    case 2:
      return 'nd';
    case 3:
      return 'rd';
    default:
      return 'th';
  }
}

function formatFoundAt(dateString: string | null): string {
  if (!dateString) {
    return 'Time not available';
  }

  const date = new Date(dateString);
  const month = date.toLocaleString('en-US', { month: 'long' });
  const day = date.getDate();
  const timeOfDay = getTimeOfDay(dateString);

  return `${month} ${day}${getOrdinal(day)} · ${timeOfDay}`;
}

function formatForDateTimeInput(dateString: string | null): string {
  if (!dateString) {
    return '';
  }

  const date = new Date(dateString);
  const offset = date.getTimezoneOffset();
  const local = new Date(date.getTime() - offset * 60000);

  return local.toISOString().slice(0, 16);
}

function MyAddedItemsPage() {
  const navigate = useNavigate();

  const token = localStorage.getItem('token');
  const userString = localStorage.getItem('user');
  const currentUser: User | null = userString ? JSON.parse(userString) : null;

  const [items, setItems] = useState<Item[]>([]);
  const [loading, setLoading] = useState(true);
  const [pageError, setPageError] = useState('');
  const [selectedItem, setSelectedItem] = useState<Item | null>(null);

  const [name, setName] = useState('');
  const [description, setDescription] = useState('');
  const [category, setCategory] = useState(categoryOptions[0]);
  const [color, setColor] = useState('');
  const [brand, setBrand] = useState('');
  const [location, setLocation] = useState(locationOptions[0]);
  const [foundAt, setFoundAt] = useState('');
  const [imageFile, setImageFile] = useState<File | null>(null);
  const [imagePreview, setImagePreview] = useState('');
  const [imageError, setImageError] = useState('');

  const [modalError, setModalError] = useState('');
  const [modalSuccess, setModalSuccess] = useState('');
  const [isUpdating, setIsUpdating] = useState(false);
  const [isDeleting, setIsDeleting] = useState(false);

  useEffect(() => {
    if (!token || !userString) {
      navigate('/signin');
      return;
    }

    fetchMyItems();
  }, [navigate, token, userString]);

  const fetchMyItems = async () => {
    try {
      setLoading(true);
      setPageError('');

      const response = await fetch(`${import.meta.env.VITE_API_URL}/api/items`, {
        headers: {
          Accept: 'application/json',
        },
      });

      const data = await response.json();

      if (!response.ok) {
        setPageError(data.message || 'Failed to load your items.');
        return;
      }

      setItems(data);
    } catch (error) {
      console.error(error);
      setPageError('Something went wrong while loading your items.');
    } finally {
      setLoading(false);
    }
  };

  const myPendingItems = useMemo(() => {
    if (!currentUser) {
      return [];
    }

    return items.filter(
      (item) => item.status === 'pending' && item.finder_id === currentUser.id
    );
  }, [items, currentUser]);

  const handleOpenModal = (item: Item) => {
    setSelectedItem(item);
    setName(item.name);
    setDescription(item.description || '');
    setCategory(item.category);
    setColor(item.color || '');
    setBrand(item.brand || '');
    setLocation(item.location);
    setFoundAt(formatForDateTimeInput(item.found_at));
    setModalError('');
    setModalSuccess('');
    setImageFile(null);
    if (imagePreview.startsWith('blob:')) {
      URL.revokeObjectURL(imagePreview);
    }
    setImagePreview(item.image_url || '');
  };

  const handleCloseModal = () => {
    setSelectedItem(null);
    setModalError('');
    setModalSuccess('');
    setImageFile(null);
    if (imagePreview.startsWith('blob:')) {
      URL.revokeObjectURL(imagePreview);
    }
    setImagePreview('');
  };

  const handleImageChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0] ?? null;
    setImageError('');

    if (!file) {
      setImageFile(null);
      if (imagePreview.startsWith('blob:')) {
        URL.revokeObjectURL(imagePreview);
      }
      setImagePreview(selectedItem?.image_url || '');
      return;
    }

    if (!file.type.startsWith('image/')) {
      setImageError('Please select a valid image file.');
      setImageFile(null);
      if (imagePreview.startsWith('blob:')) {
        URL.revokeObjectURL(imagePreview);
      }
      setImagePreview(selectedItem?.image_url || '');
      return;
    }

    if (file.size > 10 * 1024 * 1024) {
      setImageError('Image file size must be 10MB or smaller.');
      setImageFile(null);
      if (imagePreview.startsWith('blob:')) {
        URL.revokeObjectURL(imagePreview);
      }
      setImagePreview(selectedItem?.image_url || '');
      return;
    }

    setImageFile(file);
    if (imagePreview.startsWith('blob:')) {
      URL.revokeObjectURL(imagePreview);
    }
    setImagePreview(URL.createObjectURL(file));
  };

  const handleUpdateItem = async () => {
    if (!selectedItem) {
      return;
    }

    try {
      setIsUpdating(true);
      setModalError('');
      setModalSuccess('');

      const formData = new FormData();
      formData.append('name', name);
      formData.append('description', description || '');
      formData.append('category', category);
      formData.append('color', color || '');
      formData.append('brand', brand || '');
      formData.append('location', location);
      formData.append('finder_id', String(selectedItem.finder_id ?? ''));
      if (selectedItem.owner_id !== null) {
        formData.append('owner_id', String(selectedItem.owner_id));
      }
      formData.append('status', selectedItem.status);
      formData.append('found_at', foundAt || '');
      if (imageFile) {
        formData.append('image', imageFile);
      }

      const headers: Record<string, string> = {
        Accept: 'application/json',
      };
      if (token) {
        headers.Authorization = `Bearer ${token}`;
      }

      const response = await fetch(`${import.meta.env.VITE_API_URL}/api/items/${selectedItem.id}`, {
        method: 'PUT',
        headers,
        body: formData,
      });

      const responseText = await response.text();
      let data = null;
      try {
        data = responseText ? JSON.parse(responseText) : null;
      } catch {
        data = null;
      }

      if (!response.ok) {
        console.error('Update item failed:', response.status, responseText, data);
        if (data?.errors) {
          const firstError = Object.values(data.errors)[0];
          if (Array.isArray(firstError) && firstError.length > 0) {
            setModalError(firstError[0] as string);
          } else {
            setModalError(data.message || 'Failed to update item.');
          }
        } else {
          setModalError(data?.message || responseText || 'Failed to update item.');
        }
        return;
      }

      if (!response.ok) {
        if (data.errors) {
          const firstError = Object.values(data.errors)[0];
          if (Array.isArray(firstError) && firstError.length > 0) {
            setModalError(firstError[0] as string);
          } else {
            setModalError(data.message || 'Failed to update item.');
          }
        } else {
          setModalError(data.message || 'Failed to update item.');
        }
        return;
      }

      const updatedItem = data.data ?? data;

      setItems((prevItems) =>
        prevItems.map((item) =>
          item.id === updatedItem.id ? updatedItem : item
        )
      );

      setSelectedItem(updatedItem);
      setModalSuccess('Item updated successfully.');
    } catch (error) {
      console.error(error);
      setModalError('Something went wrong while updating the item.');
    } finally {
      setIsUpdating(false);
    }
  };

  const handleDeleteItem = async () => {
    if (!selectedItem) {
      return;
    }

    try {
      setIsDeleting(true);
      setModalError('');
      setModalSuccess('');

      const response = await fetch(`${import.meta.env.VITE_API_URL}/api/items/${selectedItem.id}`, {
        method: 'DELETE',
        headers: {
          Accept: 'application/json',
          Authorization: token ? `Bearer ${token}` : '',
        },
      });

      const data = await response.json();

      if (!response.ok) {
        setModalError(data.message || 'Failed to delete item.');
        return;
      }

      setItems((prevItems) =>
        prevItems.filter((item) => item.id !== selectedItem.id)
      );

      handleCloseModal();
    } catch (error) {
      console.error(error);
      setModalError('Something went wrong while deleting the item.');
    } finally {
      setIsDeleting(false);
    }
  };

  return (
    <>
      <Header />

      <div className="items-page">
        <div className="container py-5">
          <div className="text-center mb-5">
            <h1 className="auth-title">My Added Items</h1>
            <p className="auth-subtitle">
              Review and edit your pending item submissions.
            </p>
          </div>

          {pageError && (
            <div className="alert alert-danger mb-4">
              {pageError}
            </div>
          )}

          {loading ? (
            <div className="empty-items-state text-center">
              <p className="mb-0">Loading your items...</p>
            </div>
          ) : (
            <>
              <div className="mb-3 text-muted">
                {myPendingItems.length} pending item{myPendingItems.length !== 1 ? 's' : ''}
              </div>

              <div className="row g-4">
                {myPendingItems.length > 0 ? (
                  myPendingItems.map((item) => {
                    const icon = categoryIcons[item.category] || categoryIcons.Default;

                    return (
                      <div key={item.id} className="col-md-6 col-lg-4">
                        <button
                          type="button"
                          className="item-card h-100 text-start w-100 border-0"
                          onClick={() => handleOpenModal(item)}
                        >
                          <div className="item-icon-wrap">{icon}</div>

                          <h3 className="item-card-title">{item.category}</h3>

                          <div className="item-meta">
                            <p className="mb-2">
                              <span className="item-meta-icon">📍</span> {item.location}
                            </p>
                            <p className="mb-2">
                              <span className="item-meta-icon">🕒</span> {formatFoundAt(item.found_at)}
                            </p>
                            <p className="mb-0">
                              <span className="item-meta-icon">📌</span> Pending Review
                            </p>
                          </div>
                        </button>
                      </div>
                    );
                  })
                ) : (
                  <div className="col-12">
                    <div className="empty-items-state text-center">
                      <p className="mb-0">You have no pending items right now.</p>
                    </div>
                  </div>
                )}
              </div>
            </>
          )}
        </div>
      </div>

      {selectedItem && (
        <div className="custom-modal-backdrop" onClick={handleCloseModal}>
          <div
            className="custom-modal-card"
            onClick={(e) => e.stopPropagation()}
          >
            <div className="d-flex justify-content-between align-items-start mb-3">
              <div>
                <h2 className="item-card-title mb-1">
                  {(categoryIcons[category] || categoryIcons.Default)} Edit Item
                </h2>
                <p className="auth-subtitle mb-0">Update or delete your pending submission</p>
              </div>

              <button
                type="button"
                className="btn-close"
                onClick={handleCloseModal}
              ></button>
            </div>

            {modalError && (
              <div className="alert alert-danger mb-3">
                {modalError}
              </div>
            )}

            <div className="mb-3">
              <label className="form-label text-primaryLight fw-semibold">
                Item Name
              </label>
              <input
                type="text"
                className="form-control custom-input"
                value={name}
                onChange={(e) => setName(e.target.value)}
              />
            </div>

            <div className="mb-3">
              <label className="form-label text-primaryLight fw-semibold">
                Category
              </label>
              <select
                className="form-select custom-input"
                value={category}
                onChange={(e) => setCategory(e.target.value)}
              >
                {categoryOptions.map((option) => (
                  <option key={option} value={option}>
                    {option}
                  </option>
                ))}
              </select>
            </div>

            <div className="mb-3">
              <label className="form-label text-primaryLight fw-semibold">
                Location
              </label>
              <select
                className="form-select custom-input"
                value={location}
                onChange={(e) => setLocation(e.target.value)}
              >
                {locationOptions.map((option) => (
                  <option key={option} value={option}>
                    {option}
                  </option>
                ))}
              </select>
            </div>

            <div className="mb-3">
              <label className="form-label text-primaryLight fw-semibold">
                Found Date and Time
              </label>
              <input
                type="datetime-local"
                className="form-control custom-input"
                value={foundAt}
                onChange={(e) => setFoundAt(e.target.value)}
              />
            </div>

            <div className="mb-3">
              <label className="form-label text-primaryLight fw-semibold">
                Color
              </label>
              <input
                type="text"
                className="form-control custom-input"
                value={color}
                onChange={(e) => setColor(e.target.value)}
                placeholder="Optional"
              />
            </div>

            <div className="mb-3">
              <label className="form-label text-primaryLight fw-semibold">
                Brand
              </label>
              <input
                type="text"
                className="form-control custom-input"
                value={brand}
                onChange={(e) => setBrand(e.target.value)}
                placeholder="Optional"
              />
            </div>

            <div className="mb-3">
              <label className="form-label text-primaryLight fw-semibold">
                Item Photo
              </label>
              <input
                type="file"
                accept="image/*"
                className="form-control custom-input"
                onChange={handleImageChange}
              />
              {imageError && (
                <div className="text-danger small mt-2">
                  {imageError}
                </div>
              )}
              {imagePreview && (
                <img
                  src={imagePreview}
                  alt="Item preview"
                  className="img-fluid rounded mt-3"
                  style={{ maxHeight: '220px' }}
                />
              )}
            </div>

            {modalSuccess && (
              <div className="alert alert-success mb-3">
                {modalSuccess}
              </div>
            )}

            <div className="d-flex justify-content-end gap-2">
              <button
                type="button"
                className="btn delete-pill-btn px-4"
                onClick={handleDeleteItem}
                disabled={isDeleting || isUpdating}
              >
                {isDeleting ? 'Deleting...' : 'Delete'}
              </button>

              <button
                type="button"
                className="btn btn-primary auth-btn px-4"
                onClick={handleUpdateItem}
                disabled={isUpdating || isDeleting}
              >
                {isUpdating ? 'Updating...' : 'Update'}
              </button>
            </div>
          </div>
        </div>
      )}

      <Footer />
    </>
  );
}

export default MyAddedItemsPage;