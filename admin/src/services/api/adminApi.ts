import { http, httpBlob, objectToFormData, toSearch } from "../httpClient";
import type {
  ApiMessage,
  Article,
  AudioTrack,
  Counselor,
  DashboardData,
  Exercise,
  Feedback,
  Hotline,
  LoginResponse,
  ModeratedComment,
  Paginated,
  QueryParams,
  User,
} from "../../types/api";

type Payload = Record<string, unknown>;
const json = (body: Payload): RequestInit => ({
  method: "POST",
  body: JSON.stringify(body),
});
export const adminApi = {
  login: (email: string, password: string) =>
    http<LoginResponse>("/auth/login", json({ email, password })),
  me: () => http<{ success: boolean; user: User }>("/auth/me"),
  logout: () => http<ApiMessage>("/auth/logout", { method: "DELETE" }),
  forgot: (email: string) =>
    http<ApiMessage>("/auth/forgot-password", json({ email })),
  reset: (body: Payload) =>
    http<ApiMessage>("/auth/reset-password", json(body)),
  verify: (email: string, otp: string) =>
    http<LoginResponse>("/auth/verify-otp", json({ email, otp })),
  resend: (email: string) =>
    http<ApiMessage>("/auth/resend-otp", json({ email })),
  changePassword: (body: Payload) =>
    http<ApiMessage>("/auth/change-password", json(body)),
  dashboard: () =>
    http<{ success: boolean; data: DashboardData }>("/admin/dashboard"),
  users: (p: QueryParams) =>
    http<Paginated<User>>(`/admin/users${toSearch(p)}`),
  user: (id: number) =>
    http<{ success: boolean; user: User }>(`/admin/users/${id}`),
  setUserBlocked: (id: number, blocked: boolean) =>
    http<{ success: boolean; message: string; user: User }>(
      `/admin/users/${id}/${blocked ? "block" : "unblock"}`,
      { method: "PATCH" },
    ),
  articles: (p: QueryParams) =>
    http<Paginated<Article>>(`/admin/articles${toSearch(p)}`),
  article: (id: number) =>
    http<{ success: boolean; article: Article }>(`/admin/articles/${id}`),
  saveArticle: (id: number | undefined, body: Payload) =>
    http<{ success: boolean; message: string; article: Article }>(
      `/admin/articles${id ? `/${id}` : ""}`,
      { method: "POST", body: objectToFormData(body) },
    ),
  articleState: (id: number, action: "publish" | "unpublish") =>
    http<ApiMessage>(`/admin/articles/${id}/${action}`, { method: "PATCH" }),
  deleteArticle: (id: number) =>
    http<ApiMessage>(`/admin/articles/${id}`, { method: "DELETE" }),
  exercises: (p: QueryParams) =>
    http<Paginated<Exercise>>(`/admin/exercises${toSearch(p)}`),
  exercise: (id: number) =>
    http<{ success: boolean; exercise: Exercise }>(`/admin/exercises/${id}`),
  saveExercise: (id: number | undefined, body: Payload) =>
    http<{ success: boolean; message: string; exercise: Exercise }>(
      `/admin/exercises${id ? `/${id}` : ""}`,
      { method: "POST", body: objectToFormData(body) },
    ),
  exerciseState: (id: number, action: "publish" | "unpublish") =>
    http<ApiMessage>(`/admin/exercises/${id}/${action}`, { method: "PATCH" }),
  deleteExercise: (id: number) =>
    http<ApiMessage>(`/admin/exercises/${id}`, { method: "DELETE" }),
  audio: (p: QueryParams) =>
    http<Paginated<AudioTrack>>(`/admin/audio-tracks${toSearch(p)}`),
  audioTrack: (id: number) =>
    http<{ success: boolean; audio_track: AudioTrack }>(
      `/admin/audio-tracks/${id}`,
    ),
  audioPreview: (id: number, signal?: AbortSignal) =>
    httpBlob(`/admin/audio-tracks/${id}/preview`, signal),
  saveAudio: (id: number | undefined, body: Payload) =>
    http<{ success: boolean; message: string; audio_track: AudioTrack }>(
      `/admin/audio-tracks${id ? `/${id}` : ""}`,
      { method: "POST", body: objectToFormData(body) },
    ),
  audioState: (id: number, action: "publish" | "unpublish" | "restore") =>
    http<ApiMessage>(`/admin/audio-tracks/${id}/${action}`, {
      method: "PATCH",
    }),
  deleteAudio: (id: number) =>
    http<ApiMessage>(`/admin/audio-tracks/${id}`, { method: "DELETE" }),
  counselors: (page = 1) =>
    http<Paginated<Counselor>>(`/admin/sos/counselors?page=${page}`),
  saveCounselor: (id: number | undefined, body: Payload) =>
    http<{ success: boolean; message: string; counselor: Counselor }>(
      `/admin/sos/counselors${id ? `/${id}` : ""}`,
      { method: "POST", body: objectToFormData(body) },
    ),
  deleteCounselor: (id: number) =>
    http<ApiMessage>(`/admin/sos/counselors/${id}`, { method: "DELETE" }),
  hotlines: (page = 1) =>
    http<Paginated<Hotline>>(`/admin/sos/hotlines?page=${page}`),
  saveHotline: (id: number | undefined, body: Payload) =>
    http<{ success: boolean; message: string; hotline: Hotline }>(
      `/admin/sos/hotlines${id ? `/${id}` : ""}`,
      { method: id ? "PATCH" : "POST", body: JSON.stringify(body) },
    ),
  deleteHotline: (id: number) =>
    http<ApiMessage>(`/admin/sos/hotlines/${id}`, { method: "DELETE" }),
  comments: (p: QueryParams) =>
    http<Paginated<ModeratedComment>>(`/admin/comment-reports${toSearch(p)}`),
  commentState: (id: number, action: "hide" | "restore", admin_note?: string) =>
    http<ApiMessage>(`/admin/comments/${id}/${action}`, {
      method: "PATCH",
      body: action === "hide" ? JSON.stringify({ admin_note }) : undefined,
    }),
  resolveReport: (
    id: number,
    status: "resolved" | "dismissed",
    admin_note?: string,
  ) =>
    http<ApiMessage>(`/admin/comment-reports/${id}`, {
      method: "PATCH",
      body: JSON.stringify({ status, admin_note }),
    }),
  feedback: (p: QueryParams) =>
    http<Paginated<Feedback>>(`/admin/feedback${toSearch(p)}`),
  feedbackItem: (id: number) =>
    http<{ success: boolean; feedback: Feedback }>(`/admin/feedback/${id}`),
  reviewFeedback: (id: number, admin_note?: string) =>
    http<ApiMessage>(`/admin/feedback/${id}/review`, {
      method: "PATCH",
      body: JSON.stringify({ admin_note }),
    }),
};
