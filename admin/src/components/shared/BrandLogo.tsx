export function BrandLogo({ large = false }: { large?: boolean }) {
  return (
    <div className={`brand-logo ${large ? "brand-logo-large" : ""}`}>
      <img src="/assets/nafas-mascot.webp" alt="شعار نَفَس" />
      <div className="brand-wordmark">
        <strong>Nafas</strong>
        <small>نَفَس</small>
      </div>
    </div>
  );
}
