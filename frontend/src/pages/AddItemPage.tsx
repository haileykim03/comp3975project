import { useEffect, useState } from 'react';
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

function AddItemPage() {
    const navigate = useNavigate();

    const token = localStorage.getItem('token');
    const userString = localStorage.getItem('user');
    const currentUser: User | null = userString ? JSON.parse(userString) : null;

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

    const [error, setError] = useState('');
    const [successMessage, setSuccessMessage] = useState('');
    const [isSubmitting, setIsSubmitting] = useState(false);

    useEffect(() => {
        if (!token || !userString) {
            navigate('/signin');
        }
    }, [navigate, token, userString]);

    const resetForm = () => {
        setName('');
        setDescription('');
        setCategory(categoryOptions[0]);
        setColor('');
        setBrand('');
        setLocation(locationOptions[0]);
        setFoundAt('');
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
            setImagePreview('');
            return;
        }

        if (!file.type.startsWith('image/')) {
            setImageError('Please select a valid image file.');
            setImageFile(null);
            if (imagePreview.startsWith('blob:')) {
                URL.revokeObjectURL(imagePreview);
            }
            setImagePreview('');
            return;
        }

        if (file.size > 10 * 1024 * 1024) {
            setImageError('Image file size must be 10MB or smaller.');
            setImageFile(null);
            if (imagePreview.startsWith('blob:')) {
                URL.revokeObjectURL(imagePreview);
            }
            setImagePreview('');
            return;
        }

        setImageFile(file);
        if (imagePreview.startsWith('blob:')) {
            URL.revokeObjectURL(imagePreview);
        }
        setImagePreview(URL.createObjectURL(file));

        console.log('Selected image file:', {
            name: file.name,
            size: file.size,
            type: file.type,
            lastModified: file.lastModified,
        });
    };

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setError('');
        setSuccessMessage('');

        if (!currentUser) {
            setError('You must be signed in to add an item.');
            return;
        }

        if (category === 'Others' && !description.trim()) {
            setError('Please add a description when selecting Others.');
            return;
        }

        if (imageError) {
            setError(imageError);
            return;
        }

        try {
            setIsSubmitting(true);

            console.log('Submitting form with imageFile:', imageFile);

            const formData = new FormData();
            formData.append('name', name);
            formData.append('description', description || '');
            formData.append('category', category);
            formData.append('color', color || '');
            formData.append('brand', brand || '');
            formData.append('location', location);
            formData.append('finder_id', String(currentUser.id));
            formData.append('found_at', foundAt || '');
            if (imageFile) {
                formData.append('image', imageFile);
            }

            console.log('FormData image entry:', formData.get('image'));

            const headers: Record<string, string> = {
                Accept: 'application/json',
            };
            if (token) {
                headers.Authorization = `Bearer ${token}`;
            }

            const response = await fetch(`${import.meta.env.VITE_API_URL}/api/items`, {
                method: 'POST',
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
                console.error('Add item failed:', response.status, responseText, data);
                if (data?.errors) {
                    const firstError = Object.values(data.errors)[0];
                    if (Array.isArray(firstError) && firstError.length > 0) {
                        setError(firstError[0]);
                    } else {
                        setError(data.message || 'Failed to add item.');
                    }
                } else {
                    setError(data?.message || responseText || 'Failed to add item.');
                }
                return;
            }

            setSuccessMessage(
                'Your item was added successfully. Please bring the physical item to SE12-325 Lost & Found Office or call 778-123-4567.'
            );

            resetForm();
        } catch (error) {
            console.error(error);
            setError('Something went wrong while adding the item.');
        } finally {
            setIsSubmitting(false);
        }
    };

    return (
        <>
            <Header />

            <div className="items-page">
                <div className="container py-5">
                    <div className="text-center mb-5">
                        <h1 className="auth-title">Add Item</h1>
                        <p className="auth-subtitle">
                            Register a found item for admin review.
                        </p>
                    </div>

                    <div className="add-item-card mx-auto">
                        {error && (
                            <div className="alert alert-danger mb-4">
                                {error}
                            </div>
                        )}

                        <form onSubmit={handleSubmit}>
                            <div className="mb-3">
                                <label className="form-label text-primaryLight fw-semibold">
                                    Item Name
                                </label>
                                <input
                                    type="text"
                                    className="form-control custom-input"
                                    value={name}
                                    onChange={(e) => setName(e.target.value)}
                                    placeholder="e.g. Black Wallet"
                                    required
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
                                    Item Photo <span className="text-muted" style={{fontWeight:400, fontSize:'0.95em'}}>(max 10MB)</span>
                                </label>
                                <input
                                    type="file"
                                    name="image"
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

                            <div className="mb-4">
                                <label className="form-label text-primaryLight fw-semibold">
                                    Description
                                </label>
                                <textarea
                                    className="form-control custom-input"
                                    rows={4}
                                    value={description}
                                    onChange={(e) => setDescription(e.target.value)}
                                    placeholder="Optional notes for admin review"
                                />
                            </div>

                            {successMessage && (
                                <div className="alert alert-success mb-4">
                                    {successMessage}
                                </div>
                            )}

                            <button
                                type="submit"
                                className="btn btn-primary w-100 auth-btn"
                                disabled={isSubmitting}
                            >
                                {isSubmitting ? 'Submitting...' : 'Submit Item'}
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <Footer />
        </>
    );
}

export default AddItemPage;