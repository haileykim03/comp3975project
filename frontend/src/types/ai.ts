export interface StructuredData {
    item_type: string | null;
    category: string | null;
    color: string | null;
    brand: string | null;
    location: string | null;
    lost_time: string | null;
    keywords: string[];
    attributes: string[];
    skipped_fields: string[];
    last_requested_field: string | null;
    needs_followup: boolean;
    followup_question: string | null;
}

export interface MatchItem {
    id: number;
    name: string;
    description: string | null;
    category: string;
    color: string | null;
    brand: string | null;
    location: string;
    finder_id: number | null;
    owner_id: number | null;
    found_at: string | null;
    image_url: string | null;
    similarity_score: number;
}

export interface ChatMessage {
    role: 'user' | 'assistant';
    content: string;
}

export interface AiChatResponse {
    structured_data: StructuredData | null;
    matches: MatchItem[];
    assistant_reply: string;
    conversation_messages: ChatMessage[];
}