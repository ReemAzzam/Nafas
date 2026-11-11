import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useEffect, useRef, useState, type FormEvent } from "react";
import {
  Link,
  useNavigate,
  useParams,
  useSearchParams,
} from "react-router-dom";
import { AsyncState } from "../../components/ui/AsyncState";
import { ConfirmDialog } from "../../components/ui/ConfirmDialog";
import { Pagination } from "../../components/ui/Pagination";
import { ImageWithFallback } from "../../components/shared/ImageWithFallback";
import { adminApi } from "../../services/api/adminApi";
import { ApiError } from "../../services/httpClient";
import type { AudioTrack, QueryParams } from "../../types/api";

export function SpacesPage() {
  const [sp, setSp] = useSearchParams();
  const qc = useQueryClient();
  const [target, setTarget] = useState<{
    track: AudioTrack;
    action: "publish" | "unpublish" | "delete";
  } | null>(null);
  const params: QueryParams = {
    page: Number(sp.get("page") || 1),
    per_page: Number(sp.get("per_page") || 12),
    search: sp.get("search") || undefined,
    status: sp.get("status") || undefined,
    category: sp.get("category") || undefined,
  };
  const q = useQuery({
    queryKey: ["admin", "audio", params],
    queryFn: () => adminApi.audio(params),
  });
  const m = useMutation({
    mutationFn: (t: NonNullable<typeof target>) =>
      t.action === "delete"
        ? adminApi.deleteAudio(t.track.id)
        : adminApi.audioState(t.track.id, t.action),
    onSuccess: () => {
      setTarget(null);
      void qc.invalidateQueries({ queryKey: ["admin", "audio"] });
    },
  });
  const set = (k: string, v: string) => {
    const n = new URLSearchParams(sp);
    v ? n.set(k, v) : n.delete(k);
    if (k !== "page") n.set("page", "1");
    setSp(n);
  };
  return (
    <>
      <section className="spaces-hero">
        <div>
          <span className="eyebrow">مكتبة مستقلة</span>
          <h1>مساحة صغيرة… وأثر هادئ</h1>
          <p>
            إدارة المقاطع الصوتية المنشورة والمسودات، مع حقوق المحتوى وحماية
            الخطط الجارية.
          </p>
          <div className="inline">
            <Link className="primary" to="/spaces/new">
              + إضافة مقطع
            </Link>
            <Link className="ghost" to="/spaces/archive">
              المحذوفات
            </Link>
            <Link className="ghost" to="/spaces/links">
              روابط التمارين
            </Link>
          </div>
        </div>
        <div className="sound-wave">
          <i />
          <i />
          <i />
          <i />
          <i />
        </div>
      </section>
      <div className="filters tabs">
        <button
          className={!params.status ? "active" : ""}
          onClick={() => set("status", "")}
        >
          الكل
        </button>
        <button
          className={params.status === "published" ? "active" : ""}
          onClick={() => set("status", "published")}
        >
          المنشور
        </button>
        <button
          className={params.status === "draft" ? "active" : ""}
          onClick={() => set("status", "draft")}
        >
          المسودات
        </button>
        <input
          className="simple-input grow"
          placeholder="بحث بالعنوان أو slug"
          value={params.search || ""}
          onChange={(e) => set("search", e.target.value)}
        />
        <input
          className="simple-input"
          placeholder="التصنيف"
          value={params.category || ""}
          onChange={(e) => set("category", e.target.value)}
        />
      </div>
      <AsyncState
        loading={q.isLoading}
        error={q.error}
        empty={q.data?.data.length === 0}
        onRetry={() => void q.refetch()}
      >
        <div className="audio-grid">
          {q.data?.data.map((t) => (
            <AudioCard
              key={t.id}
              track={t}
              action={(action) => setTarget({ track: t, action })}
            />
          ))}
        </div>
        {q.data && (
          <Pagination
            meta={q.data.meta}
            onPage={(p) => set("page", String(p))}
          />
        )}
      </AsyncState>
      <ConfirmDialog
        open={Boolean(target)}
        danger={target?.action === "delete"}
        title={
          target?.action === "delete"
            ? "حذف المقطع"
            : target?.action === "publish"
              ? "نشر المقطع"
              : "إلغاء نشر المقطع"
        }
        description="سينفذ Laravel قواعد حماية الخطط النشطة. لن تعرض الواجهة نجاحاً إلا بعد استجابة الخادم."
        onCancel={() => setTarget(null)}
        onConfirm={() => target && m.mutate(target)}
      />
    </>
  );
}

export function AudioPreview({ track }: { track: AudioTrack }) {
  const [open, setOpen] = useState(false);
  const [loading, setLoading] = useState(false);
  const [source, setSource] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const requestRef = useRef<AbortController | null>(null);
  const objectUrlRef = useRef<string | null>(null);
  const audioRef = useRef<HTMLAudioElement | null>(null);

  function releaseObjectUrl() {
    if (objectUrlRef.current) {
      URL.revokeObjectURL(objectUrlRef.current);
      objectUrlRef.current = null;
    }
  }

  function closePreview() {
    requestRef.current?.abort();
    requestRef.current = null;
    audioRef.current?.pause();
    if (audioRef.current) audioRef.current.currentTime = 0;
    releaseObjectUrl();
    setSource(null);
    setError(null);
    setLoading(false);
    setOpen(false);
  }

  async function loadPreview() {
    requestRef.current?.abort();
    releaseObjectUrl();
    setSource(null);
    setError(null);
    setOpen(true);
    setLoading(true);

    const controller = new AbortController();
    requestRef.current = controller;
    try {
      const blob = await adminApi.audioPreview(track.id, controller.signal);
      if (controller.signal.aborted) return;
      const objectUrl = URL.createObjectURL(blob);
      objectUrlRef.current = objectUrl;
      setSource(objectUrl);
    } catch (cause) {
      if (!controller.signal.aborted) {
        setError(
          cause instanceof Error ? cause.message : "تعذر تحميل معاينة المقطع.",
        );
      }
    } finally {
      if (requestRef.current === controller) {
        requestRef.current = null;
        setLoading(false);
      }
    }
  }

  useEffect(
    () => () => {
      requestRef.current?.abort();
      requestRef.current = null;
      audioRef.current?.pause();
      releaseObjectUrl();
    },
    [],
  );

  if (!open) {
    return (
      <button className="ghost small" onClick={() => void loadPreview()}>
        معاينة الصوت
      </button>
    );
  }

  return (
    <div className="audio-preview">
      {loading && (
        <>
          <small>جارٍ تحميل المعاينة…</small>
          <button className="ghost small" onClick={closePreview}>
            إلغاء
          </button>
        </>
      )}
      {error && (
        <>
          <span role="alert">{error}</span>
          <button className="ghost small" onClick={() => void loadPreview()}>
            إعادة المحاولة
          </button>
        </>
      )}
      {source && (
        <>
          <audio
            ref={audioRef}
            aria-label={`معاينة ${track.title}`}
            controls
            preload="metadata"
            src={source}
          />
          <button
            className="ghost small"
            onClick={() => {
              if (audioRef.current) {
                audioRef.current.pause();
                audioRef.current.currentTime = 0;
              }
            }}
          >
            إيقاف
          </button>
        </>
      )}
      {!loading && (
        <button className="ghost small" onClick={closePreview}>
          إغلاق المعاينة
        </button>
      )}
    </div>
  );
}

function AudioCard({
  track,
  action,
}: {
  track: AudioTrack;
  action: (a: "publish" | "unpublish" | "delete") => void;
}) {
  return (
    <article className="audio-card">
      <div className="audio-cover">
        <ImageWithFallback
          src={track.image_url}
          alt={`غلاف ${track.title}`}
          className="audio-cover-image"
          fallback="♫"
        />
        <span className={`badge ${track.is_published ? "" : "draft"}`}>
          {track.is_published ? "منشور" : "مسودة"}
        </span>
      </div>
      <div className="audio-body">
        <small>
          {track.category} · {duration(track.duration_seconds)}
        </small>
        <h3>{track.title}</h3>
        <p>{track.description}</p>
        <div className="audio-meta">
          <span>{bytes(track.file_size_bytes)}</span>
          <span>{track.credit}</span>
          <span>{track.license_type}</span>
        </div>
        {track.audio_url && <AudioPreview track={track} />}
        <div className="row-actions">
          <Link className="ghost small" to={`/spaces/${track.id}/edit`}>
            تعديل
          </Link>
          <button
            className="ghost small"
            onClick={() => action(track.is_published ? "unpublish" : "publish")}
          >
            {track.is_published ? "إلغاء النشر" : "نشر"}
          </button>
          <button className="danger-btn small" onClick={() => action("delete")}>
            حذف
          </button>
        </div>
      </div>
    </article>
  );
}

export function AudioEditorPage() {
  const raw = useParams().id;
  const id = raw ? Number(raw) : undefined;
  const nav = useNavigate();
  const q = useQuery({
    queryKey: ["admin", "audio-track", id],
    queryFn: () => adminApi.audioTrack(id!),
    enabled: Boolean(id),
  });
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const m = useMutation({
    mutationFn: (v: Record<string, unknown>) => adminApi.saveAudio(id, v),
    onSuccess: () => nav("/spaces"),
    onError: (e) => {
      if (e instanceof ApiError) setErrors(e.errors);
    },
  });
  const t = q.data?.audio_track;
  function submit(e: FormEvent<HTMLFormElement>) {
    e.preventDefault();
    const f = new FormData(e.currentTarget);
    const audio = f.get("audio");
    const image = f.get("image");
    m.mutate({
      title: f.get("title"),
      slug: f.get("slug"),
      description: f.get("description"),
      category: f.get("category"),
      duration_seconds: Number(f.get("duration_seconds")),
      sort_order: Number(f.get("sort_order")),
      credit: f.get("credit"),
      audio: audio instanceof File && audio.size ? audio : undefined,
      image: image instanceof File && image.size ? image : undefined,
      remove_image: f.get("remove_image") === "on",
    });
  }
  return (
    <>
      <Link className="back" to="/spaces">
        → العودة لمساحات نَفَس
      </Link>
      <div className="section-head">
        <div>
          <span className="eyebrow">مساحات نَفَس</span>
          <h1>
            {id ? `تعديل «${t?.title || "المقطع"}»` : "مقطع جديد، يبدأ كمسودة"}
          </h1>
          <p>الصوت M4A بحد أقصى 35MB، والغلاف JPG/PNG/WebP بحد أقصى 2MB.</p>
        </div>
      </div>
      <AsyncState
        loading={Boolean(id) && q.isLoading}
        error={q.error}
        onRetry={() => void q.refetch()}
      >
        <form className="editor-layout" onSubmit={submit}>
          <section className="panel form-panel">
            <div className="form-grid">
              <Field
                name="title"
                label="العنوان"
                value={t?.title}
                error={errors.title}
              />
              <Field
                name="slug"
                label="المعرّف slug"
                value={t?.slug}
                pattern="[a-z0-9]+(?:-[a-z0-9]+)*"
                error={errors.slug}
              />
              <Field
                name="category"
                label="التصنيف"
                value={t?.category || "relaxation"}
                pattern="[a-z0-9]+(?:-[a-z0-9]+)*"
                error={errors.category}
              />
              <Field
                name="duration_seconds"
                label="المدة بالثواني"
                type="number"
                min="1"
                max="14400"
                value={t?.duration_seconds}
              />
              <Field
                name="sort_order"
                label="ترتيب العرض"
                type="number"
                min="0"
                max="65535"
                value={t?.sort_order || 0}
              />
              <Field
                name="credit"
                label="حقوق المحتوى"
                value={t?.credit || "فريق نَفَس"}
              />
            </div>
            <label>
              الوصف
              <textarea
                name="description"
                defaultValue={t?.description || ""}
                required
              />
              {errors.description && (
                <small className="field-error">{errors.description[0]}</small>
              )}
            </label>
            <div className="form-grid">
              <label>
                ملف M4A
                <input
                  type="file"
                  name="audio"
                  accept=".m4a,audio/mp4,audio/x-m4a"
                  required={!id}
                />
                {errors.audio && (
                  <small className="field-error">{errors.audio[0]}</small>
                )}
              </label>
              <label>
                صورة الغلاف
                <input
                  type="file"
                  name="image"
                  accept="image/jpeg,image/png,image/webp"
                />
              </label>
            </div>
            {id && (
              <label className="check">
                <input type="checkbox" name="remove_image" /> إزالة الغلاف
                الحالي
              </label>
            )}
            <div className="form-submit">
              {m.error && <span className="form-error">{m.error.message}</span>}
              <button className="primary" disabled={m.isPending}>
                {m.isPending ? "جارٍ الرفع والحفظ…" : "حفظ كمسودة"}
              </button>
            </div>
          </section>
          <aside className="panel preview-card">
            <small>معاينة المقطع</small>
            <div className="mini-art">♫</div>
            <b>{t?.title || "عنوان المقطع"}</b>
            <p>{t?.description || "سيظهر وصف المقطع هنا."}</p>
            <span className="pill">
              {t ? duration(t.duration_seconds) : "00:00"}
            </span>
          </aside>
        </form>
      </AsyncState>
    </>
  );
}

export function SpacesArchivePage() {
  const q = useQuery({
    queryKey: ["admin", "audio", { status: "deleted" }],
    queryFn: () => adminApi.audio({ status: "deleted", per_page: 100 }),
  });
  const qc = useQueryClient();
  const m = useMutation({
    mutationFn: (id: number) => adminApi.audioState(id, "restore"),
    onSuccess: () =>
      void qc.invalidateQueries({ queryKey: ["admin", "audio"] }),
  });
  return (
    <>
      <Link className="back" to="/spaces">
        → العودة للكتالوج
      </Link>
      <div className="section-head">
        <div>
          <span className="eyebrow">مساحات نَفَس</span>
          <h1>المحذوفات</h1>
          <p>الاستعادة تعيد المقطع كمسودة ولا تنشره تلقائياً.</p>
        </div>
      </div>
      <AsyncState
        loading={q.isLoading}
        error={q.error}
        empty={q.data?.data.length === 0}
      >
        <section className="panel">
          <div className="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>المقطع</th>
                  <th>التصنيف</th>
                  <th>الإجراء</th>
                </tr>
              </thead>
              <tbody>
                {q.data?.data.map((t) => (
                  <tr key={t.id}>
                    <td>{t.title}</td>
                    <td>{t.category}</td>
                    <td>
                      <button
                        className="ghost"
                        disabled={m.isPending}
                        onClick={() => m.mutate(t.id)}
                      >
                        استعادة كمسودة
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </section>
      </AsyncState>
      <div className="safe-note">
        قد يمنع Laravel حذف المقطع أو إلغاء نشره عندما يكون مستخدماً في خطة
        أسبوعية نشطة.
      </div>
    </>
  );
}
export function SpacesLinksPage() {
  const q = useQuery({
    queryKey: ["admin", "exercises", "audio-links"],
    queryFn: () => adminApi.exercises({ per_page: 100 }),
  });
  return (
    <>
      <Link className="back" to="/spaces">
        → العودة لمساحات نَفَس
      </Link>
      <div className="section-head">
        <div>
          <span className="eyebrow">الصوت المرافق</span>
          <h1>صوت يرافق التمرين، باختيار المستخدم</h1>
          <p>هذه الروابط تأتي من optional_audio_track_id في التمرين.</p>
        </div>
      </div>
      <AsyncState
        loading={q.isLoading}
        error={q.error}
        empty={q.data?.data.length === 0}
      >
        <div className="cards-list">
          {q.data?.data.map((x) => (
            <article className="panel" key={x.id}>
              <div>
                <h3>{x.title}</h3>
                <p>{x.optional_audio?.title || "لا يوجد صوت مرافق"}</p>
              </div>
              <Link className="ghost" to={`/exercises/${x.id}/edit`}>
                تعديل الربط
              </Link>
            </article>
          ))}
        </div>
      </AsyncState>
    </>
  );
}
function Field({
  name,
  label,
  value,
  type = "text",
  error,
  ...props
}: {
  name: string;
  label: string;
  value?: string | number | null;
  type?: string;
  error?: string[];
  [key: string]: unknown;
}) {
  return (
    <label>
      {label}
      <input
        name={name}
        type={type}
        defaultValue={value ?? ""}
        required
        {...props}
      />
      {error && <small className="field-error">{error[0]}</small>}
    </label>
  );
}
const duration = (s: number) =>
  `${Math.floor(s / 60)
    .toString()
    .padStart(2, "0")}:${(s % 60).toString().padStart(2, "0")}`;
const bytes = (n: number) => (n ? `${(n / 1024 / 1024).toFixed(1)} MB` : "—");
