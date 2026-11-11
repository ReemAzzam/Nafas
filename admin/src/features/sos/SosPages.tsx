import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import {
  lazy,
  Suspense,
  useEffect,
  useState,
  type FormEvent,
  type ReactNode,
} from "react";
import { Link, useNavigate, useParams } from "react-router-dom";
import { AsyncState } from "../../components/ui/AsyncState";
import { ConfirmDialog } from "../../components/ui/ConfirmDialog";
import { ImageWithFallback } from "../../components/shared/ImageWithFallback";
import { adminApi } from "../../services/api/adminApi";
import { ApiError } from "../../services/httpClient";
import type { Counselor, Hotline } from "../../types/api";
import { getSyriaCoordinatesError } from "./syriaLocation";

type CoordinateValues = { lat: string; lng: string };
const CounselorLocationMap = lazy(() => import("./CounselorLocationMap"));

export function CounselorsPage() {
  const q = useQuery({
    queryKey: ["admin", "counselors"],
    queryFn: () => adminApi.counselors(),
  });
  const qc = useQueryClient();
  const [remove, setRemove] = useState<Counselor | null>(null);
  const del = useMutation({
    mutationFn: (id: number) => adminApi.deleteCounselor(id),
    onSuccess: () => {
      setRemove(null);
      void qc.invalidateQueries({ queryKey: ["admin", "counselors"] });
    },
  });
  const toggle = useMutation({
    mutationFn: (c: Counselor) =>
      adminApi.saveCounselor(c.id, { is_active: !c.is_active }),
    onSuccess: () =>
      void qc.invalidateQueries({ queryKey: ["admin", "counselors"] }),
  });
  return (
    <>
      <SosHead
        title="مختصو SOS"
        text="إدارة بيانات الجهات العامة فقط؛ لا وصول إلى جهات المستخدم الخاصة."
        action={
          <Link className="primary" to="/sos/counselors/new">
            + مختص جديد
          </Link>
        }
      />
      <SosTabs />
      <AsyncState
        loading={q.isLoading}
        error={q.error}
        empty={q.data?.data.length === 0}
      >
        <div className="cards-list">
          {q.data?.data.map((c) => (
            <article className="panel list-card" key={c.id}>
              <div className="user-cell">
                <span className="avatar lg">
                  <ImageWithFallback
                    src={c.image_url}
                    alt={`صورة ${c.name}`}
                    className="counselor-avatar-image"
                    fallback={c.name[0]}
                  />
                </span>
                <div>
                  <h3>{c.name}</h3>
                  <p>
                    {c.specialty || "دعم نفسي"} · {c.city || "سوريا"}
                  </p>
                  <small>{c.phone}</small>
                </div>
              </div>
              <div className="row-actions">
                <span className={`badge ${c.is_active ? "" : "muted"}`}>
                  {c.is_active ? "نشط" : "غير نشط"}
                </span>
                <button className="ghost" onClick={() => toggle.mutate(c)}>
                  {c.is_active ? "تعطيل" : "تفعيل"}
                </button>
                <Link className="ghost" to={`/sos/counselors/${c.id}/edit`}>
                  تعديل
                </Link>
                <button className="danger-btn" onClick={() => setRemove(c)}>
                  حذف
                </button>
              </div>
            </article>
          ))}
        </div>
      </AsyncState>
      <ConfirmDialog
        open={Boolean(remove)}
        danger
        title="حذف المختص"
        description="سيختفي من شاشة SOS لدى المستخدمين بعد نجاح الطلب."
        onCancel={() => setRemove(null)}
        onConfirm={() => remove && del.mutate(remove.id)}
      />
    </>
  );
}
export function HotlinesPage() {
  const q = useQuery({
    queryKey: ["admin", "hotlines"],
    queryFn: () => adminApi.hotlines(),
  });
  const qc = useQueryClient();
  const [remove, setRemove] = useState<Hotline | null>(null);
  const del = useMutation({
    mutationFn: (id: number) => adminApi.deleteHotline(id),
    onSuccess: () => {
      setRemove(null);
      void qc.invalidateQueries({ queryKey: ["admin", "hotlines"] });
    },
  });
  const toggle = useMutation({
    mutationFn: (x: Hotline) =>
      adminApi.saveHotline(x.id, { is_active: !x.is_active }),
    onSuccess: () =>
      void qc.invalidateQueries({ queryKey: ["admin", "hotlines"] }),
  });
  return (
    <>
      <SosHead
        title="خطوط الدعم"
        text="أرقام قصيرة وعامة يجري التحقق منها تشغيلياً خارج الواجهة."
        action={
          <Link className="primary" to="/sos/hotlines/new">
            + خط دعم
          </Link>
        }
      />
      <SosTabs />
      <AsyncState
        loading={q.isLoading}
        error={q.error}
        empty={q.data?.data.length === 0}
      >
        <div className="cards-list">
          {q.data?.data.map((h) => (
            <article className="panel list-card" key={h.id}>
              <div>
                <h3>
                  {h.icon || "☎"} {h.title}
                </h3>
                <p>
                  {h.phone} · {h.slug}
                </p>
              </div>
              <div className="row-actions">
                <span className={`badge ${h.is_active ? "" : "muted"}`}>
                  {h.is_active ? "نشط" : "غير نشط"}
                </span>
                <button className="ghost" onClick={() => toggle.mutate(h)}>
                  {h.is_active ? "تعطيل" : "تفعيل"}
                </button>
                <Link className="ghost" to={`/sos/hotlines/${h.id}/edit`}>
                  تعديل
                </Link>
                <button className="danger-btn" onClick={() => setRemove(h)}>
                  حذف
                </button>
              </div>
            </article>
          ))}
        </div>
      </AsyncState>
      <ConfirmDialog
        open={Boolean(remove)}
        danger
        title="حذف خط الدعم"
        description="تأكد من وجود بديل صحيح قبل إزالة رقم طوارئ عام."
        onCancel={() => setRemove(null)}
        onConfirm={() => remove && del.mutate(remove.id)}
      />
    </>
  );
}

export function CounselorEditorPage() {
  const raw = useParams().id;
  const id = raw ? Number(raw) : undefined;
  const q = useQuery({
    queryKey: ["admin", "counselors"],
    queryFn: () => adminApi.counselors(),
  });
  const item = q.data?.data.find((x) => x.id === id);
  const nav = useNavigate();
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const [coordinates, setCoordinates] = useState<CoordinateValues>({
    lat: "",
    lng: "",
  });
  const coordinateError = getSyriaCoordinatesError(coordinates);
  useEffect(() => {
    if (item) {
      setCoordinates({ lat: String(item.lat), lng: String(item.lng) });
    }
  }, [item?.id, item?.lat, item?.lng]);
  const m = useMutation({
    mutationFn: (v: Record<string, unknown>) => adminApi.saveCounselor(id, v),
    onSuccess: () => nav("/sos/counselors"),
    onError: (e) => {
      if (e instanceof ApiError) setErrors(e.errors);
    },
  });
  function submit(e: FormEvent<HTMLFormElement>) {
    e.preventDefault();
    if (coordinateError) return;

    const f = new FormData(e.currentTarget);
    const image = f.get("image");
    m.mutate({
      name: f.get("name"),
      specialty: f.get("specialty") || null,
      phone: f.get("phone"),
      address: f.get("address"),
      city: f.get("city") || null,
      lat: Number(coordinates.lat),
      lng: Number(coordinates.lng),
      sort_order: Number(f.get("sort_order")),
      is_active: f.get("is_active") === "on",
      image: image instanceof File && image.size ? image : undefined,
      remove_image: f.get("remove_image") === "on",
    });
  }
  return (
    <Editor
      title={id ? "تعديل بيانات المختص" : "بيانات مختص جديد"}
      back="/sos/counselors"
    >
      <AsyncState loading={Boolean(id) && q.isLoading} error={q.error}>
        <form className="panel form-panel" onSubmit={submit}>
          <div className="form-grid">
            <Field
              label="الاسم"
              name="name"
              value={item?.name}
              error={errors.name}
            />
            <Field label="التخصص" name="specialty" value={item?.specialty} />
            <Field
              label="موبايل سوري"
              name="phone"
              type="tel"
              value={item?.phone}
              error={errors.phone}
            />
            <Field label="المدينة" name="city" value={item?.city} />
            <label>
              خط العرض
              <input
                name="lat"
                type="number"
                step="any"
                min="-90"
                max="90"
                value={coordinates.lat}
                required
                onChange={(event) =>
                  setCoordinates((current) => ({
                    ...current,
                    lat: event.target.value,
                  }))
                }
              />
              {errors.lat && (
                <small className="field-error">{errors.lat[0]}</small>
              )}
            </label>
            <label>
              خط الطول
              <input
                name="lng"
                type="number"
                step="any"
                min="-180"
                max="180"
                value={coordinates.lng}
                required
                onChange={(event) =>
                  setCoordinates((current) => ({
                    ...current,
                    lng: event.target.value,
                  }))
                }
              />
              {errors.lng && (
                <small className="field-error">{errors.lng[0]}</small>
              )}
            </label>
            <Field
              label="ترتيب العرض"
              name="sort_order"
              type="number"
              value={item?.sort_order || 0}
            />
            <label>
              الصورة
              <input
                type="file"
                name="image"
                accept="image/jpeg,image/png,image/webp"
              />
            </label>
          </div>
          {coordinateError && (
            <p className="field-error" role="alert">
              {coordinateError}
            </p>
          )}
          {item?.image_url && (
            <div className="content-cell">
              <span className="avatar lg">
                <ImageWithFallback
                  src={item.image_url}
                  alt={`الصورة الحالية للمختص ${item.name}`}
                  className="counselor-avatar-image"
                  fallback={item.name[0]}
                />
              </span>
              <small>الصورة الحالية</small>
            </div>
          )}
          <p className="counselor-map-hint">
            انقر على الخريطة أو اسحب الدبوس لتحديد الموقع.
          </p>
          <Suspense
            fallback={<div className="counselor-map counselor-map-loading" />}
          >
            <CounselorLocationMap
              values={coordinates}
              onSelect={({ lat, lng }) =>
                setCoordinates({ lat: String(lat), lng: String(lng) })
              }
            />
          </Suspense>
          <label>
            العنوان
            <textarea
              name="address"
              defaultValue={item?.address || ""}
              required
            />
          </label>
          <label className="check">
            <input
              type="checkbox"
              name="is_active"
              defaultChecked={item?.is_active ?? true}
            />{" "}
            نشط
          </label>
          {id && (
            <label className="check">
              <input type="checkbox" name="remove_image" /> إزالة الصورة
            </label>
          )}
          <Submit mutation={m} />
        </form>
      </AsyncState>
    </Editor>
  );
}
export function HotlineEditorPage() {
  const raw = useParams().id;
  const id = raw ? Number(raw) : undefined;
  const q = useQuery({
    queryKey: ["admin", "hotlines"],
    queryFn: () => adminApi.hotlines(),
  });
  const item = q.data?.data.find((x) => x.id === id);
  const nav = useNavigate();
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const m = useMutation({
    mutationFn: (v: Record<string, unknown>) => adminApi.saveHotline(id, v),
    onSuccess: () => nav("/sos/hotlines"),
    onError: (e) => {
      if (e instanceof ApiError) setErrors(e.errors);
    },
  });
  return (
    <Editor
      title={id ? "تعديل خط الدعم" : "بيانات خط دعم جديد"}
      back="/sos/hotlines"
    >
      <AsyncState loading={Boolean(id) && q.isLoading} error={q.error}>
        <form
          className="panel form-panel"
          onSubmit={(e) => {
            e.preventDefault();
            const f = new FormData(e.currentTarget);
            m.mutate({
              title: f.get("title"),
              slug: f.get("slug"),
              phone: f.get("phone"),
              icon: f.get("icon") || null,
              sort_order: Number(f.get("sort_order")),
              is_active: f.get("is_active") === "on",
            });
          }}
        >
          <div className="form-grid">
            <Field
              label="اسم الجهة"
              name="title"
              value={item?.title}
              error={errors.title}
            />
            <Field
              label="المعرّف slug"
              name="slug"
              value={item?.slug}
              pattern="[a-z0-9]+(?:-[a-z0-9]+)*"
              error={errors.slug}
            />
            <Field
              label="الرقم"
              name="phone"
              value={item?.phone}
              pattern="[0-9]{2,10}"
              error={errors.phone}
            />
            <Field label="الأيقونة" name="icon" value={item?.icon} />
            <Field
              label="ترتيب العرض"
              name="sort_order"
              type="number"
              value={item?.sort_order || 0}
            />
          </div>
          <label className="check">
            <input
              type="checkbox"
              name="is_active"
              defaultChecked={item?.is_active ?? true}
            />{" "}
            نشط
          </label>
          <Submit mutation={m} />
        </form>
      </AsyncState>
    </Editor>
  );
}
function SosTabs() {
  return (
    <div className="content-tabs">
      <Link to="/sos/counselors">المختصون</Link>
      <Link to="/sos/hotlines">خطوط الدعم</Link>
    </div>
  );
}
function SosHead({
  title,
  text,
  action,
}: {
  title: string;
  text: string;
  action: ReactNode;
}) {
  return (
    <div className="section-head">
      <div>
        <span className="eyebrow">الدعم السريع SOS</span>
        <h1>{title}</h1>
        <p>{text}</p>
      </div>
      {action}
    </div>
  );
}
function Editor({
  title,
  back,
  children,
}: {
  title: string;
  back: string;
  children: ReactNode;
}) {
  return (
    <>
      <Link className="back" to={back}>
        → العودة
      </Link>
      <div className="section-head">
        <div>
          <span className="eyebrow">SOS</span>
          <h1>{title}</h1>
          <p>تعرض للمستخدم بيانات عامة فقط. تأكد من صحتها وموافقة الجهة.</p>
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
  error,
  ...props
}: {
  label: string;
  name: string;
  value?: string | number | null;
  type?: string;
  error?: string[];
  [k: string]: unknown;
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
function Submit({
  mutation,
}: {
  mutation: { isPending: boolean; error: Error | null };
}) {
  return (
    <div className="form-submit">
      {mutation.error && (
        <span className="form-error">{mutation.error.message}</span>
      )}
      <button className="primary" disabled={mutation.isPending}>
        {mutation.isPending ? "جارٍ الحفظ…" : "حفظ"}
      </button>
    </div>
  );
}
