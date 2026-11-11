export type Id = number;

export interface User {
  id: Id;
  name: string;
  display_name: string | null;
  email: string;
  role: "user" | "admin";
  phone?: string | null;
  formatted_phone?: string | null;
  profile_image_url?: string | null;
  status?: string | null;
  onboarding_status?: string;
  is_anonymous?: boolean;
  is_blocked?: boolean;
  date_of_birth?: string | null;
  age?: number | null;
  created_at?: string;
  deleted_at?: string | null;
  email_verified_at?: string | null;
  last_activity_at?: string | null;
  counts?: {
    mood_logs: number;
    activity_completions: number;
    journal_entries: number;
    comments: number;
    comment_reports: number;
  };
  goals?: NamedItem[];
  support_methods?: NamedItem[];
}
export interface NamedItem {
  id: Id;
  title?: string;
  name?: string;
  icon?: string;
}
export interface Article {
  id: Id;
  title: string;
  slug: string;
  summary: string;
  content: string;
  author_name: string;
  image_url: string | null;
  is_published: boolean;
  comments_count: number;
  favorites_count: number;
  deleted_at: string | null;
  goals?: NamedItem[];
}
export interface AudioTrack {
  id: Id;
  slug: string;
  title: string;
  description: string;
  category: string;
  duration_seconds: number;
  audio_url: string;
  preview_url?: string | null;
  image_url: string | null;
  credit: string;
  license_type: string;
  mime_type: string;
  file_size_bytes: number;
  is_published: boolean;
  sort_order: number;
  deleted_at: string | null;
}
export interface Exercise {
  id: Id;
  slug: string;
  title: string;
  description: string;
  type: "breathing" | "grounding" | "journaling" | "relaxation";
  duration_minutes: number;
  content_steps: Array<{ title: string; body: string }>;
  media_type: "video" | "audio" | null;
  media_url: string | null;
  media_credit: string | null;
  image_url: string | null;
  optional_audio_track_id: number | null;
  optional_audio?: AudioTrack | null;
  is_published: boolean;
  is_sos_exercise: boolean;
  deleted_at: string | null;
  goals?: NamedItem[];
}
export interface Counselor {
  id: Id;
  name: string;
  specialty: string | null;
  phone: string;
  address: string;
  city: string | null;
  lat: number | string;
  lng: number | string;
  image_url?: string | null;
  sort_order: number;
  is_active: boolean;
}
export interface Hotline {
  id: Id;
  slug: string;
  title: string;
  phone: string;
  icon: string | null;
  sort_order: number;
  is_active: boolean;
}
export interface CommentReport {
  id: Id;
  reason: string;
  status: "pending" | "resolved" | "dismissed";
  reporter?: { id: Id; display_name: string };
  admin_note: string | null;
  created_at: string;
}
export interface ModeratedComment {
  id: Id;
  body: string;
  status: string;
  author: { id: Id; display_name: string; is_deleted: boolean };
  article: { id: Id; title: string; is_deleted: boolean } | null;
  reports_count: number;
  pending_reports_count: number;
  reports: CommentReport[];
  created_at: string;
}
export interface Feedback {
  id: Id;
  rating: number;
  message: string | null;
  status: "pending" | "reviewed";
  user: { id: Id; display_name: string; is_deleted: boolean } | null;
  admin_note: string | null;
  reviewed_at: string | null;
  created_at: string;
}
export interface PaginationMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  from: number | null;
  to: number | null;
}
export interface Paginated<T> {
  data: T[];
  meta: PaginationMeta;
  links?: unknown;
  success: boolean;
}
export interface ApiMessage {
  success: boolean;
  message: string;
}
export interface DashboardData {
  users: {
    active: number;
    blocked: number;
    deleted: number;
    new_last_7_days: number;
    new_last_30_days: number;
  };
  engagement: {
    mood_logs_total: number;
    active_users_last_7_days: number;
    activity_completions_total: number;
    sos_opens_last_7_days: number;
    first_7_days_retention: {
      rate_percent: number;
      retained_users: number;
      eligible_users: number;
      required_consecutive_days: number;
    };
  };
  review_queue: { pending_comment_reports: number; pending_feedback: number };
  content: {
    published_articles: number;
    draft_articles: number;
    published_exercises: number;
    draft_exercises: number;
  };
  weekly_activity: Array<{
    date: string;
    mood_logs: number;
    active_users: number;
    sos_opens: number;
  }>;
}
export interface LoginResponse extends ApiMessage {
  token: string;
  user: User;
}
export interface ValidationErrorBody {
  message?: string;
  errors?: Record<string, string[]>;
}
export interface QueryParams {
  page?: number;
  per_page?: number;
  search?: string;
  status?: string;
  role?: string;
  onboarding_status?: string;
  type?: string;
  category?: string;
  rating?: number | string;
}
