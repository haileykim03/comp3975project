import { useState } from 'react';
import type { MatchItem } from '../types/ai';

type User = {
    id: number;
    first_name?: string;
    last_name?: string;
    email?: string;
};

type PublicMatchedItemsProps = {
    items: MatchItem[];
    token: string | null;
};

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

function formatFoundAt(dateString: string | null): string {
    if (!dateString) {
        return 'Time not available';
    }

    const date = new Date(dateString);
    const formattedDate = date.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
    });

    return `${formattedDate} · ${getTimeOfDay(dateString)}`;
}

function PublicMatchedItems({
    items,
    token,
}: PublicMatchedItemsProps) {
    const [selectedItem, setSelectedItem] = useState<MatchItem | null>(null);
    const [claimMessage, setClaimMessage] = useState('');
    const [isClaiming, setIsClaiming] = useState(false);

    const userString = localStorage.getItem('user');
    const currentUser: User | null = userString ? JSON.parse(userString) : null;

    const handleOpenModal = (item: MatchItem) => {
        setSelectedItem(item);
        setClaimMessage('');
    };

    const handleCloseModal = () => {
        setSelectedItem(null);
        setClaimMessage('');
    };

    const handleClaimItem = async () => {
        if (!selectedItem || !currentUser) {
            return;
        }

        try {
            setIsClaiming(true);
            setClaimMessage('');

            const response = await fetch(
                `${import.meta.env.VITE_API_URL}/api/items/${selectedItem.id}/claim`,
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        Authorization: token ? `Bearer ${token}` : '',
                    },
                    body: JSON.stringify({
                        owner_id: currentUser.id,
                    }),
                }
            );

            const data = await response.json();

            if (!response.ok) {
                setClaimMessage(data.message || 'Failed to submit claim.');
                return;
            }

            setClaimMessage('Claim submitted successfully. Please contact 778-123-4567 or visit the SE12-325 Lost & Found office to complete the process.');
        } catch (error) {
            console.error(error);
            setClaimMessage('Something went wrong while submitting your claim.');
        } finally {
            setIsClaiming(false);
        }
    };

    return (
        <>
            <div className="card shadow-sm border-0 mt-4">
                <div className="card-body p-4">
                    <div className="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-4">
                        <div>
                            <h3 className="h5 mb-1">Possible Matches</h3>
                            <p className="text-muted mb-0">
                                Only general public item details are shown. Open a card to review and submit a claim.
                            </p>
                        </div>

                    </div>

                    {items.length === 0 ? (
                        <div className="empty-items-state">
                            No matching items yet.
                        </div>
                    ) : (
                        <div className="match-list">
                            {items.map((item) => {
                                const icon =
                                    categoryIcons[item.category || ''] || categoryIcons.Default;
                                const title = item.category || item.name || 'Item';

                                return (
                                    <button
                                        key={item.id}
                                        type="button"
                                        className="match-list-card text-start"
                                        onClick={() => handleOpenModal(item)}
                                    >
                                        <div className="match-list-icon">{icon}</div>

                                        <div className="match-list-top">
                                            <h4 className="match-list-title">
                                                {title}
                                            </h4>
                                        </div>

                                        <div className="match-list-meta">
                                            <span className="match-list-meta-item">
                                                📍 {item.location || 'Unknown location'}
                                            </span>

                                            <span className="match-list-meta-divider">•</span>

                                            <span className="match-list-meta-item">
                                                🕒 {formatFoundAt(item.found_at)}
                                            </span>
                                        </div>

                                    </button>
                                );
                            })}
                        </div>
                    )}
                </div>
            </div>

            {selectedItem && (
                <div className="custom-modal-backdrop" onClick={handleCloseModal}>
                    <div
                        className="custom-modal-card match-modal-card"
                        onClick={(event) => event.stopPropagation()}
                    >
                        <div className="d-flex justify-content-between align-items-start gap-3 mb-4">
                            <div className="d-flex align-items-start gap-3">
                                <div className="match-modal-icon">
                                    {categoryIcons[selectedItem.category || ''] || categoryIcons.Default}
                                </div>

                                <div>
                                    <div className="d-flex flex-wrap align-items-center gap-2 mb-2">
                                        <h2 className="match-modal-title mb-0">
                                            {selectedItem.category || selectedItem.name || 'Item'}
                                        </h2>


                                    </div>

                                    <p className="text-muted mb-0">
                                        General public item details
                                    </p>
                                </div>
                            </div>

                            <button
                                type="button"
                                className="btn-close"
                                onClick={handleCloseModal}
                            ></button>
                        </div>

                        <div className="match-modal-info-grid mb-4">
                            <div className="match-modal-info-card">
                                <div className="match-modal-label">Location</div>
                                <div className="match-modal-value">
                                    {selectedItem.location || 'Unknown'}
                                </div>
                            </div>

                            <div className="match-modal-info-card">
                                <div className="match-modal-label">Found Time</div>
                                <div className="match-modal-value">
                                    {formatFoundAt(selectedItem.found_at)}
                                </div>
                            </div>
                        </div>

                        <div className="match-modal-note mb-4">
                            If this looks like your item, submit a claim for review. You may be asked to confirm details privately.
                        </div>

                        {claimMessage && (
                            <div className="alert alert-info mb-3">
                                {claimMessage}
                            </div>
                        )}

                        <div className="d-flex justify-content-end gap-2 flex-wrap">
                            <button
                                type="button"
                                className="btn btn-light"
                                onClick={handleCloseModal}
                            >
                                Close
                            </button>

                            <button
                                type="button"
                                className="btn btn-primary auth-btn px-4"
                                onClick={handleClaimItem}
                                disabled={isClaiming}
                            >
                                {isClaiming ? 'Submitting...' : 'Claim This Item'}
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </>
    );
}

export default PublicMatchedItems;