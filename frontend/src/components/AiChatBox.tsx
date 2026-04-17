import { useEffect, useRef, useState } from 'react';
import type {
    AiChatResponse,
    ChatMessage,
    StructuredData,
} from '../types/ai';

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

interface AiChatBoxProps {
    token: string;
    onResult: (result: AiChatResponse) => void;
}

function AiChatBox({ token, onResult }: AiChatBoxProps) {
    const [message, setMessage] = useState('');
    const [loading, setLoading] = useState(false);
    const [errorMessage, setErrorMessage] = useState('');
    const [messages, setMessages] = useState<ChatMessage[]>([
        {
            role: 'assistant',
            content: 'Tell me about the item you lost.',
        },
    ]);
    const [structuredData, setStructuredData] = useState<StructuredData | null>(null);
    const [imageFile, setImageFile] = useState<File | null>(null);
    const [imagePreview, setImagePreview] = useState('');
    const [selectedCategory, setSelectedCategory] = useState(categoryOptions[0]);

    const apiBaseUrl = import.meta.env.VITE_API_URL ?? 'http://127.0.0.1:8000';
    const inputRef = useRef<HTMLTextAreaElement | null>(null);
    const fileInputRef = useRef<HTMLInputElement | null>(null);
    const chatContainerRef = useRef<HTMLDivElement | null>(null);

    useEffect(() => {
        const el = chatContainerRef.current;
        if (el) {
            el.scrollTop = el.scrollHeight;
        }
    }, [messages, loading]);

    useEffect(() => {
        inputRef.current?.focus();
    }, []);

    const focusInput = () => {
        setTimeout(() => {
            inputRef.current?.focus();
        }, 0);
    };

    const resetChat = () => {
        setMessages([
            {
                role: 'assistant',
                content: 'Tell me about the item you lost.',
            },
        ]);
        setStructuredData(null);
        setMessage('');
        setImageFile(null);
        setImagePreview('');
        setErrorMessage('');
        onResult({
            structured_data: null,
            matches: [],
            assistant_reply: 'Tell me about the item you lost.',
            conversation_messages: [],
        });
        focusInput();
    };

    const handleImageChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0] ?? null;
        
        if (!file) {
            setImageFile(null);
            setImagePreview('');
            return;
        }

        if (!file.type.startsWith('image/')) {
            setErrorMessage('Please select a valid image file.');
            setImageFile(null);
            setImagePreview('');
            return;
        }

        if (file.size > 10 * 1024 * 1024) {
            setErrorMessage('Image file size must be 10MB or smaller.');
            setImageFile(null);
            setImagePreview('');
            return;
        }

        setImageFile(file);
        setImagePreview(URL.createObjectURL(file));
        setErrorMessage('');
    };

    const handleImageSearch = async () => {
        if (!imageFile || loading) return;

        const formData = new FormData();
        formData.append('image', imageFile);
        formData.append('category', selectedCategory);

        setLoading(true);
        setErrorMessage('');

        try {
            const response = await fetch(`${apiBaseUrl}/api/ai/search-by-image`, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    Authorization: `Bearer ${token}`,
                },
                body: formData,
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.message || 'Failed to search by image.');
            }

            const result = data as AiChatResponse;
            setStructuredData(result.structured_data);
            
            const chatMessages: ChatMessage[] = [
                ...messages,
                {
                    role: 'user',
                    content: '[Uploaded an image for search]',
                },
                {
                    role: 'assistant',
                    content: result.assistant_reply,
                },
            ];

            setMessages(chatMessages);
            setImageFile(null);
            setImagePreview('');
            onResult({ ...result, conversation_messages: chatMessages });
        } catch (error) {
            const text = error instanceof Error ? error.message : 'Something went wrong.';
            setErrorMessage(text);
        } finally {
            setLoading(false);
        }
    };

    const sendMessage = async () => {
        const trimmedMessage = message.trim();

        if (!trimmedMessage || loading) {
            focusInput();
            return;
        }

        const nextMessages: ChatMessage[] = [
            ...messages,
            {
                role: 'user',
                content: trimmedMessage,
            },
        ];

        setMessages(nextMessages);
        setMessage('');
        setLoading(true);
        setErrorMessage('');
        focusInput();

        try {
            const response = await fetch(`${apiBaseUrl}/api/ai/chat`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    Authorization: `Bearer ${token}`,
                },
                body: JSON.stringify({
                    message: trimmedMessage,
                    previous_structured_data: structuredData,
                    conversation_messages: nextMessages,
                }),
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.message || 'Failed to get AI response.');
            }

            const result = data as AiChatResponse;

            setStructuredData(result.structured_data);
            setMessages(result.conversation_messages);
            onResult(result);
        } catch (error) {
            const text =
                error instanceof Error ? error.message : 'Something went wrong.';
            setErrorMessage(text);
        } finally {
            setLoading(false);
            focusInput();
        }
    };

    const handleSubmit = async (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        await sendMessage();
    };

    const handleKeyDown = async (
        event: React.KeyboardEvent<HTMLTextAreaElement>
    ) => {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();

            if (!loading) {
                await sendMessage();
            }
        }
    };

    return (
        <div className="card shadow-sm border-0">
            <div className="card-header bg-white border-0 pt-4 pb-2">
                <h3 className="h5 mb-1">AI Lost Item Chat</h3>
                <p className="text-muted small mb-0">
                    Describe your lost item and answer follow-up questions.
                </p>
            </div>

            <div
                className="card-body p-0"
                style={{ height: '600px', display: 'flex', flexDirection: 'column' }}
            >
                <div
                    ref={chatContainerRef}
                    className="flex-grow-1 px-3 pt-3"
                    style={{
                        overflowY: 'auto',
                        backgroundColor: '#f8f9fa',
                    }}
                >
                    {messages.map((chatMessage, index) => (
                        <div
                            key={`${chatMessage.role}-${index}`}
                            className={`d-flex mb-3 ${chatMessage.role === 'user'
                                    ? 'justify-content-end'
                                    : 'justify-content-start'
                                }`}
                        >
                            <div
                                className={`px-3 py-2 rounded-4 ${chatMessage.role === 'user'
                                        ? 'bg-dark text-white'
                                        : 'bg-white border'
                                    }`}
                                style={{
                                    maxWidth: '78%',
                                    whiteSpace: 'pre-wrap',
                                }}
                            >
                                <div className="small fw-bold mb-1">
                                    {chatMessage.role === 'user' ? 'You' : 'AI'}
                                </div>
                                <div>{chatMessage.content}</div>
                            </div>
                        </div>
                    ))}

                    {loading && (
                        <div className="d-flex justify-content-start mb-3">
                            <div
                                className="px-3 py-2 rounded-4 bg-white border"
                                style={{ maxWidth: '78%' }}
                            >
                                <div className="small fw-bold mb-1">AI</div>
                                <div>Typing...</div>
                            </div>
                        </div>
                    )}
                </div>

                <div className="border-top p-3 bg-white">
                    {errorMessage && (
                        <div className="alert alert-danger py-2 mb-3" role="alert">
                            {errorMessage}
                        </div>
                    )}

                    {imagePreview && (
                        <div className="mb-3">
                            <img 
                                src={imagePreview} 
                                alt="Selected" 
                                className="img-fluid rounded"
                                style={{maxHeight: '200px'}}
                            />
                            <div className="mt-3 mb-3">
                                <label className="form-label small fw-semibold mb-2">Item Category</label>
                                <select 
                                    className="form-select form-select-sm"
                                    value={selectedCategory}
                                    onChange={(e) => setSelectedCategory(e.target.value)}
                                >
                                    {categoryOptions.map((cat) => (
                                        <option key={cat} value={cat}>{cat}</option>
                                    ))}
                                </select>
                            </div>
                            <div className="d-flex gap-2 mt-2">
                                <button
                                    type="button"
                                    onClick={handleImageSearch}
                                    className="btn btn-success rounded-4 flex-grow-1"
                                    disabled={loading}
                                >
                                    {loading ? 'Searching...' : 'Search by Image'}
                                </button>
                                <button
                                    type="button"
                                    onClick={() => {
                                        setImageFile(null);
                                        setImagePreview('');
                                        if (fileInputRef.current) fileInputRef.current.value = '';
                                    }}
                                    className="btn btn-secondary rounded-4"
                                    disabled={loading}
                                >
                                    Cancel
                                </button>
                            </div>
                        </div>
                    )}

                    {structuredData && !structuredData.needs_followup ? (
                        <div className="d-flex gap-2">
                            <button
                                type="button"
                                onClick={resetChat}
                                className="btn btn-primary flex-grow-1 rounded-4"
                            >
                                Start New Search
                            </button>
                        </div>
                    ) : (
                        <form onSubmit={handleSubmit}>
                            <div className="d-flex gap-2 align-items-end">
                                <textarea
                                    ref={inputRef}
                                    className="form-control rounded-4"
                                    rows={2}
                                    value={message}
                                    onChange={(event) => setMessage(event.target.value)}
                                    onKeyDown={handleKeyDown}
                                    placeholder="Describe your lost item..."
                                    style={{ resize: 'none' }}
                                />

                                <button
                                    type="button"
                                    className="btn btn-outline-secondary rounded-4"
                                    onClick={() => fileInputRef.current?.click()}
                                    disabled={loading}
                                    title="Upload image to search"
                                >
                                    📸
                                </button>

                                <input
                                    ref={fileInputRef}
                                    type="file"
                                    accept="image/*"
                                    className="d-none"
                                    onChange={handleImageChange}
                                />

                                <button
                                    type="submit"
                                    className="btn btn-dark rounded-4 px-4"
                                    disabled={loading || !message.trim()}
                                >
                                    {loading ? 'Sending...' : 'Send'}
                                </button>
                            </div>
                        </form>
                    )}
                </div>
            </div>
        </div>
    );
}

export default AiChatBox;