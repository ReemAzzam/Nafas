import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useEffect, useState, type ReactNode } from "react";
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
import type {
  Article,
  Exercise,
  Paginated,
  QueryParams,
} from "../../types/api";

type ContentKind = "article" | "exercise";
export function ArticlesPage() {
  return <ContentList kind="article" />;
}
export function ExercisesPage() {
  return <ContentList kind="exercise" />;
}
function ContentList({ kind }: { kind: ContentKind }) {
  const isArticle = kind === "article";
  const [sp, setSp] = useSearchParams();
  const qc = useQueryClient();
  const [confirm, setConfirm] = useState<{
    id: number;
    type: "delete" | "state";
    published: boolean;
  } | null>(null);
  const params: QueryParams = {
    page: Number(sp.get("page") || 1),
    per_page: 20,
    search: sp.get("search") || undefined,
    status: sp.get("status") || undefined,
    type: sp.get("type") || undefined,
  };
  const query = useQuery({
    queryKey: ["admin", `${kind}s`, params],
    queryFn: async (): Promise<Paginated<Article | Exercise>> =>
      (isArticle
        ? await adminApi.articles(params)
        : await adminApi.exercises(params)) as Paginated<Article | Exercise>,
  });
  const mutate = useMutation({
    mutationFn: async (c: NonNullable<typeof confirm>) => {
      if (c.type === "delete")
        return isArticle
          ? adminApi.deleteArticle(c.id)
          : adminApi.deleteExercise(c.id);
      return isArticle
        ? adminApi.articleState(c.id, c.published ? "unpublish" : "publish")
        : adminApi.exerciseState(c.id, c.published ? "unpublish" : "publish");
    },
    onSuccess: () => {
      setConfirm(null);
      void qc.invalidateQueries({ queryKey: ["admin", `${kind}s`] });
    },
  });
  const update = (k: string, v: string) => {
    const n = new URLSearchParams(sp);
    v ? n.set(k, v) : n.delete(k);
    if (k !== "page") n.set("page", "1");
    setSp(n);
  };
  return (
    <>
      <div className="section-head">
        <div>
          <span className="eyebrow">إدارة المحتوى</span>
          <h1>{isArticle ? "المقالات" : "التمارين"}</h1>
          <p>
            {isArticle
              ? "محتوى معرفي موثوق مع حالات نشر واضحة."
              : "كتالوج التمارين مع حماية تمارين SOS وربط الصوت الاختياري."}
          </p>
        </div>
        <Link className="primary" to={`/${kind}s/new`}>
          + {isArticle ? "مقال جديد" : "تمرين جديد"}
        </Link>
      </div>
      <div className="filters">
        <input
          className="simple-input grow"
          placeholder="ابحث بالعنوان أو المعرّف"
          value={sp.get("search") || ""}
          onChange={(e) => update("search", e.target.value)}
        />
        <select
          value={sp.get("status") || ""}
          onChange={(e) => update("status", e.target.value)}
        >
          <option value="">كل الحالات</option>
          <option value="published">منشور</option>
          <option value="draft">مسودة</option>
          <option value="deleted">محذوف</option>
        </select>
        {!isArticle && (
          <select
            value={sp.get("type") || ""}
            onChange={(e) => update("type", e.target.value)}
          >
            <option value="">كل الأنواع</option>
            <option value="breathing">تنفس</option>
            <option value="grounding">تثبيت</option>
            <option value="journaling">كتابة</option>
            <option value="relaxation">استرخاء</option>
          </select>
        )}
      </div>
      <AsyncState
        loading={query.isLoading}
        error={query.error}
        empty={query.data?.data.length === 0}
        onRetry={() => void query.refetch()}
      >
        <section className="panel">
          <div className="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>المحتوى</th>
                  <th>{isArticle ? "التفاعل" : "النوع والمدة"}</th>
                  <th>الحالة</th>
                  <th>الإجراءات</th>
                </tr>
              </thead>
              <tbody>
                {query.data?.data.map((item) => (
                  <tr key={item.id}>
                    <td>
                      <div className="content-cell">
                        <span className="thumb gradient1">
                          {isArticle ? (
                            <ImageWithFallback
                              src={(item as Article).image_url}
                              alt={`صورة ${item.title}`}
                              className="thumb-image"
                              fallback="▤"
                            />
                          ) : (
                            <ImageWithFallback
                              src={(item as Exercise).image_url}
                              alt={`صورة ${item.title}`}
                              className="thumb-image"
                              fallback="◫"
                            />
                          )}
                        </span>
                        <div>
                          <b>{item.title}</b>
                          <small>{item.slug}</small>
                        </div>
                      </div>
                    </td>
                    <td>
                      {isArticle
                        ? `${(item as Article).comments_count} تعليق · ${(item as Article).favorites_count} حفظ`
                        : `${(item as Exercise).type} · ${(item as Exercise).duration_minutes} د`}
                    </td>
                    <td>
                      <span
                        className={`badge ${item.deleted_at ? "muted" : item.is_published ? "" : "draft"}`}
                      >
                        {item.deleted_at
                          ? "محذوف"
                          : item.is_published
                            ? "منشور"
                            : "مسودة"}
                      </span>
                    </td>
                    <td>
                      <div className="row-actions">
                        {!item.deleted_at && (
                          <>
                            <Link
                              className="ghost small"
                              to={`/${kind}s/${item.id}/edit`}
                            >
                              تعديل
                            </Link>
                            <button
                              className="ghost small"
                              onClick={() =>
                                setConfirm({
                                  id: item.id,
                                  type: "state",
                                  published: item.is_published,
                                })
                              }
                            >
                              {item.is_published ? "إلغاء النشر" : "نشر"}
                            </button>
                            <button
                              className="danger-btn small"
                              disabled={
                                !isArticle && (item as Exercise).is_sos_exercise
                              }
                              onClick={() =>
                                setConfirm({
                                  id: item.id,
                                  type: "delete",
                                  published: item.is_published,
                                })
                              }
                            >
                              حذف
                            </button>
                          </>
                        )}
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          {query.data && (
            <Pagination
              meta={query.data.meta}
              onPage={(p) => update("page", String(p))}
            />
          )}
        </section>
      </AsyncState>
      <ConfirmDialog
        open={Boolean(confirm)}
        danger={confirm?.type === "delete"}
        title={confirm?.type === "delete" ? "حذف المحتوى" : "تغيير حالة النشر"}
        description={
          confirm?.type === "delete"
            ? "سيُحذف السجل من العرض العام. قد يرفض Laravel العملية إذا كان المحتوى محمياً."
            : "سيتم تحديث ظهوره للمستخدمين فور نجاح الخادم."
        }
        onCancel={() => setConfirm(null)}
        onConfirm={() => confirm && mutate.mutate(confirm)}
      />
    </>
  );
}

export function ArticleEditorPage() {
  const raw = useParams().id;
  const id = raw ? Number(raw) : undefined;
  const nav = useNavigate();
  const q = useQuery({
    queryKey: ["admin", "article", id],
    queryFn: () => adminApi.article(id!),
    enabled: Boolean(id),
  });
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const m = useMutation({
    mutationFn: (body: Record<string, unknown>) =>
      adminApi.saveArticle(id, body),
    onSuccess: () => nav("/articles"),
    onError: (e) => {
      if (e instanceof ApiError) setErrors(e.errors);
    },
  });
  if (id && q.isLoading)
    return (
      <AsyncState loading error={null}>
        {null}
      </AsyncState>
    );
  const a = q.data?.article;
  return (
    <EditorShell
      back="/articles"
      title={id ? "تعديل المقال" : "مقال جديد"}
      hint="يحفظ الحقل is_published اختيارياً، ويمكن إدارة النشر أيضاً من القائمة."
    >
      <form
        className="panel form-panel"
        onSubmit={(e) => {
          e.preventDefault();
          const f = new FormData(e.currentTarget);
          m.mutate({
            title: f.get("title"),
            slug: f.get("slug"),
            author_name: f.get("author_name"),
            summary: f.get("summary"),
            content: f.get("content"),
            image:
              f.get("image") instanceof File && (f.get("image") as File).size
                ? (f.get("image") as File)
                : undefined,
            remove_image: f.get("remove_image") === "on",
          });
        }}
      >
        <div className="form-grid">
          <Field
            label="العنوان"
            name="title"
            value={a?.title}
            error={errors.title}
          />
          <Field
            label="المعرّف slug"
            name="slug"
            value={a?.slug}
            pattern="[a-z0-9]+(?:-[a-z0-9]+)*"
            error={errors.slug}
          />
          <Field
            label="اسم الكاتب"
            name="author_name"
            value={a?.author_name || "فريق نَفَس"}
            error={errors.author_name}
          />
          <FileField
            label="الصورة (JPG/PNG/WebP، حتى 2MB)"
            name="image"
            accept="image/jpeg,image/png,image/webp"
          />
        </div>
        {a?.image_url && (
          <div className="content-cell">
            <span className="thumb gradient1">
              <ImageWithFallback
                src={a.image_url}
                alt={`الصورة الحالية للمقال ${a.title}`}
                className="thumb-image"
                fallback="▤"
              />
            </span>
            <small>الصورة الحالية</small>
          </div>
        )}
        <Area
          label="الملخص"
          name="summary"
          value={a?.summary}
          error={errors.summary}
        />
        <Area
          label="المحتوى"
          name="content"
          value={a?.content}
          large
          error={errors.content}
        />
        {id && (
          <label className="check">
            <input type="checkbox" name="remove_image" /> إزالة الصورة الحالية
          </label>
        )}
        <Submit busy={m.isPending} error={m.error} />
      </form>
    </EditorShell>
  );
}

export function ExerciseEditorPage() {
  const raw = useParams().id;
  const id = raw ? Number(raw) : undefined;
  const nav = useNavigate();
  const q = useQuery({
    queryKey: ["admin", "exercise", id],
    queryFn: () => adminApi.exercise(id!),
    enabled: Boolean(id),
  });
  const audio = useQuery({
    queryKey: ["admin", "audio", "published-options"],
    queryFn: () => adminApi.audio({ status: "published", per_page: 100 }),
  });
  const [steps, setSteps] = useState<Array<{ title: string; body: string }>>([
    { title: "", body: "" },
  ]);
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  useEffect(() => {
    if (q.data?.exercise.content_steps) setSteps(q.data.exercise.content_steps);
  }, [q.data]);
  const m = useMutation({
    mutationFn: (body: Record<string, unknown>) =>
      adminApi.saveExercise(id, body),
    onSuccess: () => nav("/exercises"),
    onError: (e) => {
      if (e instanceof ApiError) setErrors(e.errors);
    },
  });
  const x = q.data?.exercise;
  return (
    <EditorShell
      back="/exercises"
      title={id ? "تعديل التمرين" : "تمرين جديد"}
      hint="تمارين SOS المحمية قد يرفض الخادم حذفها؛ النشر مستقل عن الحفظ."
    >
      <form
        className="panel form-panel"
        onSubmit={(e) => {
          e.preventDefault();
          const f = new FormData(e.currentTarget);
          m.mutate({
            title: f.get("title"),
            slug: f.get("slug"),
            description: f.get("description"),
            type: f.get("type"),
            duration_minutes: Number(f.get("duration_minutes")),
            content_steps: steps,
            media_type: f.get("media_type") || null,
            media_url: f.get("media_url") || null,
            media_credit: f.get("media_credit") || null,
            optional_audio_track_id: f.get("optional_audio_track_id")
              ? Number(f.get("optional_audio_track_id"))
              : null,
            image:
              f.get("image") instanceof File && (f.get("image") as File).size
                ? f.get("image")
                : undefined,
            remove_image: f.get("remove_image") === "on",
          });
        }}
      >
        <div className="form-grid">
          <Field
            label="العنوان"
            name="title"
            value={x?.title}
            error={errors.title}
          />
          <Field
            label="المعرّف slug"
            name="slug"
            value={x?.slug}
            pattern="[a-z0-9]+(?:-[a-z0-9]+)*"
            error={errors.slug}
          />
          <SelectField
            label="النوع"
            name="type"
            value={x?.type || "breathing"}
            options={[
              ["breathing", "تنفس"],
              ["grounding", "تثبيت"],
              ["journaling", "كتابة"],
              ["relaxation", "استرخاء"],
            ]}
          />
          <Field
            label="المدة بالدقائق"
            name="duration_minutes"
            type="number"
            min="1"
            max="120"
            value={x?.duration_minutes}
          />
        </div>
        <Area
          label="الوصف"
          name="description"
          value={x?.description}
          error={errors.description}
        />
        <h3>خطوات التمرين</h3>
        <div className="step-builder">
          {steps.map((s, i) => (
            <div key={i}>
              <span>{i + 1}</span>
              <input
                aria-label={`عنوان الخطوة ${i + 1}`}
                value={s.title}
                onChange={(e) =>
                  setSteps((v) =>
                    v.map((z, j) =>
                      j === i ? { ...z, title: e.target.value } : z,
                    ),
                  )
                }
                required
              />
              <input
                aria-label={`تفاصيل الخطوة ${i + 1}`}
                value={s.body}
                onChange={(e) =>
                  setSteps((v) =>
                    v.map((z, j) =>
                      j === i ? { ...z, body: e.target.value } : z,
                    ),
                  )
                }
                required
              />
              <button
                type="button"
                className="ghost"
                disabled={steps.length === 1}
                onClick={() => setSteps((v) => v.filter((_, j) => j !== i))}
              >
                ×
              </button>
            </div>
          ))}
        </div>
        <button
          type="button"
          className="ghost"
          onClick={() => setSteps((v) => [...v, { title: "", body: "" }])}
        >
          + خطوة
        </button>
        <div className="form-grid">
          <SelectField
            label="نوع الوسائط"
            name="media_type"
            value={x?.media_type || ""}
            options={[
              ["", "بدون"],
              ["video", "فيديو"],
              ["audio", "صوت"],
            ]}
          />
          <Field
            label="رابط الوسائط"
            name="media_url"
            type="url"
            required={false}
            value={x?.media_url || ""}
          />
          <Field
            label="حقوق الوسائط"
            name="media_credit"
            required={false}
            value={x?.media_credit || ""}
          />
          <SelectField
            label="الصوت المرافق"
            name="optional_audio_track_id"
            value={x?.optional_audio_track_id || ""}
            options={[
              ["", "بدون"],
              ...(audio.data?.data.map(
                (t) => [String(t.id), t.title] as [string, string],
              ) || []),
            ]}
          />
          <FileField
            label="الصورة"
            name="image"
            accept="image/jpeg,image/png,image/webp"
          />
        </div>
        {id && (
          <label className="check">
            <input type="checkbox" name="remove_image" /> إزالة الصورة الحالية
          </label>
        )}
        <Submit busy={m.isPending} error={m.error} />
      </form>
    </EditorShell>
  );
}

function EditorShell({
  back,
  title,
  hint,
  children,
}: {
  back: string;
  title: string;
  hint: string;
  children: ReactNode;
}) {
  return (
    <>
      <Link className="back" to={back}>
        → العودة
      </Link>
      <div className="section-head">
        <div>
          <span className="eyebrow">محرر المحتوى</span>
          <h1>{title}</h1>
          <p>{hint}</p>
        </div>
      </div>
      {children}
    </>
  );
}
function Field({
  label,
  name,
  value,
  type = "text",
  required = true,
  error,
  ...rest
}: {
  label: string;
  name: string;
  value?: string | number | null;
  type?: string;
  required?: boolean;
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
        required={required}
        {...rest}
      />
      {error && <small className="field-error">{error[0]}</small>}
    </label>
  );
}
function Area({
  label,
  name,
  value,
  large,
  error,
}: {
  label: string;
  name: string;
  value?: string | null;
  large?: boolean;
  error?: string[];
}) {
  return (
    <label>
      {label}
      <textarea
        className={large ? "editor" : ""}
        name={name}
        defaultValue={value || ""}
        required
      />
      {error && <small className="field-error">{error[0]}</small>}
    </label>
  );
}
function SelectField({
  label,
  name,
  value,
  options,
}: {
  label: string;
  name: string;
  value: string | number;
  options: Array<[string, string]>;
}) {
  return (
    <label>
      {label}
      <select name={name} defaultValue={String(value)} required>
        {options.map(([v, l]) => (
          <option key={v} value={v}>
            {l}
          </option>
        ))}
      </select>
    </label>
  );
}
function FileField({
  label,
  name,
  accept,
}: {
  label: string;
  name: string;
  accept: string;
}) {
  return (
    <label>
      {label}
      <input name={name} type="file" accept={accept} />
    </label>
  );
}
function Submit({ busy, error }: { busy: boolean; error: Error | null }) {
  return (
    <div className="form-submit">
      {error && <span className="form-error">{error.message}</span>}
      <button className="primary" disabled={busy}>
        {busy ? "جارٍ الحفظ…" : "حفظ التغييرات"}
      </button>
    </div>
  );
}
