# Nafas Admin — React + Laravel API

واجهة إدارة React/TypeScript مبنية من لوحة HTML المرفقة، وتحافظ على هوية نَفَس البصرية وRTL. تتصل حصراً بالـLaravel REST API ولا تحتوي خادماً أو endpoints وهمية.

## التشغيل

يتطلب Node.js 20+ وخادم Laravel المرفق عاملاً.

```bash
cp .env.example .env
npm install
npm run dev
```

غيّر `VITE_API_BASE_URL` إلى أصل خادم Laravel دون `/api/v1` ودون شرطة نهائية. مثال: `http://127.0.0.1:8000`.

لعرض خريطة تحديد موقع مختصي SOS بتسميات عربية، أنشئ ملف `.env.local` وأضف `VITE_MAPTILER_KEY` بمفتاح MapTiler. هذا الملف مستثنى من Git. إذا لم يتوفر المفتاح تبقى حقول الإحداثيات اليدوية متاحة وتظهر رسالة توضح كيفية تفعيل الخريطة.

تُعرض أغلفة التمارين والمقاطع الصوتية من الحقل `image_url` في استجابة Laravel؛ تُحل المسارات النسبية على أصل `VITE_API_BASE_URL`، كما يُستبدل أصل `http://10.0.2.2:8000` الخاص بمحاكي Android بأصل `VITE_API_BASE_URL` مع الحفاظ على المسار والاستعلام. اترك `APP_URL` في Laravel مضبوطاً بما يلائم تطبيق Flutter، واضبط `VITE_API_BASE_URL` على العنوان الذي يمكن لمتصفح الكمبيوتر الوصول إليه. معاينة صوت «مساحات نَفَس» تُجلب عبر مسار معاينة الأدمن باستخدام Bearer token ثم تُشغّل كـBlob؛ لا تستخدم `audio_url` مباشرة لأنه قد يتطلب Authorization. ملفات Vite المحلية الموضوعة في `public` تُستخدم بمسار يبدأ من الجذر، مثل `/assets/nafas-mascot.webp`.

## التحقق

```bash
npm run test
npm run build
```

## ملاحظات Laravel

- المصادقة Bearer token صادرة من Laravel Sanctum (`createToken`) وليست SPA cookie session.
- يجب أن يسمح CORS بأصل Vite.
- الصور والصوت تستخدم `FormData` ويُترك للمتصفح توليد multipart boundary.
- الواجهة تتعامل مع 401 و403 و404 و422 و429، وتعرض رسائل Laravel دون ادعاء نجاح محلي.
- لا تضع أسرار Laravel أو بيانات دخول في متغيرات Vite؛ كل متغير `VITE_` مكشوف للمتصفح.

راجع [API_INTEGRATION_STATUS.md](./API_INTEGRATION_STATUS.md) و[MIGRATION_INVENTORY.md](./MIGRATION_INVENTORY.md) لنطاق الترحيل والقيود الدقيقة.
