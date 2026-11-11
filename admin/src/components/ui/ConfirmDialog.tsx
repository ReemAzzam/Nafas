import { useEffect, useRef } from "react";
export function ConfirmDialog({
  open,
  title,
  description,
  danger = false,
  onCancel,
  onConfirm,
}: {
  open: boolean;
  title: string;
  description: string;
  danger?: boolean;
  onCancel: () => void;
  onConfirm: () => void;
}) {
  const ref = useRef<HTMLDialogElement>(null);
  useEffect(() => {
    const d = ref.current;
    if (!d) return;
    if (open && !d.open) d.showModal();
    if (!open && d.open) d.close();
  }, [open]);
  return (
    <dialog ref={ref} className="dialog" onCancel={onCancel}>
      <h2>{title}</h2>
      <p>{description}</p>
      <div className="actions">
        <button className="ghost" onClick={onCancel}>
          إلغاء
        </button>
        <button
          className={danger ? "danger-btn" : "primary"}
          onClick={onConfirm}
        >
          تأكيد
        </button>
      </div>
    </dialog>
  );
}
